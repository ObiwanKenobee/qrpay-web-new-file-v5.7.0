<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_attestation_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attestation_id');
            $table->unsignedBigInteger('evidence_id');
            $table->unsignedBigInteger('exchange_id');
            $table->string('appellant_guard', 24);
            $table->unsignedBigInteger('appellant_id');
            $table->char('evidence_sha256', 64);
            $table->string('reason_code', 32);
            $table->text('statement');
            $table->timestamp('submitted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('attestation_id', 'sanctum_scan_appeal_attest_fk')
                ->references('id')
                ->on('sanctum_scan_independent_attestations')
                ->onDelete('restrict');

            $table->foreign('evidence_id', 'sanctum_scan_appeal_evidence_fk')
                ->references('id')
                ->on('sanctum_scan_exchange_dispute_evidence')
                ->onDelete('restrict');

            $table->foreign('exchange_id', 'sanctum_scan_appeal_exchange_fk')
                ->references('id')
                ->on('sanctum_scan_exchanges')
                ->onDelete('restrict');

            $table->unique(
                ['attestation_id', 'appellant_guard', 'appellant_id'],
                'sanctum_scan_appeal_participant_unique'
            );

            $table->index(['exchange_id', 'submitted_at'], 'sanctum_scan_appeal_exchange_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_attestation_appeals');
    }
};
