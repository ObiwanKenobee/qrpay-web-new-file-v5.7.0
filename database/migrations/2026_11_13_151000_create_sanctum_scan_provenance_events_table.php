<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_provenance_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->string('event_type', 40);
            $table->text('statement');
            $table->string('location_label', 160)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('verification_status', 32)->default('merchant_declared');
            $table->string('recorded_by_guard', 32);
            $table->unsignedBigInteger('recorded_by_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('resource_id')
                ->references('id')
                ->on('sanctum_scan_resources')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['resource_id', 'created_at'], 'sanctum_scan_provenance_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_provenance_events');
    }
};
