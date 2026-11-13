<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_exchange_dispute_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispute_id');
            $table->string('evidence_type', 40);
            $table->string('original_name', 180);
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path', 255)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('caption', 500)->nullable();
            $table->string('submitted_by_guard', 32);
            $table->unsignedBigInteger('submitted_by_id');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->foreign('dispute_id')
                ->references('id')
                ->on('sanctum_scan_exchange_disputes')
                ->onDelete('cascade');

            $table->index(['dispute_id', 'created_at'], 'sanctum_scan_dispute_evidence_timeline_index');
            $table->index(['submitted_by_guard', 'submitted_by_id'], 'sanctum_scan_dispute_evidence_actor_index');
            $table->index('sha256', 'sanctum_scan_dispute_evidence_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_exchange_dispute_evidence');
    }
};
