<?php

namespace Tests\Feature\SanctumScan;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PaymentEvidenceVersioningMigrationTest extends TestCase
{
    private const TABLE = 'sanctum_scan_exchange_payment_evidence';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        $this->runMigration('2026_11_13_150000_create_sanctum_scan_resources_table.php');
        $this->runMigration('2026_11_13_160000_create_sanctum_scan_exchanges_table.php');
        $this->runMigration('2026_11_13_162000_create_sanctum_scan_exchange_payment_evidence_table.php');

        DB::table('users')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('merchants')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sanctum_scan_resources')->insert([
            'id' => 1,
            'merchant_id' => 1,
            'resource_type' => 'agricultural_batch',
            'display_name' => 'Payment evidence rollback fixture',
            'category' => 'Potatoes',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_versioning_migration_adds_the_composite_unique_key_and_safely_rolls_back_initial_evidence(): void
    {
        $migration = $this->versioningMigration();
        $migration->up();

        $this->assertTrue(Schema::hasColumns(self::TABLE, ['version', 'supersedes_id']));

        $indexes = $this->indexes();

        $this->assertArrayHasKey('scan_payment_evidence_exchange_version_unique', $indexes);
        $this->assertArrayNotHasKey('sanctum_scan_exchange_payment_evidence_exchange_id_unique', $indexes);

        $exchangeId = $this->makeExchange();
        $evidenceId = $this->insertEvidence($exchangeId, 1, null, 'INITIAL-ROLLBACK-REF');

        $migration->down();

        $this->assertFalse(Schema::hasColumn(self::TABLE, 'version'));
        $this->assertFalse(Schema::hasColumn(self::TABLE, 'supersedes_id'));

        $indexes = $this->indexes();

        $this->assertArrayHasKey('sanctum_scan_exchange_payment_evidence_exchange_id_unique', $indexes);
        $this->assertArrayNotHasKey('scan_payment_evidence_exchange_version_unique', $indexes);

        $evidence = DB::table(self::TABLE)->where('id', $evidenceId)->first();

        $this->assertNotNull($evidence);
        $this->assertSame('INITIAL-ROLLBACK-REF', $evidence->reference);
        $this->assertSame((string) $exchangeId, (string) $evidence->exchange_id);
    }

    public function test_rollback_refuses_multiple_versions_without_mutating_schema_or_evidence(): void
    {
        $migration = $this->versioningMigration();
        $migration->up();

        $exchangeId = $this->makeExchange();
        $firstEvidenceId = $this->insertEvidence($exchangeId, 1, null, 'VERSION-1-REF');
        $this->insertEvidence($exchangeId, 2, $firstEvidenceId, 'VERSION-2-REF');

        $before = DB::table(self::TABLE)
            ->where('exchange_id', $exchangeId)
            ->orderBy('version')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        try {
            $migration->down();
            $this->fail('Rollback must refuse to collapse a multi-version evidence history.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'non-initial or superseding evidence rows exist',
                $exception->getMessage()
            );
        }

        $this->assertTrue(
            Schema::hasColumns(self::TABLE, ['version', 'supersedes_id']),
            'The guard must run before dropping either versioning column.'
        );

        $indexes = $this->indexes();
        $this->assertArrayHasKey('scan_payment_evidence_exchange_version_unique', $indexes);
        $this->assertArrayNotHasKey('sanctum_scan_exchange_payment_evidence_exchange_id_unique', $indexes);

        $after = DB::table(self::TABLE)
            ->where('exchange_id', $exchangeId)
            ->orderBy('version')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $this->assertSame($before, $after, 'Rollback must preserve every submitted evidence row byte-for-byte at the SQL-value level.');
        $this->assertSame(2, count($after));

        $foreignKeys = DB::select("PRAGMA foreign_key_list('".self::TABLE."')");
        $this->assertTrue(
            collect($foreignKeys)->contains(fn ($foreignKey) => $foreignKey->from === 'supersedes_id'),
            'The self-reference foreign key must remain in place after a refused rollback.'
        );
    }

    public function test_rollback_refuses_a_non_initial_version_even_if_its_predecessor_is_missing(): void
    {
        $migration = $this->versioningMigration();
        $migration->up();

        $exchangeId = $this->makeExchange();
        $this->insertEvidence($exchangeId, 2, null, 'ORPHANED-VERSION-2');

        try {
            $migration->down();
            $this->fail('Rollback must refuse a non-initial evidence version even if its predecessor is missing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'non-initial or superseding evidence rows exist',
                $exception->getMessage()
            );
        }

        $this->assertTrue(Schema::hasColumns(self::TABLE, ['version', 'supersedes_id']));
        $this->assertSame(
            1,
            DB::table(self::TABLE)->where('exchange_id', $exchangeId)->count()
        );
        $this->assertSame(
            2,
            (int) DB::table(self::TABLE)->where('exchange_id', $exchangeId)->value('version')
        );
    }

    private function makeExchange(): int
    {
        return (int) DB::table('sanctum_scan_exchanges')->insertGetId([
            'merchant_id' => 1,
            'buyer_user_id' => 1,
            'resource_id' => 1,
            'resource_type' => 'agricultural_batch',
            'resource_name_snapshot' => 'Payment evidence rollback fixture',
            'quantity' => '100.0000',
            'unit' => 'kg',
            'amount' => '25000.00000000',
            'currency' => 'KES',
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEvidence(
        int $exchangeId,
        int $version,
        ?int $supersedesId,
        string $reference
    ): int {
        return (int) DB::table(self::TABLE)->insertGetId([
            'exchange_id' => $exchangeId,
            'version' => $version,
            'supersedes_id' => $supersedesId,
            'rail' => 'bank_transfer',
            'reference' => $reference,
            'amount' => '25000.00000000',
            'currency' => 'KES',
            'verification_status' => 'unverified',
            'submitted_by_guard' => 'api',
            'submitted_by_id' => 1,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexes(): array
    {
        return collect(DB::select("PRAGMA index_list('".self::TABLE."')"))
            ->keyBy('name')
            ->all();
    }

    private function runMigration(string $file): void
    {
        (require base_path('database/migrations/'.$file))->up();
    }

    private function versioningMigration(): object
    {
        return require base_path(
            'database/migrations/2026_11_16_130000_add_versioning_to_sanctum_scan_exchange_payment_evidence_table.php'
        );
    }
}
