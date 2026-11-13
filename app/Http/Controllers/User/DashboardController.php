<?php
namespace App\Http\Controllers\User;

use App\Http\Helpers\DashboardStats;

use App\Constants\GlobalConst;

use App\Models\Admin\ModuleSetting;

use App\Models\UserWallet;

use App\Models\Admin\ExchangeRate;

use Illuminate\Support\Facades\DB;

use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\Currency;
use App\Models\Agent;
use App\Models\AgentQrCode;
use App\Models\GiftCard;
use App\Models\Merchants\Merchant;
use App\Models\Merchants\MerchantQrCode;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use App\Models\UserQrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\AdminNotifications\AuthNotifications;

class DashboardController extends Controller
{
    use AuthNotifications;
    public function index()
    {
        $page_title     = __("Dashboard");
        $baseCurrency   = Currency::default();
        $user_id        = auth()->id();
        $transactions   = Transaction::auth()->with('charge')->latest()->take(5)->get();

        // all transaction totals in ONE grouped query (by type / attribute / status),
        // with this-month counts as conditional aggregates
        $month_start = now()->startOfMonth();
        $month_end   = now()->endOfMonth();
        $stats      = DashboardStats::user($user_id, (float) ($baseCurrency->rate ?? 1) ?: 1);
        $all_totals = $stats->totals();
        $totals = $all_totals->where('status', PaymentGatewayConst::STATUSSUCCESS);
        $sum = fn($type, $attribute = null, $column = 'total_amount') => (float) $totals
            ->filter(fn($row) => $row->type == $type && ($attribute === null || $row->attribute == $attribute))
            ->sum($column);
        $base_rate = (float) ($baseCurrency->rate ?? 1) ?: 1;

        // this month activity + pending (needs action) from the same query
        $data['month'] = [
            'total'     => (int) $all_totals->sum('month_count'),
            'success'   => (int) $all_totals->where('status', PaymentGatewayConst::STATUSSUCCESS)->sum('month_count'),
            'pending'   => (int) $all_totals->where('status', PaymentGatewayConst::STATUSPENDING)->sum('month_count'),
            'rejected'  => (int) $all_totals->whereIn('status', [PaymentGatewayConst::STATUSREJECTED, PaymentGatewayConst::STATUSFAILD])->sum('month_count'),
        ];
        $data['pending_total'] = (int) $all_totals->where('status', PaymentGatewayConst::STATUSPENDING)->sum('total_count');

        // wallets (one query) + total balance in the base currency
        $wallets = UserWallet::auth()->with('currency')->get();
        $data['wallets']       = $wallets;
        // reuse the loaded wallets for the latest transactions (no second wallet / currency query)
        $wallets_by_id = $wallets->keyBy('id');
        $transactions->each(fn($trx) => $wallets_by_id->has($trx->user_wallet_id) ? $trx->setRelation('user_wallet', $wallets_by_id[$trx->user_wallet_id]) : null);
        $data['total_balance'] = $wallets->sum(fn($wallet) => (float) $wallet->balance / ((float) ($wallet->currency->rate ?? 1) ?: 1)) * $base_rate;

        $data['totalReceiveRemittance'] = $sum(PaymentGatewayConst::SENDREMITTANCE, PaymentGatewayConst::RECEIVED);
        $data['totalSendRemittance']    = $sum(PaymentGatewayConst::SENDREMITTANCE, PaymentGatewayConst::SEND);
        // money out can be paid from any wallet -> convert each wallet amount to the base currency
        $data['withdraw']               = $sum(PaymentGatewayConst::TYPEMONEYOUT, null, 'total_amount_base') * $base_rate;
        $data['total_transaction']      = (int) $totals->sum('total_count');

        // bill pay + mobile top up: amount is converted with the rate saved on each transaction, so one query for both
        $rate_based = Transaction::auth()
            ->whereIn('type', [PaymentGatewayConst::BILLPAY, PaymentGatewayConst::MOBILETOPUP])
            ->where('status', PaymentGatewayConst::STATUSSUCCESS)
            ->get(['id', 'type', 'request_amount', 'details']);
        $data['billPay']    = amountOnBaseCurrency($rate_based->where('type', PaymentGatewayConst::BILLPAY));
        $data['topUps']     = amountOnBaseCurrency($rate_based->where('type', PaymentGatewayConst::MOBILETOPUP));

        // virtual cards: load the exchange rates once instead of one query per card
        $card_info  = userActiveCardData();
        $cards      = $card_info['total_cards'] ?? [];
        $data['cards_active'] = (int) ($card_info['active_cards'] ?? 0);
        $data['cards_total']  = (int) ($card_info['virtual_cards'] ?? 0);
        $card_rates = ExchangeRate::whereIn('currency_code', collect($cards)->pluck('currency')->filter()->unique()->values())->pluck('rate', 'currency_code');
        $data['cardAmount'] = collect($cards)->sum(fn($card) => ($card->amount ?? $card->balance ?? 0) / ((float) ($card_rates[$card->currency ?? ''] ?? 1) ?: 1));

        $data['total_gift_cards'] = GiftCard::auth()->count();

        // one card per feature (only enabled modules). Values come from the grouped totals above, so no extra queries.
        $modules = ModuleSetting::where('user_type', GlobalConst::USER)->pluck('status', 'slug');
        $on      = fn($slug) => (bool) ($modules[$slug] ?? false);
        $cnt     = fn($type, $attribute = null) => (int) $totals->filter(fn($row) => $row->type == $type && ($attribute === null || $row->attribute == $attribute))->sum('total_count');
        $base_amount = fn($type, $attribute = null) => $sum($type, $attribute, 'total_amount_base') * $base_rate;
        $card_route  = [
            'flutterwave' => 'user.virtual.card.index', 'sudo' => 'user.sudo.virtual.card.index', 'stripe' => 'user.stripe.virtual.card.index',
            'strowallet' => 'user.strowallet.virtual.card.index', 'cardyfie' => 'user.cardyfie.virtual.card.index',
        ][activeCardSystem()] ?? null;
        $T = PaymentGatewayConst::class;
        $features = [
            ['module' => 'add-money',        'label' => __("Add Money"),                'icon' => 'las la-plus-circle',      'tone' => 'green',  'route' => 'user.add.money.index',      'amount' => $base_amount($T::TYPEADDMONEY),                 'count' => $cnt($T::TYPEADDMONEY)],
            ['module' => 'withdraw-money',   'label' => __("Total Withdraw"),           'icon' => 'las la-arrow-circle-up',  'tone' => 'red',    'route' => 'user.money.out.index',      'amount' => $data['withdraw'],                              'count' => $cnt($T::TYPEMONEYOUT)],
            ['module' => 'money-out',        'label' => __("Money Out"),                'icon' => 'las la-user-tie',         'tone' => 'orange', 'route' => 'user.agent.money.out.index', 'amount' => $base_amount($T::AGENTMONEYOUT),               'count' => $cnt($T::AGENTMONEYOUT)],
            ['module' => 'send-money',       'label' => __("Send Money"),               'icon' => 'las la-paper-plane',      'tone' => 'blue',   'route' => 'user.send.money.index',     'amount' => $base_amount($T::TYPETRANSFERMONEY, $T::SEND),     'count' => $cnt($T::TYPETRANSFERMONEY, $T::SEND)],
            ['module' => 'receive-money',    'label' => __("Received Money"),           'icon' => 'las la-hand-holding-usd', 'tone' => 'teal',   'route' => 'user.receive.money.index',  'amount' => $base_amount($T::TYPETRANSFERMONEY, $T::RECEIVED), 'count' => $cnt($T::TYPETRANSFERMONEY, $T::RECEIVED)],
            ['module' => 'make-payment',     'label' => __("Make Payment"),             'icon' => 'las la-store',            'tone' => 'purple', 'route' => 'user.make.payment.index',   'amount' => $base_amount($T::TYPEMAKEPAYMENT),              'count' => $cnt($T::TYPEMAKEPAYMENT)],
            ['module' => 'request-money',    'label' => __("Request Money"),            'icon' => 'las la-hand-holding',     'tone' => 'orange', 'route' => 'user.request.money.index',  'amount' => $base_amount($T::REQUESTMONEY),                 'count' => $cnt($T::REQUESTMONEY)],
            ['module' => 'pay-link',         'label' => __("Payment Link"),             'icon' => 'las la-link',             'tone' => 'pink',   'route' => 'user.payment-link.index',   'amount' => $base_amount($T::TYPEPAYLINK),                  'count' => $cnt($T::TYPEPAYLINK)],
            ['module' => 'remittance-money', 'label' => __("Total Send Remittance"),    'icon' => 'las la-globe',            'tone' => 'blue',   'route' => 'user.remittance.index',     'amount' => $data['totalSendRemittance'],                   'count' => $cnt($T::SENDREMITTANCE, $T::SEND)],
            ['module' => 'remittance-money', 'label' => __("Total Receive Remittance"), 'icon' => 'las la-receipt',          'tone' => 'green',  'route' => 'user.remittance.index',     'amount' => $data['totalReceiveRemittance'],                'count' => $cnt($T::SENDREMITTANCE, $T::RECEIVED)],
            ['module' => 'bill-pay',         'label' => __("Total Bill Pay"),           'icon' => 'las la-file-invoice',     'tone' => 'orange', 'route' => 'user.bill.pay.index',       'amount' => $data['billPay'],                               'count' => $cnt($T::BILLPAY)],
            ['module' => 'mobile-top-up',    'label' => __("Total Mobile TopUp"),       'icon' => 'las la-mobile',           'tone' => 'teal',   'route' => 'user.mobile.topup.index',   'amount' => $data['topUps'],                                'count' => $cnt($T::MOBILETOPUP)],
            ['module' => 'virtual-card',     'label' => __("Virtual Card"),             'icon' => 'las la-credit-card',      'tone' => 'purple', 'route' => $card_route,                 'amount' => $data['cardAmount'],                            'badge' => $data['cards_active'] . ' / ' . $data['cards_total'] . ' ' . __("Active")],
            ['module' => 'gift-cards',       'label' => __("Total Gift Cards"),         'icon' => 'las la-gift',             'tone' => 'pink',   'route' => 'user.gift.card.index',      'number' => $data['total_gift_cards']],
            ['module' => null,               'label' => __("Total Transactions"),       'icon' => 'las la-exchange-alt',     'tone' => 'blue',   'route' => 'user.transactions.index',   'number' => $data['total_transaction']],
        ];
        $data['features'] = array_values(array_filter($features, fn($f) => $f['module'] === null || $on($f['module'])));

        // quick access: popular features (enabled modules only)
        $quick = [
            ['module' => 'add-money',      'label' => __("Add Money"),     'icon' => 'las la-plus-circle', 'tone' => 'green',     'route' => 'user.add.money.index'],
            ['module' => 'send-money',     'label' => __("Send Money"),    'icon' => 'las la-paper-plane', 'tone' => 'blue',     'route' => 'user.send.money.index'],
            ['module' => 'withdraw-money', 'label' => __("Withdraw"),      'icon' => 'las la-arrow-circle-up', 'tone' => 'red', 'route' => 'user.money.out.index'],
            ['module' => 'receive-money',  'label' => __("Receive Money"), 'icon' => 'las la-qrcode', 'tone' => 'teal',          'route' => 'user.receive.money.index'],
            ['module' => 'make-payment',   'label' => __("Make Payment"),  'icon' => 'las la-store', 'tone' => 'purple',           'route' => 'user.make.payment.index'],
            ['module' => 'bill-pay',       'label' => __("Bill Pay"),      'icon' => 'las la-file-invoice', 'tone' => 'orange',    'route' => 'user.bill.pay.index'],
            ['module' => 'mobile-top-up',  'label' => __("Mobile TopUp"),  'icon' => 'las la-mobile', 'tone' => 'teal',          'route' => 'user.mobile.topup.index'],
            ['module' => 'virtual-card',   'label' => __("Virtual Card"),  'icon' => 'las la-credit-card', 'tone' => 'purple',     'route' => $card_route],
            ['module' => 'gift-cards',     'label' => __("Gift Card"),     'icon' => 'las la-gift', 'tone' => 'pink',            'route' => 'user.gift.card.list'],
            ['module' => 'request-money',  'label' => __("Request Money"), 'icon' => 'las la-hand-holding', 'tone' => 'orange',    'route' => 'user.request.money.index'],
            ['module' => 'money-out',      'label' => __("Money Out"),     'icon' => 'las la-user-tie', 'tone' => 'red',        'route' => 'user.agent.money.out.index'],
            ['module' => 'pay-link',       'label' => __("Payment Link"),  'icon' => 'las la-link', 'tone' => 'pink',            'route' => 'user.payment-link.index'],
        ];
        $data['quick'] = array_values(array_filter($quick, fn($q) => $on($q['module']) && $q['route']));

        // charts: add money + money out per day and status for this month in ONE grouped query
        $daily = Transaction::auth()
            ->whereIn('type', [PaymentGatewayConst::TYPEADDMONEY, PaymentGatewayConst::TYPEMONEYOUT])
            ->whereBetween('created_at', [$month_start, $month_end])
            ->groupBy('type', 'status', DB::raw('DATE(created_at)'))
            ->selectRaw('type, status, DATE(created_at) as day, COUNT(*) as total')
            ->get()
            ->groupBy(fn($row) => $row->type . '|' . $row->status . '|' . $row->day);
        $count = fn($type, $status, $day) => (int) ($daily->get($type . '|' . $status . '|' . $day)?->first()->total ?? 0);

        $month_day = [];
        $chart_one_data = $chart_two_data = ['pending_data' => [], 'success_data' => [], 'canceled_data' => [], 'hold_data' => []];
        $statuses = [
            'pending_data'  => PaymentGatewayConst::STATUSPENDING,
            'success_data'  => PaymentGatewayConst::STATUSSUCCESS,
            'canceled_data' => PaymentGatewayConst::STATUSREJECTED,
            'hold_data'     => PaymentGatewayConst::STATUSHOLD,
        ];
        for ($day = $month_start->copy(); $day->lte($month_end); $day->addDay()) {
            $date = $day->format('Y-m-d');
            foreach ($statuses as $key => $status) {
                $chart_one_data[$key][] = $count(PaymentGatewayConst::TYPEADDMONEY, $status, $date);
                $chart_two_data[$key][] = $count(PaymentGatewayConst::TYPEMONEYOUT, $status, $date);
            }
            $month_day[] = $date;
        }
        $chartData = [
            'chart_one_data'   => $chart_one_data,
            'chart_two_data'   => $chart_two_data,
            'month_day'        => $month_day,
        ];

        return view('user.dashboard',compact("page_title","baseCurrency",'transactions','data','chartData'));
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login')->with(['success' => [__('Logout Successfully!')]]);
    }
    public function qrScan($qr_code)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: PUT, GET, POST");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        $qrCode = UserQrCode::where('qr_code',$qr_code)->first();
        if(!$qrCode){
            return response()->json(['error'=>__("Invalid request")]);
        }
        $user = User::where('id',$qrCode->user_id)->active()->first();
        if(!$user){
            return response()->json(['error'=>__('Not found')]);
        }
        return $user->email??$user->full_mobile;
    }
    public function agentQrScan($qr_code)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: PUT, GET, POST");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        $qrCode = AgentQrCode::where('qr_code',$qr_code)->first();
        if(!$qrCode){
            return response()->json(['error'=>__("Invalid request")]);
        }
        $user = Agent::where('id',$qrCode->agent_id)->active()->first();
        if(!$user){
            return response()->json(['error'=>__('Invalid Agent')]);
        }
        return $user->email??$user->full_mobile;
    }
    public function merchantQrScan($qr_code)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: PUT, GET, POST");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        $qrCode = MerchantQrCode::where('qr_code',$qr_code)->first();
        if(!$qrCode){
            return response()->json(['error'=>__("Invalid request")]);
        }
        $user = Merchant::where('id',$qrCode->merchant_id)->active()->first();
        if(!$user){
            return response()->json(['error'=>__('Invalid merchant')]);
        }
        return $user->email??$user->full_mobile;
    }
    public function deleteAccount(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required',
        ]);
        $user = auth()->user();
        //make unsubscribe
         try{
            (new PushNotificationHelper(['users' => [$user->id],'user_type' => 'user']))->unsubscribe();
        }catch(Exception $e) {
            // handle exception
        }
        //admin notification
        $this->deleteUserNotificationToAdmin($user,"USER",'web');
        $user->status = false;
        $user->email_verified = false;
        $user->kyc_verified = false;
        $user->deleted_at = now();
        $user->save();
        try{
            Auth::logout();
            return redirect()->route('index')->with(['success' => [__('Your profile deleted successfully!')]]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
    }
}
