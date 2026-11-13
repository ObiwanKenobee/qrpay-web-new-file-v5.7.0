<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_verifier_institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('organization_type', 40);
            $table->char('country_code', 2);
            $table->string('registration_reference', 120)->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by_admin_id');
            $table->timestamps();

            $table->foreign('created_by_admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('restrict');

            $table->index(['status', 'country_code'], 'sanctum_scan_verifier_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_verifier_institutions');
    }
};