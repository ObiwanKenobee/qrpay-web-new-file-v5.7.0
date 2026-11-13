<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_exchanges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('buyer_user_id');
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_type', 32);
            $table->string('resource_name_snapshot', 140);
            $table->string('resource_reference_snapshot', 80)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 24);
            $table->decimal('amount', 28, 8);
            $table->char('currency', 3);
            $table->text('terms')->nullable();
            $table->string('status', 24)->default('offered');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign('buyer_user_id')->references('id')->on('users');
            $table->foreign('resource_id')->references('id')->on('sanctum_scan_resources');

            $table->index(['merchant_id', 'status', 'created_at'], 'sanctum_scan_exchange_merchant_index');
            $table->index(['buyer_user_id', 'status', 'created_at'], 'sanctum_scan_exchange_buyer_index');
            $table->index(['resource_id', 'created_at'], 'sanctum_scan_exchange_resource_index');
            $table->index(['status', 'expires_at'], 'sanctum_scan_exchange_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_exchanges');
    }
};
