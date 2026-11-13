<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\GatewayAPi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GatewayApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $gateway_a_pis = array(
            array('id' => '1','admin_id' => '1','secret_key' => 'sk_test_REPLACE_WITH_YOUR_STRIPE_SECRET_KEY','public_key' => 'pk_test_REPLACE_WITH_YOUR_STRIPE_PUBLIC_KEY','account_id' => NULL,'created_at' => '2023-10-21 10:03:02','updated_at' => '2023-10-21 10:03:02')
        );

        GatewayAPi::insert($gateway_a_pis);

    }
}
