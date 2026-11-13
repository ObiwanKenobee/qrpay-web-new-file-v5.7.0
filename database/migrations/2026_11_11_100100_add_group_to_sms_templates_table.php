<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each account type (user/agent/merchant) gets its own copy of an SMS template.
 * Safe for a live database: existing rows become the "user" copy, nothing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sms_templates', 'group')) {
            Schema::table('sms_templates', function (Blueprint $table) {
                $table->string('group', 20)->default('user')->after('act');
            });
        }
        if (!Schema::hasColumn('sms_templates', 'last_edit_by')) {
            Schema::table('sms_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('last_edit_by')->nullable()->after('sms_status');
                $table->foreign('last_edit_by')->references('id')->on('admins')->nullOnDelete()->cascadeOnUpdate();
            });
        }
        if (Schema::hasIndex('sms_templates', 'sms_templates_act_unique')) {
            Schema::table('sms_templates', fn(Blueprint $table) => $table->dropUnique('sms_templates_act_unique'));
        }
        if (!Schema::hasIndex('sms_templates', 'sms_templates_act_group_unique')) {
            Schema::table('sms_templates', fn(Blueprint $table) => $table->unique(['act', 'group'], 'sms_templates_act_group_unique'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('sms_templates', 'sms_templates_act_group_unique')) {
            Schema::table('sms_templates', fn(Blueprint $table) => $table->dropUnique('sms_templates_act_group_unique'));
        }
        if (Schema::hasColumn('sms_templates', 'last_edit_by')) {
            Schema::table('sms_templates', function (Blueprint $table) {
                $table->dropForeign(['last_edit_by']);
                $table->dropColumn('last_edit_by');
            });
        }
        if (Schema::hasColumn('sms_templates', 'group')) {
            // keep one row per act so the old unique key can come back
            $keep_ids = DB::table('sms_templates')->selectRaw('MIN(id) as id')->groupBy('act')->pluck('id');
            DB::table('sms_templates')->whereNotIn('id', $keep_ids)->delete();
            Schema::table('sms_templates', fn(Blueprint $table) => $table->dropColumn('group'));
        }
        if (!Schema::hasIndex('sms_templates', 'sms_templates_act_unique')) {
            Schema::table('sms_templates', fn(Blueprint $table) => $table->unique('act'));
        }
    }
};
