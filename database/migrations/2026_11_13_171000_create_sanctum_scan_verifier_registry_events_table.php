<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_verifier_registry_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 24);
            $table->unsignedBigInteger('subject_id');
            $table->string('event_type', 48);
            $table->string('actor_guard', 24);
            $table->unsignedBigInteger('actor_id');
            $table->json('event_data')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['subject_type', 'subject_id', 'recorded_at'], 'sanctum_scan_verifier_event_subject_index');
            $table->index(['actor_guard', 'actor_id'], 'sanctum_scan_verifier_event_actor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_verifier_registry_events');
    }
};