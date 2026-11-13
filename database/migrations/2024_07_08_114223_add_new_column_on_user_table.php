<?php

use App\Constants\GlobalConst;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users',function(Blueprint $table){
            $table->dropColumn('refferal_user_id');
            $table->string('referral_id')->nullable()->after('password');
            $table->unsignedBigInteger('current_referral_level_id')->nullable()->after('referral_id');
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
            $table->enum("registered_by",[
                GlobalConst::EMAIL,
                GlobalConst::PHONE,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_id');
            $table->dropColumn('email');
            $table->dropColumn('registered_by');
        });
    }
};
