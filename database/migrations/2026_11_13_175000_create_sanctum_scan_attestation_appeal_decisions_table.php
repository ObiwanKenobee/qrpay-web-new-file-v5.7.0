<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_attestation_appeal_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appeal_id');
            $table->string('outcome', 32);
            $table->text('decision_summary');
            $table->unsignedBigInteger('reviewed_by_admin_id');
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('appeal_id', 'sanctum_scan_appeal_decision_fk')
                ->references('id')
                ->on('sanctum_scan_attestation_appeals')
                ->onDelete('restrict');

            $table->foreign('reviewed_by_admin_id', 'sanctum_scan_appeal_admin_fk')
                ->references('id')
                ->on('admins')
                ->onDelete('restrict');

            $table->unique('appeal_id', 'sanctum_scan_appeal_one_decision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_attestation_appeal_decisions');
    }
};
