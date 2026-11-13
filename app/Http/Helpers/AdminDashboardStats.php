<?php

namespace App\Http\Helpers;

use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Daily transaction totals for the admin dashboard (dashboard_daily_stats).
 * The dashboard reads this small table instead of adding up every transaction.
 *
 * No cron / job: sync() runs inside the dashboard request and re-summarizes only the days that have
 * new or changed (created_at / updated_at) transactions since the last sync.
 *
 * Single currency project: every wallet is in the default currency, so amount = request_amount (no wallet joins).
 * Admin profit follows the profit log rule: add money charge / gateway currency rate, others / default rate.
 */
class AdminDashboardStats
{
    const TABLE = 'dashboard_daily_stats';

    // a transaction saved (or committed late) while the last sync ran is still picked up
    const SYNC_OVERLAP_SECONDS = 600;

    // sync and full rebuild share this lock, so they never write the summary at the same time
    const LOCK = 'dashboard-stats-sync';
    const LOCK_SECONDS = 3600;

    // up to this many transactions a first fill is fast enough to run inside the page request
    const INLINE_REBUILD_LIMIT = 200000;

    /**
     * Bring the summary up to date (fast: only changed days)
     */
    public static function sync(): void
    {
        $meta = DB::table('dashboard_stats_syncs')->first();

        // never filled (update seeder not run) on a big table: fill after the response is sent, so the page can't time out
        if (!$meta?->synced_at && DB::table('transactions')->skip(self::INLINE_REBUILD_LIMIT)->take(1)->exists()) {
            app()->terminating(function () {
                ignore_user_abort(true);
                set_time_limit(0);
                $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);
                if ($lock->get()) {
                    try { self::fillAll(); } finally { $lock->release(); }
                }
            });
            return;
        }

        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);
        if (!$lock->get()) return; // another request / the rebuild command is running, the current summary is used

        try {
            $started = now();
            if (!$meta?->synced_at) {
                self::fillAll();
                return;
            }
            $since = Carbon::parse($meta->synced_at)->subSeconds(self::SYNC_OVERLAP_SECONDS);
            // two index range scans (created_at / updated_at) instead of an OR over the whole table
            $days = DB::table('transactions')->where('created_at', '>=', $since)->selectRaw('DATE(created_at) as day')
                        ->union(DB::table('transactions')->where('updated_at', '>=', $since)->whereNotNull('created_at')->selectRaw('DATE(created_at) as day'))
                        ->pluck('day')->map(fn($day) => (string) $day)->all();
            self::rebuildDays($days);
            self::markSynced($started, $meta->id);
        } finally {
            $lock->release();
        }
    }

    /**
     * Summarize every transaction again (after install/update, bulk deletes or rate changes).
     * Waits until a running sync is finished.
     */
    public static function rebuildAll(): void
    {
        Cache::lock(self::LOCK, self::LOCK_SECONDS)->block(self::LOCK_SECONDS, fn() => self::fillAll());
    }

    private static function fillAll(): void
    {
        $started = now();
        DB::transaction(function () {
            DB::table(self::TABLE)->delete();
            self::insertSummary('1 = 1', []);
        });
        self::markSynced($started);
    }

    /**
     * Summarize the given days again
     * @param array $days Y-m-d
     */
    public static function rebuildDays(array $days): void
    {
        $days = array_values(array_unique(array_filter($days)));
        if (!$days) return;
        sort($days);

        // consecutive days as ranges, so the created_at index is used
        $ranges = [];
        foreach ($days as $day) {
            $last = count($ranges) - 1;
            if ($last >= 0 && Carbon::parse($ranges[$last][1])->addDay()->toDateString() === $day) {
                $ranges[$last][1] = $day;
            } else {
                $ranges[] = [$day, $day];
            }
        }
        $where = implode(' OR ', array_fill(0, count($ranges), '(transactions.created_at >= ? AND transactions.created_at < ?)'));
        $bindings = [];
        foreach ($ranges as [$from, $to]) {
            $bindings[] = $from . ' 00:00:00';
            $bindings[] = Carbon::parse($to)->addDay()->toDateString() . ' 00:00:00';
        }

        DB::transaction(function () use ($days, $where, $bindings) {
            DB::table(self::TABLE)->whereIn('day', $days)->delete();
            self::insertSummary('(' . $where . ')', $bindings);
        });
    }

    /**
     * One INSERT ... SELECT grouped by day, type, attribute, status
     */
    private static function insertSummary(string $where, array $bindings): void
    {
        $default_rate = (float) (get_default_currency_rate() ?: 1);
        $profit_rate  = "COALESCE(NULLIF(CASE WHEN transactions.type = '" . PaymentGatewayConst::TYPEADDMONEY . "' THEN payment_gateway_currencies.rate ELSE {$default_rate} END, 0), {$default_rate})";

        DB::insert("
            INSERT INTO " . self::TABLE . " (day, type, attribute, status, trx_count, amount, profit, created_at, updated_at)
            SELECT
                DATE(transactions.created_at),
                transactions.type,
                COALESCE(transactions.attribute, ''),
                transactions.status,
                COUNT(*),
                COALESCE(SUM(transactions.request_amount), 0),
                COALESCE(SUM(COALESCE((SELECT SUM(transaction_charges.total_charge) FROM transaction_charges WHERE transaction_charges.transaction_id = transactions.id), 0) / {$profit_rate}), 0),
                NOW(), NOW()
            FROM transactions
            LEFT JOIN payment_gateway_currencies ON payment_gateway_currencies.id = transactions.payment_gateway_currency_id
            WHERE transactions.created_at IS NOT NULL AND {$where}
            GROUP BY DATE(transactions.created_at), transactions.type, COALESCE(transactions.attribute, ''), transactions.status
        ", $bindings);
    }

    private static function markSynced($time, ?int $id = null): void
    {
        $id ??= DB::table('dashboard_stats_syncs')->value('id');
        if ($id) {
            DB::table('dashboard_stats_syncs')->where('id', $id)->update(['synced_at' => $time, 'updated_at' => now()]);
        } else {
            DB::table('dashboard_stats_syncs')->insert(['synced_at' => $time, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
