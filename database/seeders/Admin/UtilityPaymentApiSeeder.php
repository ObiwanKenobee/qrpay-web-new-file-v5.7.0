<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\ReloadlyApi;
use Illuminate\Database\Seeder;

class UtilityPaymentApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $utility_payment_apis = array(
            array('provider' => 'RELOADLY','type' => 'UTILITY-PAYMENT','credentials' => '{"client_id":"iSBGOhBpPVZExPpH5hERk1rLuRcoBB3Q","secret_key":"9Lq7FHtkS4-KnV1swQeFtRpjE7tGwb-Cw66lf0KB9izuSAKIfbLgU5o7KyusRAT","production_base_url":"https://utilities.reloadly.com","sandbox_base_url":"https://utilities-sandbox.reloadly.com"}','status' => '1','env' => 'sandbox','created_at' =>now(),'updated_at' =>now())
          );

        ReloadlyApi::insert($utility_payment_apis);
    }
}
