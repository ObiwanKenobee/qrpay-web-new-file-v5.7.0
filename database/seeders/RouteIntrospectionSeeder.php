<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gate fixture for ADR 0016 G5.
 *
 * `php artisan route:list` instantiates every controller to collect its
 * middleware. The legacy virtual-card controllers read `VirtualCardApi::first()`
 * in their constructors, so the command cannot succeed against a migrated but
 * otherwise empty database. This seeder writes only the singleton rows those
 * constructors dereference; it is not application demo data and must not be
 * added to DatabaseSeeder.
 */
class RouteIntrospectionSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('virtual_card_apis')->exists()) {
            return;
        }

        $adminId = DB::table('admins')->insertGetId([
            'firstname' => 'Gate',
            'lastname' => 'Fixture',
            'username' => 'gate-fixture-admin',
            'email' => 'gate-fixture@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('virtual_card_apis')->insert([
            'admin_id' => $adminId,
            'card_limit' => 0,
            'card_details' => '',
            'config' => json_encode([
                'flutterwave_url' => '',
                'strowallet_url' => '',
                'strowallet_mode' => 'sandbox',
                'cardyfie_mode' => 'sandbox',
                'cardyfie_sandbox_url' => '',
                'cardyfie_production_url' => '',
                'cardyfie_public_key' => '',
                'cardyfie_secret_key' => '',
                'stripe_mode' => 'sandbox',
                'sudo_mode' => 'sandbox',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
