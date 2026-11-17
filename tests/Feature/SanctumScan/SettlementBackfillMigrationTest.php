<?php

namespace Tests\Feature\SanctumScan;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettlementBackfillMigrationTest extends TestCase
{
    private object $backfillMigration;

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

        (require base_path('database/migrations/2026_11_13_150000_create_sanctum_scan_resources_table.php'))->up();
        (require base_path('database/migrations/2026_11_13_160000_create_sanctum_scan_exchanges_table.php'))->up();
        (require base_path('database/migrations/2026_11_14_110000_add_settlement_status_to_sanctum_scan_exchanges_table.php'))->up();

        $this->backfillMigration = require base_path('database/migrations/2026_11_16_120000_backfill_settlement_status_for_closed_exchanges.php');

        DB::table('users')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('merchants')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sanctum_scan_resources')->insert([
            'id' => 1,
            'merchant_id' => 1,
            'resource_type' => 'agricultural_batch',
            'display_name' => 'Backfill fixture batch',
            'category' => 'Potatoes',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeExchange(string $exchangeStatus, string $settlementStatus): int
    {
        return (int) DB::table('sanctum_scan_exchanges')->insertGetId([
            'merchant_id' => 1,
            'buyer_user_id' => 1,
            'resource_id' => 1,
            'resource_type' => 'agricultural_batch',
            'resource_name_snapshot' => 'Backfill fixture batch',
            'quantity' => '100.0000',
            'unit' => 'kg',
            'amount' => '25000.00000000',
            'currency' => 'KES',
            'status' => $exchangeStatus,
            'settlement_status' => $settlementStatus,
            'settlement_status_updated_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function settlementStatus(int $exchangeId): string
    {
        return (string) DB::table('sanctum_scan_exchanges')
            ->where('id', $exchangeId)
            ->value('settlement_status');
    }

    public function test_backfill_cancels_only_unresolved_closed_exchanges_and_can_be_reversed(): void
    {
        $declined = $this->makeExchange('declined', 'awaiting_payment');
        $cancelled = $this->makeExchange('cancelled', 'awaiting_payment');
        $expired = $this->makeExchange('expired', 'awaiting_payment');
        $accepted = $this->makeExchange('accepted', 'awaiting_payment');
        $verifiedButClosed = $this->makeExchange('declined', 'payment_verified');
        $alreadyRejected = $this->makeExchange('cancelled', 'payment_rejected');

        $this->backfillMigration->up();

        $this->assertSame('cancelled', $this->settlementStatus($declined));
        $this->assertSame('cancelled', $this->settlementStatus($cancelled));
        $this->assertSame('cancelled', $this->settlementStatus($expired));
        $this->assertSame('awaiting_payment', $this->settlementStatus($accepted));
        $this->assertSame('payment_verified', $this->settlementStatus($verifiedButClosed));
        $this->assertSame('payment_rejected', $this->settlementStatus($alreadyRejected));

        foreach ([$declined, $cancelled, $expired] as $exchangeId) {
            $this->assertNotNull(
                DB::table('sanctum_scan_exchanges')->where('id', $exchangeId)->value('settlement_status_updated_at')
            );
        }

        $this->assertNull(
            DB::table('sanctum_scan_exchanges')->where('id', $verifiedButClosed)->value('settlement_status_updated_at')
        );

        // Running up() again must be a no-op.
        $this->backfillMigration->up();

        $this->assertSame('cancelled', $this->settlementStatus($declined));
        $this->assertSame('payment_verified', $this->settlementStatus($verifiedButClosed));

        $this->backfillMigration->down();

        foreach ([$declined, $cancelled, $expired] as $exchangeId) {
            $this->assertSame('awaiting_payment', $this->settlementStatus($exchangeId));
            $this->assertNull(
                DB::table('sanctum_scan_exchanges')->where('id', $exchangeId)->value('settlement_status_updated_at')
            );
        }

        $this->assertSame('awaiting_payment', $this->settlementStatus($accepted));
        $this->assertSame('payment_verified', $this->settlementStatus($verifiedButClosed));
        $this->assertSame('payment_rejected', $this->settlementStatus($alreadyRejected));
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sanctum_scan_exchanges');
        Schema::dropIfExists('sanctum_scan_resources');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();
        DB::purge('sqlite');

        parent::tearDown();
    }
}
