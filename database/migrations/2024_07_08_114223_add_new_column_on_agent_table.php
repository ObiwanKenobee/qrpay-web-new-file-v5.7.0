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
        Schema::table('agents',function(Blueprint $table){
            DB::statement('ALTER TABLE agents MODIFY email VARCHAR(255) NULL');
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
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->dropColumn('registered_by');
        });
    }
};
