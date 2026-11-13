<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctum_scan_references', function (Blueprint $table) {
            $table->string('created_by_guard', 32)->nullable()->after('revoked_at');
            $table->unsignedBigInteger('created_by_id')->nullable()->after('created_by_guard');
            $table->string('revoked_by_guard', 32)->nullable()->after('created_by_id');
            $table->unsignedBigInteger('revoked_by_id')->nullable()->after('revoked_by_guard');

            $table->index(
                ['created_by_guard', 'created_by_id'],
                'sanctum_scan_created_by_index'
            );
            $table->index(
                ['revoked_by_guard', 'revoked_by_id'],
                'sanctum_scan_revoked_by_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sanctum_scan_references', function (Blueprint $table) {
            $table->dropIndex('sanctum_scan_created_by_index');
            $table->dropIndex('sanctum_scan_revoked_by_index');
            $table->dropColumn([
                'created_by_guard',
                'created_by_id',
                'revoked_by_guard',
                'revoked_by_id',
            ]);
        });
    }
};
