<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strowallet card KYC asks for the card holder's annual salary and place of birth.
 * Safe for a live database: only adds nullable columns, skips any that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strowallet_customer_kycs', function (Blueprint $table) {
            if (!Schema::hasColumn('strowallet_customer_kycs', 'annual_salary')) { $table->decimal('annual_salary', 20, 2)->nullable(); }
            if (!Schema::hasColumn('strowallet_customer_kycs', 'place_of_birth')) { $table->string('place_of_birth', 100)->nullable(); }
        });
    }

    public function down(): void
    {
        Schema::table('strowallet_customer_kycs', function (Blueprint $table) {
            foreach (['annual_salary', 'place_of_birth'] as $column) {
                if (Schema::hasColumn('strowallet_customer_kycs', $column)) { $table->dropColumn($column); }
            }
        });
    }
};
