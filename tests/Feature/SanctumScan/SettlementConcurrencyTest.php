<?php

namespace Tests\Feature\SanctumScan;

use App\Models\SanctumScanExchange;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Required by the G4 MySQL job (set SANCTUM_SETTLEMENT_CONCURRENCY=1 and run
 * against a migrated, disposable MySQL database). Skipped on SQLite and
 * skipped by default so a developer's local MySQL database is never modified.
 *
 * The test starts two independent PHP workers. A third connection temporarily
 * holds the exchange row lock until both workers have reached their own
 * SELECT ... FOR UPDATE call. Releasing that lock makes the two manager
 * transitions compete under InnoDB rather than simulating concurrency with
 * sequential calls in one PHPUnit process.
 */
class SettlementConcurrencyTest extends TestCase
{
    private ?int $merchantId = null;

    private ?int $buyerId = null;

    private ?int $resourceId = null;

    private ?int $exchangeId = null;

    private ?string $barrierDirectory = null;

    /** @var array<string, Process> */
    private array $workerProcesses = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Settlement concurrency requires MySQL row-level locking.');
        }

        $flag = getenv('SANCTUM_SETTLEMENT_CONCURRENCY');

        if (! in_array($flag, ['1', 'true'], true)) {
            $this->markTestSkipped('Set SANCTUM_SETTLEMENT_CONCURRENCY=1 to run against a disposable MySQL database.');
        }

        $suffix = substr((string) microtime(true), -6);

        $this->merchantId = (int) DB::table('merchants')->insertGetId([
            'firstname' => 'Concurrency',
            'lastname' => 'Merchant',
            'username' => 'concurrency-merchant-'.$suffix,
            'email' => 'concurrency-merchant-'.$suffix.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->buyerId = (int) DB::table('users')->insertGetId([
            'firstname' => 'Concurrency',
            'lastname' => 'Buyer',
            'username' => 'concurrency-buyer-'.$suffix,
            'email' => 'concurrency-buyer-'.$suffix.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->resourceId = (int) DB::table('sanctum_scan_resources')->insertGetId([
            'merchant_id' => $this->merchantId,
            'resource_type' => 'agricultural_batch',
            'display_name' => 'Concurrency fixture batch',
            'category' => 'Potatoes',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->exchangeId = (int) DB::table('sanctum_scan_exchanges')->insertGetId([
            'merchant_id' => $this->merchantId,
            'buyer_user_id' => $this->buyerId,
            'resource_id' => $this->resourceId,
            'resource_type' => 'agricultural_batch',
            'resource_name_snapshot' => 'Concurrency fixture batch',
            'quantity' => '100.0000',
            'unit' => 'kg',
            'amount' => '25000.00000000',
            'currency' => 'KES',
            'status' => 'received',
            'settlement_status' => 'payment_evidence_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sanctum_scan_exchange_payment_evidence')->insert([
            'exchange_id' => $this->exchangeId,
            'version' => 1,
            'rail' => 'bank_transfer',
            'reference' => 'CONCURRENCY-'.$suffix,
            'amount' => '25000.00000000',
            'currency' => 'KES',
            'verification_status' => 'unverified',
            'submitted_by_guard' => 'api',
            'submitted_by_id' => $this->buyerId,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_two_competing_manager_transitions_append_exactly_one_decision(): void
    {
        $this->barrierDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'sanctum-settlement-'
            .$this->exchangeId
            .'-'
            .bin2hex(random_bytes(6));

        if (! mkdir($this->barrierDirectory, 0700, true) && ! is_dir($this->barrierDirectory)) {
            $this->fail('Could not create the settlement concurrency barrier directory.');
        }

        $workerPath = base_path('tests/Support/SanctumScan/settlement-concurrency-worker.php');

        $this->workerProcesses = [
            'verified' => new Process([
                PHP_BINARY,
                $workerPath,
                (string) $this->exchangeId,
                'payment_verified',
                '101',
                'payment_evidence_submitted',
                $this->barrierDirectory,
                'verified',
            ], base_path(), ['APP_ENV' => 'testing']),
            'rejected' => new Process([
                PHP_BINARY,
                $workerPath,
                (string) $this->exchangeId,
                'payment_rejected',
                '102',
                'payment_evidence_submitted',
                $this->barrierDirectory,
                'rejected',
            ], base_path(), ['APP_ENV' => 'testing']),
        ];

        foreach ($this->workerProcesses as $process) {
            $process->setTimeout(45);
        }

        $coordinator = DB::connection();
        $coordinator->beginTransaction();

        try {
            $coordinator->table('sanctum_scan_exchanges')
                ->where('id', $this->exchangeId)
                ->lockForUpdate()
                ->first();

            foreach ($this->workerProcesses as $process) {
                $process->start();
            }

            $this->waitForBarrierFiles([
                'verified.ready',
                'rejected.ready',
            ]);

            $verifiedConnectionId = (int) file_get_contents($this->barrierDirectory.'/verified.ready');
            $rejectedConnectionId = (int) file_get_contents($this->barrierDirectory.'/rejected.ready');

            $this->assertGreaterThan(0, $verifiedConnectionId);
            $this->assertGreaterThan(0, $rejectedConnectionId);
            $this->assertNotSame(
                $verifiedConnectionId,
                $rejectedConnectionId,
                'The competing workers must use independent MySQL sessions.'
            );

            file_put_contents($this->barrierDirectory.'/start', 'go', LOCK_EX);

            // Each worker writes its marker immediately before executing the
            // manager's SELECT ... FOR UPDATE. The coordinator still owns the
            // row lock, so both manager transactions are queued concurrently.
            $this->waitForBarrierFiles([
                'verified.lock-query-started',
                'rejected.lock-query-started',
            ]);

            // Confirm MySQL itself sees both workers executing their row-lock
            // statements while the coordinator still owns the exchange lock.
            $this->waitForMySqlLockQueries([
                $verifiedConnectionId,
                $rejectedConnectionId,
            ]);

            $coordinator->commit();

            foreach ($this->workerProcesses as $process) {
                $process->wait();
            }
        } finally {
            if ($coordinator->transactionLevel() > 0) {
                $coordinator->rollBack();
            }

            foreach ($this->workerProcesses as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }

        $results = [];

        foreach ($this->workerProcesses as $name => $process) {
            $this->assertTrue(
                $process->isSuccessful(),
                sprintf(
                    'The %s worker failed. STDOUT: %s STDERR: %s',
                    $name,
                    $process->getOutput(),
                    $process->getErrorOutput()
                )
            );

            $results[$name] = json_decode(
                trim($process->getOutput()),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        $winners = array_values(array_filter(
            $results,
            fn (array $result): bool => $result['ok'] === true
        ));
        $losers = array_values(array_filter(
            $results,
            fn (array $result): bool => $result['ok'] === false
        ));

        $this->assertCount(1, $winners, 'Exactly one competing transition must succeed.');
        $this->assertCount(1, $losers, 'Exactly one competing transition must lose.');
        $this->assertSame('stale_expected_status', $losers[0]['error']);

        $finalStatus = DB::table('sanctum_scan_exchanges')
            ->where('id', $this->exchangeId)
            ->value('settlement_status');

        $this->assertContains($finalStatus, ['payment_verified', 'payment_rejected']);
        $this->assertSame($winners[0]['to'], $finalStatus);

        $events = DB::table('sanctum_scan_exchange_events')
            ->where('exchange_id', $this->exchangeId)
            ->where('event_type', 'settlement_status_changed')
            ->get();

        $this->assertCount(1, $events, 'Only the winning decision may append a settlement event.');

        $event = $events->first();
        $eventData = json_decode((string) $event->event_data, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('payment_evidence_submitted', $eventData['from']);
        $this->assertSame($finalStatus, $eventData['to']);
        $this->assertSame((int) $winners[0]['actor_id'], (int) $event->recorded_by_id);
        $this->assertSame('admin', $event->recorded_by_guard);
    }

    private function waitForBarrierFiles(array $fileNames, int $timeoutSeconds = 15): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $missing = array_values(array_filter(
                $fileNames,
                fn (string $fileName): bool => ! is_file($this->barrierDirectory.'/'.$fileName)
            ));

            if ($missing === []) {
                return;
            }

            foreach ($this->workerProcesses as $name => $process) {
                if (! $process->isRunning() && $process->getExitCode() !== null) {
                    $this->fail(sprintf(
                        'The %s worker exited before reaching the concurrency barrier. STDOUT: %s STDERR: %s',
                        $name,
                        $process->getOutput(),
                        $process->getErrorOutput()
                    ));
                }
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->fail('Timed out waiting for concurrency barrier files: '.implode(', ', $missing));
    }


    private function waitForMySqlLockQueries(array $connectionIds, int $timeoutSeconds = 20): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $rows = DB::select(
                'SELECT ID, INFO FROM information_schema.PROCESSLIST WHERE ID IN (?, ?) AND INFO IS NOT NULL',
                $connectionIds
            );

            $observedConnectionIds = [];

            foreach ($rows as $row) {
                $query = strtolower((string) $row->info);

                if (
                    str_contains($query, 'sanctum_scan_exchanges')
                    && preg_match('/\bfor\s+update\b/', $query) === 1
                ) {
                    $observedConnectionIds[(int) $row->id] = true;
                }
            }

            if (count($observedConnectionIds) === 2) {
                return;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->fail('MySQL did not show both competing SELECT ... FOR UPDATE statements before the coordinator released its lock.');
    }

    protected function tearDown(): void
    {
        foreach ($this->workerProcesses as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }

        if ($this->barrierDirectory !== null && is_dir($this->barrierDirectory)) {
            foreach (glob($this->barrierDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($this->barrierDirectory);
        }

        if ($this->exchangeId !== null) {
            DB::table('sanctum_scan_exchange_events')->where('exchange_id', $this->exchangeId)->delete();
            DB::table('sanctum_scan_exchange_payment_evidence')->where('exchange_id', $this->exchangeId)->delete();
            DB::table('sanctum_scan_exchanges')->where('id', $this->exchangeId)->delete();
        }

        if ($this->resourceId !== null) {
            DB::table('sanctum_scan_resources')->where('id', $this->resourceId)->delete();
        }

        if ($this->buyerId !== null) {
            DB::table('users')->where('id', $this->buyerId)->delete();
        }

        if ($this->merchantId !== null) {
            DB::table('merchants')->where('id', $this->merchantId)->delete();
        }

        parent::tearDown();
    }
}
