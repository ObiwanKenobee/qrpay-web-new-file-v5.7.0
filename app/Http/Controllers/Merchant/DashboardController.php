<?php
namespace App\Http\Controllers\Merchant;

use App\Models\Merchants\MerchantWallet;

use App\Models\Admin\ModuleSetting;

use App\Http\Helpers\DashboardStats;

use App\Constants\GlobalConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\Currency;
use App\Models\Merchants\SandboxWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $page_title     = __("Merchant Dashboard");
        $baseCurrency   = Currency::default();
        $merchant_id    = auth()->id();
        $base_rate      = (float) ($baseCurrency->rate ?? 1) ?: 1;
        $stats          = DashboardStats::merchant($merchant_id, $base_rate);   // 1 grouped query for every total
        $T              = PaymentGatewayConst::class;

        // wallet (single wallet system) - one query, also reused by the latest transactions
        $wallets        = MerchantWallet::auth()->with('currency')->get();
        $wallets_by_id  = $wallets->keyBy('id');
        $transactions   = Transaction::merchantAuth()->with('charge')->latest()->take(5)->get();
        $transactions->each(fn($trx) => $wallets_by_id->has($trx->merchant_wallet_id) ? $trx->setRelation('merchant_wallet', $wallets_by_id[$trx->merchant_wallet_id]) : null);

        $sandbox_fiat_wallets = SandboxWallet::auth()->whereHas("currency",function($q) {
            return $q->where("type",GlobalConst::FIAT);
        })->orderByDesc("balance")->limit(8)->get();

        $data = [
            'receive_money'         => $stats->baseAmount($T::TYPEMAKEPAYMENT, $T::RECEIVED),
            'money_out_amount'      => $stats->baseAmount($T::TYPEMONEYOUT),
            'total_transaction'     => $stats->totalSuccessCount(),
            'sandbox_fiat_wallets'  => $sandbox_fiat_wallets,
        ];

        // enabled merchant modules (one query)
        $modules = ModuleSetting::where('user_type', GlobalConst::MERCHANT)->pluck('status', 'slug');
        $on      = fn($slug) => $slug === null || (bool) ($modules[$slug] ?? false);

        $features = [
            ['module' => 'merchant-receive-money',    'label' => __("Received Payment"),  'icon' => 'las la-hand-holding-usd', 'tone' => 'green',  'route' => 'merchant.receive.money.index',   'amount' => $data['receive_money'],                     'count' => $stats->count($T::TYPEMAKEPAYMENT, $T::RECEIVED)],
            ['module' => 'merchant-gateway-settings', 'label' => __("Gateway Payment"),   'icon' => 'las la-shopping-cart',    'tone' => 'blue',   'route' => 'merchant.gateway.setting.index', 'amount' => $stats->baseAmount($T::MERCHANTPAYMENT),   'count' => $stats->count($T::MERCHANTPAYMENT)],
            ['module' => 'merchant-pay-link',         'label' => __("Payment Link"),      'icon' => 'las la-link',             'tone' => 'pink',   'route' => 'merchant.payment-link.index',    'amount' => $stats->baseAmount($T::TYPEPAYLINK),       'count' => $stats->count($T::TYPEPAYLINK)],
            ['module' => 'merchant-withdraw-money',   'label' => __("Total Withdraw"),    'icon' => 'las la-arrow-circle-up',  'tone' => 'red',    'route' => 'merchant.withdraw.index',        'amount' => $data['money_out_amount'],                  'count' => $stats->count($T::TYPEMONEYOUT)],
            ['module' => null,                        'label' => __("Total Transactions"),'icon' => 'las la-exchange-alt',     'tone' => 'purple', 'route' => 'merchant.transactions.index',    'number' => $data['total_transaction']],
        ];
        $quick = [
            ['module' => 'merchant-receive-money',    'label' => __("Receive Money"),     'icon' => 'las la-qrcode',           'tone' => 'green',  'route' => 'merchant.receive.money.index'],
            ['module' => 'merchant-withdraw-money',   'label' => __("Withdraw"),          'icon' => 'las la-arrow-circle-up',  'tone' => 'red',    'route' => 'merchant.withdraw.index'],
            ['module' => 'merchant-pay-link',         'label' => __("Payment Link"),      'icon' => 'las la-link',             'tone' => 'pink',   'route' => 'merchant.payment-link.index'],
            ['module' => 'merchant-gateway-settings', 'label' => __("Gateway Settings"),  'icon' => 'las la-cog',              'tone' => 'blue',   'route' => 'merchant.gateway.setting.index'],
            ['module' => 'merchant-api-key',          'label' => __("API Keys"),          'icon' => 'las la-key',              'tone' => 'orange', 'route' => 'merchant.developer.api.index'],
            ['module' => null,                        'label' => __("Transactions"),      'icon' => 'las la-list',             'tone' => 'purple', 'route' => 'merchant.transactions.index'],
        ];

        $overview = [
            'balance'       => $wallets->sum(fn($wallet) => (float) $wallet->balance / ((float) ($wallet->currency->rate ?? 1) ?: 1)) * $base_rate,
            'currency'      => $baseCurrency,
            'quick'         => array_values(array_filter($quick, fn($q) => $on($q['module']))),
            'month'         => $stats->month(),
            'pending_total' => $stats->pendingCount(),
            'pending_route' => 'merchant.transactions.index',
            'themed'        => true,
            'features'      => array_values(array_filter($features, fn($f) => $on($f['module']))),
        ];

        // money out chart for this month (one query)
        $chart     = $stats->chart([$T::TYPEMONEYOUT]);
        $chartData = [
            'chart_one_data' => $chart[$T::TYPEMONEYOUT],
            'month_day'      => $chart['month_day'],
        ];
        return view('merchant.dashboard',compact("page_title","baseCurrency",'transactions','data','chartData','overview'));

    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('merchant.login')->with(['success' => [__('Logout Successfully!')]]);
    }

}
