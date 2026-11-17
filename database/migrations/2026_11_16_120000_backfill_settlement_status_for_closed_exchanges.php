<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sanctum_scan_exchanges')
            ->whereIn('status', ['declined', 'cancelled', 'expired'])
            ->where('settlement_status', 'awaiting_payment')
            ->update([
                'settlement_status' => 'cancelled',
                'settlement_status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sanctum_scan_exchanges')
            ->whereIn('status', ['declined', 'cancelled', 'expired'])
            ->where('settlement_status', 'cancelled')
            ->update([
                'settlement_status' => 'awaiting_payment',
                'settlement_status_updated_at' => null,
            ]);
    }
};
