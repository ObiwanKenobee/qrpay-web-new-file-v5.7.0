<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_attestation_reassessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prior_attestation_id');
            $table->unsignedBigInteger('prior_revision_id')->nullable();
            $table->unsignedBigInteger('reassessment_attestation_id');
            $table->unsignedBigInteger('evidence_id');
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('reassessed_by_membership_id');
            $table->unsignedBigInteger('reassessed_by_user_id');
            $table->unsignedSmallInteger('prior_version_number');
            $table->string('prior_status', 16);
            $table->char('evidence_sha256', 64);
            $table->text('reason_summary');
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('prior_attestation_id', 'sanctum_scan_reassess_prior_fk')
                ->references('id')
                ->on('sanctum_scan_independent_attestations')
                ->onDelete('restrict');

            $table->foreign('prior_revision_id', 'sanctum_scan_reassess_revision_fk')
                ->references('id')
                ->on('sanctum_scan_attestation_revisions')
                ->onDelete('restrict');

            $table->foreign('reassessment_attestation_id', 'sanctum_scan_reassess_new_fk')
                ->references('id')
                ->on('sanctum_scan_independent_attestations')
                ->onDelete('restrict');

            $table->foreign('evidence_id', 'sanctum_scan_reassess_evidence_fk')
                ->references('id')
                ->on('sanctum_scan_exchange_dispute_evidence')
                ->onDelete('restrict');

            $table->foreign('institution_id', 'sanctum_scan_reassess_institution_fk')
                ->references('id')
                ->on('sanctum_scan_verifier_institutions')
                ->onDelete('restrict');

            $table->foreign('reassessed_by_membership_id', 'sanctum_scan_reassess_member_fk')
                ->references('id')
                ->on('sanctum_scan_verifier_memberships')
                ->onDelete('restrict');

            $table->foreign('reassessed_by_user_id', 'sanctum_scan_reassess_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->unique('reassessment_attestation_id', 'sanctum_scan_reassess_new_unique');
            $table->index(
                ['prior_attestation_id', 'prior_version_number', 'recorded_at'],
                'sanctum_scan_reassess_history_index'
            );
            $table->index(['evidence_id', 'institution_id'], 'sanctum_scan_reassess_evidence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_attestation_reassessments');
    }
};
