<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_evidence_verification_mandates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evidence_id');
            $table->unsignedBigInteger('institution_id');
            $table->string('verification_scope', 40);
            $table->text('mandate_reason');
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('mandated_by_admin_id');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('revoked_by_admin_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('evidence_id')
                ->references('id')
                ->on('sanctum_scan_exchange_dispute_evidence')
                ->onDelete('restrict');

            $table->foreign('institution_id')
                ->references('id')
                ->on('sanctum_scan_verifier_institutions')
                ->onDelete('restrict');

            $table->foreign('mandated_by_admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('restrict');

            $table->foreign('revoked_by_admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('restrict');

            $table->unique(['evidence_id', 'institution_id'], 'sanctum_scan_evidence_mandate_unique');
            $table->index(['institution_id', 'status', 'expires_at'], 'sanctum_scan_mandate_authority_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_evidence_verification_mandates');
    }
};