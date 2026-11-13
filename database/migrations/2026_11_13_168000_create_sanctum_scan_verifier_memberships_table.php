<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_verifier_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 24)->default('invited');
            $table->unsignedBigInteger('invited_by_admin_id');
            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')
                ->references('id')
                ->on('sanctum_scan_verifier_institutions')
                ->onDelete('restrict');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('invited_by_admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('restrict');

            $table->unique(['institution_id', 'user_id'], 'sanctum_scan_verifier_membership_unique');
            $table->index(['user_id', 'status'], 'sanctum_scan_verifier_membership_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_verifier_memberships');
    }
};