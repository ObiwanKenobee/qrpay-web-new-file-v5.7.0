<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_user_identity_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('method', 80);
            $table->string('reference', 255)->nullable();
            $table->string('review_status', 30); // verified | rejected | unable_to_verify
            $table->text('summary');
            $table->unsignedBigInteger('reviewed_by_admin_id');
            $table->timestamp('reviewed_at');
            $table->timestamp('created_at');

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'review_status', 'reviewed_at'], 'sanctum_identity_check_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_user_identity_checks');
    }
};
