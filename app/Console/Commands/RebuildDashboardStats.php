<?php

namespace App\Console\Commands;

use App\Http\Helpers\AdminDashboardStats as DashboardStats;
use Illuminate\Console\Command;

class RebuildDashboardStats extends Command
{
    /**
     * Rebuild the admin dashboard daily summary from all transactions.
     * Needed once after install/update, after deleting many transactions, or to re-convert with new currency rates.
     *
     * @var string
     */
    protected $signature = 'dashboard:rebuild-stats';

    protected $description = 'Rebuild the admin dashboard daily transaction summary';

    public function handle()
    {
        $started = microtime(true);
        DashboardStats::rebuildAll();
        $this->info('Dashboard summary rebuilt in '.round(microtime(true) - $started, 1).'s');
        return self::SUCCESS;
    }
}
