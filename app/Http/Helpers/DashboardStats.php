<?php

namespace App\Http\Helpers;

use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard numbers for one owner (user / merchant / agent) with a fixed, small number of queries:
 *  - totals(): ONE grouped query (type, attribute, status) with counts, amounts, amounts in base currency and this-month counts
 *  - chart():  ONE grouped query (type, status, day) for the current month
 * Works on the transactions table indexes (owner_id, type, status, ...) / (owner_id, created_at).
 */
class DashboardStats
{
    protected ?Collection $totals = null;

    /**
     * @param string $ownerColumn   user_id | merchant_id | agent_id
     * @param int    $ownerId
     * @param string $walletColumn  user_wallet_id | merchant_wallet_id | agent_wallet_id
     * @param string $walletTable   user_wallets | merchant_wallets | agent_wallets
     * @param float  $baseRate      default currency rate
     */
    public function __construct(
        protected string $ownerColumn,
        protected int $ownerId,
        protected string $walletColumn,
        protected string $walletTable,
        protected float $baseRate = 1,
    ) {
        $this->baseRate = $baseRate ?: 1;
    }

    public static function user(int $id, float $baseRate = 1): self
    {
        return new self('user_id', $id, 'user_wallet_id', 'user_wallets', $baseRate);
    }

    public static function merchant(int $id, float $baseRate = 1): self
    {
        return new self('merchant_id', $id, 'merchant_wallet_id', 'merchant_wallets', $baseRate);
    }

    public static function agent(int $id, float $baseRate = 1): self
    {
        return new self('agent_id', $id, 'agent_wallet_id', 'agent_wallets', $baseRate);
    }

    /**
     * All transactions grouped by type / attribute / status (cached on the instance, runs once).
     */
    public function totals(): Collection
    {
        if ($this->totals !== null) return $this->totals;

        $month_start = now()->startOfMonth();
        $month_end   = now()->endOfMonth();

        return $this->totals = DB::table('transactions as t')
            ->leftJoin($this->walletTable . ' as w', 'w.id', '=', 't.' . $this->walletColumn)
            ->leftJoin('currencies as c', 'c.id', '=', 'w.currency_id')
            ->where('t.' . $this->ownerColumn, $this->ownerId)
            ->groupBy('t.type', 't.attribute', 't.status')
            ->selectRaw('t.type, t.attribute, t.status, COUNT(*) as total_count, SUM(t.request_amount) as total_amount,
                SUM(t.request_amount / COALESCE(NULLIF(c.rate, 0), 1)) as total_amount_base,
                SUM(CASE WHEN t.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as month_count', [$month_start, $month_end])
            ->get();
    }

    protected function success(): Collection
    {
        return $this->totals()->where('status', PaymentGatewayConst::STATUSSUCCESS);
    }

    protected function filter(Collection $rows, string $type, ?string $attribute): Collection
    {
        return $rows->filter(fn($row) => $row->type == $type && ($attribute === null || $row->attribute == $attribute));
    }

    /** Successful amount (as stored, request_amount). */
    public function amount(string $type, ?string $attribute = null): float
    {
        return (float) $this->filter($this->success(), $type, $attribute)->sum('total_amount');
    }

    /** Successful amount converted from each wallet currency to the base currency. */
    public function baseAmount(string $type, ?string $attribute = null): float
    {
        return (float) $this->filter($this->success(), $type, $attribute)->sum('total_amount_base') * $this->baseRate;
    }

    /** Successful transaction count. */
    public function count(string $type, ?string $attribute = null): int
    {
        return (int) $this->filter($this->success(), $type, $attribute)->sum('total_count');
    }

    public function totalSuccessCount(): int
    {
        return (int) $this->success()->sum('total_count');
    }

    public function pendingCount(): int
    {
        return (int) $this->totals()->where('status', PaymentGatewayConst::STATUSPENDING)->sum('total_count');
    }

    /** This month: total / success / pending / rejected counts. */
    public function month(): array
    {
        $rows = $this->totals();
        return [
            'total'    => (int) $rows->sum('month_count'),
            'success'  => (int) $rows->where('status', PaymentGatewayConst::STATUSSUCCESS)->sum('month_count'),
            'pending'  => (int) $rows->where('status', PaymentGatewayConst::STATUSPENDING)->sum('month_count'),
            'rejected' => (int) $rows->whereIn('status', [PaymentGatewayConst::STATUSREJECTED, PaymentGatewayConst::STATUSFAILD])->sum('month_count'),
        ];
    }

    /**
     * Daily status counts for the current month in ONE query.
     * Returns ['month_day' => [...], TYPE => ['pending_data' => [...], 'success_data' => [...], 'canceled_data' => [...], 'hold_data' => [...]], ...]
     */
    public function chart(array $types): array
    {
        $month_start = now()->startOfMonth();
        $month_end   = now()->endOfMonth();

        $daily = DB::table('transactions')
            ->where($this->ownerColumn, $this->ownerId)
            ->whereIn('type', $types)
            ->whereBetween('created_at', [$month_start, $month_end])
            ->groupBy('type', 'status', DB::raw('DATE(created_at)'))
            ->selectRaw('type, status, DATE(created_at) as day, COUNT(*) as total')
            ->get()
            ->keyBy(fn($row) => $row->type . '|' . $row->status . '|' . $row->day);

        $statuses = [
            'pending_data'  => PaymentGatewayConst::STATUSPENDING,
            'success_data'  => PaymentGatewayConst::STATUSSUCCESS,
            'canceled_data' => PaymentGatewayConst::STATUSREJECTED,
            'hold_data'     => PaymentGatewayConst::STATUSHOLD,
        ];
        $result = ['month_day' => []];
        foreach ($types as $type) {
            $result[$type] = array_fill_keys(array_keys($statuses), []);
        }
        for ($day = $month_start->copy(); $day->lte($month_end); $day->addDay()) {
            $date = $day->format('Y-m-d');
            foreach ($types as $type) {
                foreach ($statuses as $key => $status) {
                    $result[$type][$key][] = (int) ($daily->get($type . '|' . $status . '|' . $date)->total ?? 0);
                }
            }
            $result['month_day'][] = $date;
        }

        return $result;
    }
}
