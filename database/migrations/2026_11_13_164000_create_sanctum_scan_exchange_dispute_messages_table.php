<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_exchange_dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispute_id');
            $table->string('message_type', 32);
            $table->text('body');
            $table->string('recorded_by_guard', 32);
            $table->unsignedBigInteger('recorded_by_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('dispute_id')
                ->references('id')
                ->on('sanctum_scan_exchange_disputes')
                ->onDelete('cascade');

            $table->index(['dispute_id', 'id'], 'sanctum_scan_dispute_message_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_exchange_dispute_messages');
    }
};
