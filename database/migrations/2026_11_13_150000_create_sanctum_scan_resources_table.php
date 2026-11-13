<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('resource_type', 32);
            $table->string('display_name', 140);
            $table->text('description')->nullable();
            $table->string('category', 100);
            $table->string('reference_code', 80)->nullable();
            $table->string('variety', 100)->nullable();
            $table->string('origin_label', 160)->nullable();
            $table->date('occurred_on')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 24)->nullable();
            $table->string('status', 16)->default('published');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('merchant_id')
                ->references('id')
                ->on('merchants')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(['merchant_id', 'resource_type', 'status'], 'sanctum_scan_resource_owner_index');
            $table->index(['resource_type', 'status'], 'sanctum_scan_resource_public_index');
            $table->unique(['merchant_id', 'resource_type', 'reference_code'], 'sanctum_scan_resource_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_resources');
    }
};
