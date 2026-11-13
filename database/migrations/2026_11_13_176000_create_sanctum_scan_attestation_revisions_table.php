<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_attestation_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attestation_id');
            $table->unsignedBigInteger('evidence_id');
            $table->unsignedBigInteger('previous_revision_id')->nullable();
            $table->unsignedSmallInteger('version_number');
            $table->char('evidence_sha256', 64);
            $table->string('revision_action', 16);
            $table->string('verification_scope', 40);
            $table->string('verification_method', 32);
            $table->string('outcome', 24);
            $table->text('basis_summary');
            $table->string('reason_code', 32);
            $table->text('reason_summary');
            $table->string('recorded_by_guard', 24);
            $table->unsignedBigInteger('recorded_by_id');
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('attestation_id', 'sanctum_scan_revision_attest_fk')
                ->references('id')
                ->on('sanctum_scan_independent_attestations')
                ->onDelete('restrict');

            $table->foreign('evidence_id', 'sanctum_scan_revision_evidence_fk')
                ->references('id')
                ->on('sanctum_scan_exchange_dispute_evidence')
                ->onDelete('restrict');

            $table->foreign('previous_revision_id', 'sanctum_scan_revision_previous_fk')
                ->references('id')
                ->on('sanctum_scan_attestation_revisions')
                ->onDelete('restrict');

            $table->unique(
                ['attestation_id', 'version_number'],
                'sanctum_scan_revision_version_unique'
            );
            $table->unique('previous_revision_id', 'sanctum_scan_revision_successor_unique');
            $table->index(
                ['attestation_id', 'version_number', 'revision_action'],
                'sanctum_scan_revision_history_index'
            );
            $table->index(['evidence_id', 'recorded_at'], 'sanctum_scan_revision_evidence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_attestation_revisions');
    }
};
