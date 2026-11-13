<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_exchange_payment_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exchange_id')->unique();
            $table->string('rail', 32);
            $table->string('reference', 120);
            $table->decimal('amount', 28, 8);
            $table->char('currency', 3);
            $table->string('verification_status', 24)->default('unverified');
            $table->string('submitted_by_guard', 32);
            $table->unsignedBigInteger('submitted_by_id');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->foreign('exchange_id')
                ->references('id')
                ->on('sanctum_scan_exchanges');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_exchange_payment_evidence');
    }
};
