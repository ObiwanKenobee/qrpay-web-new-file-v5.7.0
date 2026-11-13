<?php

namespace Database\Seeders\Admin;

use App\Models\VirtualCardApi;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class VirtualApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $virtual_card_apis = array(
            array('admin_id' => '1','image' => 'seeder/virtual-card.png','card_details' => 'This card is property of QRPay, Wonderland. Misuse is criminal offence. If found, please return to QRPay or to the nearest bank.','config' => '{"flutterwave_secret_key":"FLWSECK_TEST-REPLACE_WITH_YOUR_FLUTTERWAVE_KEY-X","flutterwave_url":"https:\/\/api.flutterwave.com\/v3","sudo_api_key":"REPLACE_WITH_YOUR_SUDO_API_KEY","sudo_vault_id":"tntbuyt0v9u","sudo_url":"https:\/\/api.sandbox.sudo.cards","sudo_mode":"sandbox","stripe_public_key":"pk_test_REPLACE_WITH_YOUR_STRIPE_PUBLIC_KEY","stripe_secret_key":"sk_test_REPLACE_WITH_YOUR_STRIPE_SECRET_KEY","stripe_url":"https:\/\/api.stripe.com\/v1","stripe_mode":"sandbox","strowallet_public_key":"48233434cb3096e6e218aa48df13d3584098b746","strowallet_url":"https:\/\/ziiropay.com\/api\/bitvcard\/","strowallet_mode":"sandbox","cardyfie_public_key":"pub_y8kN5RqwmTArMlZfczxgjdciAko7ww4BqcQvDkmKlhTcS3M9UlDayJ5MVSGwNANEs6BBYYTt6UavutinA9V4xfJT4nvjh96ryiZAJhTyNwui4dcmrdw2SQC8","cardyfie_secret_key":"sec_1CUtD4XEg4ZQY9ck5ZOgxtJ58a5AsefTG42q9E4DceajMqqE9p3xGt62QpQDAzQrwF6aYWPl9AowVilPtFVZwpFzKGNFixpYfHnFpqU8cjqS7VsTEvx45Y2X","cardyfie_sandbox_url":"https:\/\/core.cardyfie.com\/api\/sandbox\/v1","cardyfie_production_url":"https:\/\/core.cardyfie.com\/api\/product\/v1","cardyfie_webhook_secret":"w_sec_dYhoj1raOnoJURN9hvOZ2lppoGXbp9UhUSfS9MDqBiPESBag3KhVDhtJZii8ybA7CASMoXH6bJGFpfuMUJ2ShAM1G2DM266Z22Xu4Tfpeku8U4ZEOeKOFA8Y","cardyfie_universal_card_issues_fee":"3.00","cardyfie_platinum_card_issues_fee":"5.00","cardyfie_card_deposit_fixed_fee":"1.00","cardyfie_card_deposit_percent_fee":"1.00","cardyfie_card_withdraw_fixed_fee":"1.00","cardyfie_card_withdraw_percent_fee":"1.00","cardyfie_card_maintenance_fixed_fee":"0.00","cardyfie_min_limit":"5.00","cardyfie_max_limit":"500.00","cardyfie_daily_limit":"5000.00","cardyfie_monthly_limit":"10000.00","cardyfie_mode":"sandbox","name":"strowallet"}','created_at' => now(),'updated_at' => now())
          );
        VirtualCardApi::insert($virtual_card_apis);
    }
}