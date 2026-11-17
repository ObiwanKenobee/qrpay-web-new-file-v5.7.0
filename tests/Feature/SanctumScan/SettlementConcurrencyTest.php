<?php

namespace Tests\Feature\SanctumScan;

use App\Models\SanctumScanExchange;
use App\Services\SanctumScan\ExchangeSettlementManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Required by the G4 MySQL job (set SANCTUM_SETTLEMENT_CONCURRENCY=1 and run
 * against a migrated MySQL database). Skipped on SQLite and skipped by
 * default so a developer's local MySQL database is never modified.
 */
class SettlementConcurrencyTest extends TestCase
{
    private ?int $merchantId = null;

    private ?int $buyerId = null;

    private ?int $resourceId = null;

    private ?int $exchangeId = null;

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
    }

    public function test_two_writers_serialize_on_the_exchange_row_and_only_one_transition_wins(): void
    {
        config(['database.connections.settlement_probe' => config('database.connections.mysql')]);
        DB::purge('settlement_probe');

        $default = DB::connection();
        $probe = DB::connection('settlement_probe');

        $default->beginTransaction();

        try {
            $default->table('sanctum_scan_exchanges')
                ->where('id', $this->exchangeId)
                ->lockForUpdate()
                ->first();

            $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $probe->beginTransaction();

            try {
                $probe->table('sanctum_scan_exchanges')
                    ->where('id', $this->exchangeId)
                    ->lockForUpdate()
                    ->first();

                $this->fail('A second writer must not acquire the exchange row lock while it is held.');
            } catch (QueryException $exception) {
                $this->assertSame(
                    1205,
                    (int) ($exception->errorInfo[1] ?? 0),
                    'Expected an InnoDB lock wait timeout, got: '.$exception->getMessage()
                );
            } finally {
                $probe->rollBack();
            }
        } finally {
            $default->commit();
        }

        $manager = app(ExchangeSettlementManager::class);
        $exchange = SanctumScanExchange::query()->findOrFail($this->exchangeId);

        $winner = $manager->transition(
            $exchange,
            'payment_verified',
            'admin',
            1,
            'First operator reconciled the settlement statement.',
            'payment_evidence_submitted'
        );
        $this->assertTrue($winner->ok);

        $loser = $manager->transition(
            $exchange->fresh(),
            'payment_rejected',
            'admin',
            2,
            'Second operator acted on a stale expected status.',
            'payment_evidence_submitted'
        );
        $this->assertFalse($loser->ok);
        $this->assertSame('stale_expected_status', $loser->error);

        $this->assertSame(
            'payment_verified',
            DB::table('sanctum_scan_exchanges')->where('id', $this->exchangeId)->value('settlement_status')
        );
        $this->assertSame(1, DB::table('sanctum_scan_exchange_events')
            ->where('exchange_id', $this->exchangeId)
            ->where('event_type', 'settlement_status_changed')
            ->count());
    }

    protected function tearDown(): void
    {
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

        DB::purge('settlement_probe');

        parent::tearDown();
    }
}
