<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctum_scan_exchanges', function (Blueprint $table) {
            $table->string('settlement_status', 40)
                ->default('awaiting_payment')
                ->after('status');
            $table->timestamp('settlement_status_updated_at')->nullable()->after('settlement_status');
            $table->index(['settlement_status', 'updated_at'], 'sanctum_exchange_settlement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sanctum_scan_exchanges', function (Blueprint $table) {
            $table->dropIndex('sanctum_exchange_settlement_idx');
            $table->dropColumn(['settlement_status', 'settlement_status_updated_at']);
        });
    }
};
