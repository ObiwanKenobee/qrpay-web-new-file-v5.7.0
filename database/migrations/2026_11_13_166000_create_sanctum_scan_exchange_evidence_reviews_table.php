<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_exchange_evidence_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evidence_id')->unique();
            $table->string('review_status', 24);
            $table->string('review_method', 32);
            $table->char('evidence_sha256', 64);
            $table->text('review_summary');
            $table->unsignedBigInteger('reviewed_by_admin_id');
            $table->timestamp('reviewed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('evidence_id')
                ->references('id')
                ->on('sanctum_scan_exchange_dispute_evidence')
                ->onDelete('cascade');

            $table->foreign('reviewed_by_admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('restrict');

            $table->index(['review_status', 'reviewed_at'], 'sanctum_scan_evidence_review_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_exchange_evidence_reviews');
    }
};
