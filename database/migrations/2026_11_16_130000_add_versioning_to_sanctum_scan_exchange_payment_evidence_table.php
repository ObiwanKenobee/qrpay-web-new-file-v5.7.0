<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctum_scan_exchange_payment_evidence', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('id');
            $table->unsignedBigInteger('supersedes_id')->nullable()->after('version');

            $table->dropUnique(['exchange_id']);
            $table->unique(['exchange_id', 'version'], 'scan_payment_evidence_exchange_version_unique');
            $table->foreign('supersedes_id')
                ->references('id')
                ->on('sanctum_scan_exchange_payment_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('sanctum_scan_exchange_payment_evidence', function (Blueprint $table) {
            $table->dropForeign(['supersedes_id']);
            $table->dropUnique('scan_payment_evidence_exchange_version_unique');
            $table->dropColumn(['supersedes_id', 'version']);
            $table->unique(['exchange_id']);
        });
    }
};
