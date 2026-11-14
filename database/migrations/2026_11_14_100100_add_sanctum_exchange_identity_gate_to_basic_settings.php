<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basic_settings', function (Blueprint $table) {
            $table->boolean('sanctum_exchange_identity_gate')->default(false)->after('merchant_pin_verification');
        });
    }

    public function down(): void
    {
        Schema::table('basic_settings', function (Blueprint $table) {
            $table->dropColumn('sanctum_exchange_identity_gate');
        });
    }
};
