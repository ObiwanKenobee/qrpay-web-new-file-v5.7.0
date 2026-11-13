<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_independent_attestations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mandate_id');
            $table->unsignedBigInteger('evidence_id');
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('verifier_user_id');
            $table->char('evidence_sha256', 64);
            $table->string('verification_scope', 40);
            $table->string('verification_method', 32);
            $table->string('outcome', 24);
            $table->text('basis_summary');
            $table->timestamp('attested_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('mandate_id', 'sanctum_scan_attest_mandate_fk')
                ->references('id')
                ->on('sanctum_scan_evidence_verification_mandates')
                ->onDelete('restrict');

            $table->foreign('evidence_id', 'sanctum_scan_attest_evidence_fk')
                ->references('id')
                ->on('sanctum_scan_exchange_dispute_evidence')
                ->onDelete('restrict');

            $table->foreign('institution_id', 'sanctum_scan_attest_institution_fk')
                ->references('id')
                ->on('sanctum_scan_verifier_institutions')
                ->onDelete('restrict');

            $table->foreign('membership_id', 'sanctum_scan_attest_member_fk')
                ->references('id')
                ->on('sanctum_scan_verifier_memberships')
                ->onDelete('restrict');

            $table->foreign('verifier_user_id', 'sanctum_scan_attest_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->unique(['mandate_id', 'membership_id'], 'sanctum_scan_attestation_membership_unique');
            $table->index(['evidence_id', 'attested_at'], 'sanctum_scan_attestation_evidence_index');
            $table->index(['institution_id', 'attested_at'], 'sanctum_scan_attestation_institution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_independent_attestations');
    }
};