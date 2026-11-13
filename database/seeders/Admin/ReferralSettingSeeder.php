<?php

namespace Database\Seeders\Admin;

use Illuminate\Database\Seeder;
use App\Models\Admin\ReferralSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ReferralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if(!ReferralSetting::first()) {
            ReferralSetting::create(array('id' => '1','bonus' => '0.50000000','wallet_type' => 'c_balance','mail' => '0','sms' => '0','status' => '0','created_at' => now(),'updated_at' => now()));
        }
    }
}
