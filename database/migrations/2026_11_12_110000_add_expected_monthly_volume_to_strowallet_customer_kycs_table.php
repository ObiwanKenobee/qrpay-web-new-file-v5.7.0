<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strowallet card KYC asks how much the card holder expects to spend per month.
 * Safe for a live database: only adds a nullable column, skips it if it already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('strowallet_customer_kycs', 'expected_monthly_volume')) {
            Schema::table('strowallet_customer_kycs', fn(Blueprint $table) => $table->decimal('expected_monthly_volume', 20, 2)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('strowallet_customer_kycs', 'expected_monthly_volume')) {
            Schema::table('strowallet_customer_kycs', fn(Blueprint $table) => $table->dropColumn('expected_monthly_volume'));
        }
    }
};
