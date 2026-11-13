<?php

namespace Database\Seeders\Update;

use App\Http\Helpers\AdminDashboardStats as DashboardStats;
use Illuminate\Database\Seeder;

class DashboardStatsSeeder extends Seeder
{
    /**
     * Fill the admin dashboard daily summary from existing transactions (safe to run again).
     *
     * @return void
     */
    public function run()
    {
        DashboardStats::rebuildAll();
    }
}
