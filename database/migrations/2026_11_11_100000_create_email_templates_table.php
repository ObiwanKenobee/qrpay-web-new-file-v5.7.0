<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email templates (admin + user/agent/merchant) and the per admin "email notification" switch.
 * Safe for a live database: only adds, skips anything that already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('type');
                $table->string('subject');
                $table->longText('body');
                $table->boolean('status')->default(true);
                $table->unsignedBigInteger('last_edit_by')->nullable();
                $table->timestamps();

                $table->foreign('last_edit_by')->references('id')->on('admins')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (!Schema::hasColumn('admins', 'email_notification')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->boolean('email_notification')->default(true)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'email_notification')) {
            Schema::table('admins', fn(Blueprint $table) => $table->dropColumn('email_notification'));
        }
        Schema::dropIfExists('email_templates');
    }
};
