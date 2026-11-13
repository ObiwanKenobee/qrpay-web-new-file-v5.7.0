<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_references', function (Blueprint $table) {
            $table->id();
            $table->char('reference_hash', 64)->unique();
            $table->string('schema_version', 16)->default('1.0');
            $table->string('resource_type', 32);
            $table->unsignedBigInteger('resource_id');
            $table->string('display_name', 120);
            $table->text('display_description')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id'], 'sanctum_scan_resource_index');
            $table->index(['status', 'expires_at'], 'sanctum_scan_status_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_references');
    }
};
