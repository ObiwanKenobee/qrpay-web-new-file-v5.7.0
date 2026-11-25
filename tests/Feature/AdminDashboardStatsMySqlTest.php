<?php

namespace Tests\\Feature;

use App\\Constants\\PaymentGatewayConst;
use App\\Http\\Helpers\\AdminDashboardStats;
use App\\Providers\\Admin\\CurrencyProvider;
use Illuminate\\Foundation\\Testing\\DatabaseTransactions;
use Illuminate\\Support\\Facades\\Cache;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Str;
use Tests\\TestCase;

class AdminDashboardStatsMySqlTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Dashboard aggregation compatibility is verified in the MySQL CI job.');
        }

        Cache::setDefaultDriver('array');
        $this->app->instance(
            CurrencyProvider::class,
            new CurrencyProvider((object) ['rate' => 2.0])
        );
    }

    public function test_rebuild_aggregates_per_transaction_profit_with_mysql_strict_grouping(): void
    {
        $day = '2026-11-25';
        $createdAt = $day.' 09:30:00';

        $addMoneyCurrencyId = $this->createGatewayCurrency('dashboard-add-'.Str::uuid(), 4.0);
        $moneyOutCurrencyId = $this->createGatewayCurrency('dashboard-out-'.Str::uuid(), 99.0);

        $firstAddMoney = $this->createTransaction(PaymentGatewayConst::TYPEADDMONEY, $addMoneyCurrencyId, 100, $createdAt);
        $this->createCharge($firstAddMoney, 4);
        $this->createCharge($firstAddMoney, 4);

        $secondAddMoney = $this->createTransaction(PaymentGatewayConst::TYPEADDMONEY, $addMoneyCurrencyId, 50, $createdAt);
        $this->createCharge($secondAddMoney, 12);

        $moneyOut = $this->createTransaction(PaymentGatewayConst::TYPEMONEYOUT, $moneyOutCurrencyId, 30, $createdAt);
        $this->createCharge($moneyOut, 10);

        AdminDashboardStats::rebuildAll();

        $addMoneySummary = DB::table('dashboard_daily_stats')
            ->where('day', $day)
            ->where('type', PaymentGatewayConst::TYPEADDMONEY)
            ->where('attribute', PaymentGatewayConst::SEND)
            ->where('status', PaymentGatewayConst::STATUSSUCCESS)
            ->first();

        $moneyOutSummary = DB::table('dashboard_daily_stats')
            ->where('day', $day)
            ->where('type', PaymentGatewayConst::TYPEMONEYOUT)
            ->where('attribute', PaymentGatewayConst::SEND)
            ->where('status', PaymentGatewayConst::STATUSSUCCESS)
            ->first();

        $this->assertNotNull($addMoneySummary);
        $this->assertSame(2, (int) $addMoneySummary->trx_count);
        $this->assertEqualsWithDelta(150.0, (float) $addMoneySummary->amount, 0.000001);
        $this->assertEqualsWithDelta(5.0, (float) $addMoneySummary->profit, 0.000001);

        $this->assertNotNull($moneyOutSummary);
        $this->assertSame(1, (int) $moneyOutSummary->trx_count);
        $this->assertEqualsWithDelta(30.0, (float) $moneyOutSummary->amount, 0.000001);
        $this->assertEqualsWithDelta(5.0, (float) $moneyOutSummary->profit, 0.000001);
    }

    private function createGatewayCurrency(string $alias, float $rate): int
    {
        return (int) DB::table('payment_gateway_currencies')->insertGetId([
            'payment_gateway_id' => 1,
            'name' => 'Dashboard test currency',
            'alias' => $alias,
            'currency_code' => 'KES',
            'rate' => $rate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTransaction(string $type, int $currencyId, float $amount, string $createdAt): int
    {
        return (int) DB::table('transactions')->insertGetId([
            'payment_gateway_currency_id' => $currencyId,
            'type' => $type,
            'trx_id' => 'dashboard-test-'.Str::uuid(),
            'request_amount' => $amount,
            'status' => PaymentGatewayConst::STATUSSUCCESS,
            'attribute' => PaymentGatewayConst::SEND,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createCharge(int $transactionId, float $total): void
    {
        DB::table('transaction_charges')->insert([
            'transaction_id' => $transactionId,
            'total_charge' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
