<?php

namespace Tests\Feature\SanctumScan;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $this->runMigration('2026_11_13_160000_create_sanctum_scan_exchanges_table.php');
        $this->runMigration('2026_11_13_162000_create_sanctum_scan_exchange_payment_evidence_table.php');
    }

    public function test_versioning_migration_adds_the_composite_unique_key_and_rolls_back(): void
    {
        $this->runMigration('2026_11_16_130000_add_versioning_to_sanctum_scan_exchange_payment_evidence_table.php');

        $this->assertTrue(Schema::hasColumns(self::TABLE, ['version', 'supersedes_id']));

        $indexes = $this->indexes();

        $this->assertArrayHasKey('scan_payment_evidence_exchange_version_unique', $indexes);
        $this->assertArrayNotHasKey('sanctum_scan_exchange_payment_evidence_exchange_id_unique', $indexes);

        (require base_path('database/migrations/2026_11_16_130000_add_versioning_to_sanctum_scan_exchange_payment_evidence_table.php'))->down();

        $this->assertFalse(Schema::hasColumn(self::TABLE, 'version'));
        $this->assertFalse(Schema::hasColumn(self::TABLE, 'supersedes_id'));

        $indexes = $this->indexes();

        $this->assertArrayHasKey('sanctum_scan_exchange_payment_evidence_exchange_id_unique', $indexes);
        $this->assertArrayNotHasKey('scan_payment_evidence_exchange_version_unique', $indexes);
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
}
