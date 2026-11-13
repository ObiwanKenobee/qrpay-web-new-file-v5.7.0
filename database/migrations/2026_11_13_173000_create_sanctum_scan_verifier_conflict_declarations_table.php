<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_verifier_conflict_declarations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('mandate_id');
            $table->unsignedBigInteger('verifier_user_id');
            $table->boolean('has_conflict');
            $table->text('disclosure_statement');
            $table->timestamp('declared_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('membership_id', 'sanctum_scan_coi_member_fk')
                ->references('id')
                ->on('sanctum_scan_verifier_memberships')
                ->onDelete('restrict');

            $table->foreign('mandate_id', 'sanctum_scan_coi_mandate_fk')
                ->references('id')
                ->on('sanctum_scan_evidence_verification_mandates')
                ->onDelete('restrict');

            $table->foreign('verifier_user_id', 'sanctum_scan_coi_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->index(
                ['membership_id', 'mandate_id', 'id'],
                'sanctum_scan_coi_current_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_verifier_conflict_declarations');
    }
};
