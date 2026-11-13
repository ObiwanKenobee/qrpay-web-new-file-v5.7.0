<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_scan_verifier_credential_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('membership_id')->nullable();
            $table->string('credential_scope', 40);
            $table->string('credential_type', 48);
            $table->string('issuer_name', 180);
            $table->string('credential_reference', 160);
            $table->string('jurisdiction', 120)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('review_method', 32);
            $table->string('review_status', 24);
            $table->text('review_summary');
            $table->unsignedBigInteger('reviewed_by_admin_id');
            $table->timestamp('reviewed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('institution_id', 'sanctum_scan_cred_institution_fk')
                ->references('id')
                ->on('sanctum_scan_verifier_institutions')
                ->onDelete('restrict');

            $table->foreign('membership_id', 'sanctum_scan_cred_member_fk')
                ->references('id')
                ->on('sanctum_scan_verifier_memberships')
                ->onDelete('restrict');

            $table->foreign('reviewed_by_admin_id', 'sanctum_scan_cred_reviewer_fk')
                ->references('id')
                ->on('admins')
                ->onDelete('restrict');

            $table->index(
                ['institution_id', 'membership_id', 'credential_scope', 'id'],
                'sanctum_scan_cred_current_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_scan_verifier_credential_reviews');
    }
};
