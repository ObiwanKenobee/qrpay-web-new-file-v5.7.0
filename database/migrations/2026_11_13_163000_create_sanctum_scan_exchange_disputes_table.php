<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_exchange_disputes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exchange_id')->unique();
            $table->string('category', 32);
            $table->string('status', 24)->default('open');
            $table->string('opened_by_guard', 32);
            $table->unsignedBigInteger('opened_by_id');
            $table->timestamp('opened_at');
            $table->string('resolution_proposed_by_guard', 32)->nullable();
            $table->unsignedBigInteger('resolution_proposed_by_id')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->timestamp('resolution_proposed_at')->nullable();
            $table->string('resolved_by_guard', 32)->nullable();
            $table->unsignedBigInteger('resolved_by_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->foreign('exchange_id')
                ->references('id')
                ->on('sanctum_scan_exchanges');

            $table->index(['status', 'created_at'], 'sanctum_scan_dispute_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_exchange_disputes');
    }
};
