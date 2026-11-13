<?php
namespace App\Http\Controllers\Agent;

use App\Constants\GlobalConst;

use App\Models\AgentWallet;

use App\Models\Admin\ModuleSetting;

use App\Http\Helpers\DashboardStats;

use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\Currency;
use App\Models\Agent;
use App\Models\AgentProfit;
use App\Models\AgentQrCode;
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
        $page_title     = __( "Dashboard");
        $baseCurrency   = Currency::default();
        $agent_id       = auth()->id();
        $base_rate      = (float) ($baseCurrency->rate ?? 1) ?: 1;
        $stats          = DashboardStats::agent($agent_id, $base_rate);   // 1 grouped query for every total
        $T              = PaymentGatewayConst::class;

        // wallet (single wallet system) - one query, also reused by the latest transactions
        $wallets        = AgentWallet::auth()->with('currency')->get();
        $wallets_by_id  = $wallets->keyBy('id');
        // latest 5: gateway currency + gateway eager loaded once; `currency` and `gateway_currency` are the same relation
        $transactions   = Transaction::agentAuth()->with(['charge', 'currency.gateway.currencies'])->latest()->take(5)->get();
        $transactions->each(function ($trx) use ($wallets_by_id) {
            $trx->setRelation('gateway_currency', $trx->currency);
            if ($wallets_by_id->has($trx->agent_wallet_id)) $trx->setRelation('agent_wallet', $wallets_by_id[$trx->agent_wallet_id]);
        });

        // bill pay + mobile top up: converted with the rate saved on each transaction, one query for both
        $rate_based = Transaction::agentAuth()
            ->whereIn('type', [$T::BILLPAY, $T::MOBILETOPUP])
            ->where('status', $T::STATUSSUCCESS)
            ->get(['id', 'type', 'request_amount', 'details']);

        $data['totalAddMoney']       = $stats->baseAmount($T::TYPEADDMONEY);
        $data['totalWithdrawMoney']  = $stats->baseAmount($T::TYPEMONEYOUT);
        $data['totalSendMoney']      = $stats->baseAmount($T::TYPETRANSFERMONEY, $T::SEND);
        $data['totalMoneyIn']        = $stats->baseAmount($T::MONEYIN);
        $data['totalReceiveMoney']   = $stats->baseAmount($T::AGENTMONEYOUT);
        // successful remittance only (pending / rejected are not "sent")
        $data['totalSendRemittance'] = $stats->baseAmount($T::SENDREMITTANCE, $T::SEND);
        $data['billPay']             = amountOnBaseCurrency($rate_based->where('type', $T::BILLPAY));
        $data['topUps']              = amountOnBaseCurrency($rate_based->where('type', $T::MOBILETOPUP));
        $data['total_transaction']   = $stats->totalSuccessCount();
        $data['agent_profits']       = AgentProfit::agentAuth()->sum('total_charge');

        // enabled agent modules (one query)
        $modules = ModuleSetting::where('user_type', GlobalConst::AGENT)->pluck('status', 'slug');
        $on      = fn($slug) => $slug === null || (bool) ($modules[$slug] ?? false);

        $features = [
            ['module' => 'agent-add-money',        'label' => __("Add Money"),             'icon' => 'las la-plus-circle',      'tone' => 'green',  'route' => 'agent.add.money.index',     'amount' => $data['totalAddMoney'],       'count' => $stats->count($T::TYPEADDMONEY)],
            ['module' => 'agent-withdraw-money',   'label' => __("Total Withdraw"),        'icon' => 'las la-arrow-circle-up',  'tone' => 'red',    'route' => 'agent.money.out.index',     'amount' => $data['totalWithdrawMoney'],  'count' => $stats->count($T::TYPEMONEYOUT)],
            ['module' => 'agent-transfer-money',   'label' => __("Send Money"),            'icon' => 'las la-paper-plane',      'tone' => 'blue',   'route' => 'agent.send.money.index',    'amount' => $data['totalSendMoney'],      'count' => $stats->count($T::TYPETRANSFERMONEY, $T::SEND)],
            ['module' => 'agent-money-in',         'label' => __("Money In"),              'icon' => 'las la-arrow-circle-down','tone' => 'teal',   'route' => 'agent.money.in.index',      'amount' => $data['totalMoneyIn'],        'count' => $stats->count($T::MONEYIN)],
            ['module' => 'agent-receive-money',    'label' => __("Receive Money"),         'icon' => 'las la-hand-holding-usd', 'tone' => 'green',  'route' => 'agent.receive.money.index', 'amount' => $data['totalReceiveMoney'],   'count' => $stats->count($T::AGENTMONEYOUT)],
            ['module' => 'agent-remittance-money', 'label' => __("Total Send Remittance"), 'icon' => 'las la-globe',            'tone' => 'purple', 'route' => 'agent.remittance.index',    'amount' => $data['totalSendRemittance'], 'count' => $stats->count($T::SENDREMITTANCE, $T::SEND)],
            ['module' => 'agent-bill-pay',         'label' => __("Total Bill Pay"),        'icon' => 'las la-file-invoice',     'tone' => 'orange', 'route' => 'agent.bill.pay.index',      'amount' => $data['billPay'],             'count' => $stats->count($T::BILLPAY)],
            ['module' => 'agent-mobile-top-up',    'label' => __("Total Mobile TopUp"),    'icon' => 'las la-mobile',           'tone' => 'teal',   'route' => 'agent.mobile.topup.index',  'amount' => $data['topUps'],              'count' => $stats->count($T::MOBILETOPUP)],
            ['module' => null,                     'label' => __("Total Profit"),          'icon' => 'las la-coins',            'tone' => 'orange', 'route' => 'agent.profits.index',       'amount' => $data['agent_profits']],
            ['module' => null,                     'label' => __("Total Transactions"),    'icon' => 'las la-exchange-alt',     'tone' => 'blue',   'route' => 'agent.transactions.index',  'number' => $data['total_transaction']],
        ];
        $quick = [
            ['module' => 'agent-add-money',        'label' => __("Add Money"),        'icon' => 'las la-plus-circle',       'tone' => 'green',  'route' => 'agent.add.money.index'],
            ['module' => 'agent-transfer-money',   'label' => __("Send Money"),       'icon' => 'las la-paper-plane',       'tone' => 'blue',   'route' => 'agent.send.money.index'],
            ['module' => 'agent-money-in',         'label' => __("Money In"),         'icon' => 'las la-arrow-circle-down', 'tone' => 'teal',   'route' => 'agent.money.in.index'],
            ['module' => 'agent-withdraw-money',   'label' => __("Withdraw"),         'icon' => 'las la-arrow-circle-up',   'tone' => 'red',    'route' => 'agent.money.out.index'],
            ['module' => 'agent-receive-money',    'label' => __("Receive Money"),    'icon' => 'las la-qrcode',            'tone' => 'green',  'route' => 'agent.receive.money.index'],
            ['module' => 'agent-bill-pay',         'label' => __("Bill Pay"),         'icon' => 'las la-file-invoice',      'tone' => 'orange', 'route' => 'agent.bill.pay.index'],
            ['module' => 'agent-mobile-top-up',    'label' => __("Mobile TopUp"),     'icon' => 'las la-mobile',            'tone' => 'teal',   'route' => 'agent.mobile.topup.index'],
            ['module' => 'agent-remittance-money', 'label' => __("Remittance"),       'icon' => 'las la-globe',             'tone' => 'purple', 'route' => 'agent.remittance.index'],
            ['module' => 'agent-remittance-money', 'label' => __("Saved My Sender"),  'icon' => 'las la-user-edit',         'tone' => 'blue',   'route' => 'agent.sender.recipient.index'],
            ['module' => 'agent-remittance-money', 'label' => __("Saved My Receiver"),'icon' => 'las la-user-check',        'tone' => 'pink',   'route' => 'agent.receiver.recipient.index'],
            ['module' => null,                     'label' => __("Profits Log"),      'icon' => 'las la-coins',             'tone' => 'orange', 'route' => 'agent.profits.index'],
            ['module' => null,                     'label' => __("Transactions"),     'icon' => 'las la-list',              'tone' => 'purple', 'route' => 'agent.transactions.index'],
        ];

        $overview = [
            'balance'       => $wallets->sum(fn($wallet) => (float) $wallet->balance / ((float) ($wallet->currency->rate ?? 1) ?: 1)) * $base_rate,
            'currency'      => $baseCurrency,
            'quick'         => array_values(array_filter($quick, fn($q) => $on($q['module']))),
            'month'         => $stats->month(),
            'pending_total' => $stats->pendingCount(),
            'pending_route' => 'agent.transactions.index',
            'themed'        => true,
            'features'      => array_values(array_filter($features, fn($f) => $on($f['module']))),
        ];

        // add money + money out charts for this month (one query)
        $chart     = $stats->chart([$T::TYPEADDMONEY, $T::TYPEMONEYOUT]);
        $chartData = [
            'chart_one_data' => $chart[$T::TYPEADDMONEY],
            'chart_two_data' => $chart[$T::TYPEMONEYOUT],
            'month_day'      => $chart['month_day'],
        ];
        return view('agent.dashboard',compact("page_title","baseCurrency",'transactions','data','chartData','overview'));

    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('agent.login')->with(['success' => [__('Logout Successfully!')]]);
    }
    public function qrScan($qr_code)
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
        return $user->email ?? $user->full_mobile;
    }
    public function userQrScan($qr_code)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: PUT, GET, POST");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        $qrCode = UserQrCode::where('qr_code',$qr_code)->first();
        if(!$qrCode){
            return response()->json(['error'=>__("Invalid request")]);
        }
        $user = User::fwhere('id',$qrCode->user_id)->active()->first();
        if(!$user){
            return response()->json(['error'=>__('Not found')]);
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
            (new PushNotificationHelper(['users' => [$user->id],'user_type' => 'agent']))->unsubscribe();
        }catch(Exception $e) {}
        //admin notification
        $this->deleteUserNotificationToAdmin($user,"AGENT",'agent');
        $user->status = false;
        $user->email_verified = false;
        $user->kyc_verified = false;
        $user->deleted_at = now();
        $user->save();
        try{
            Auth::logout();
            return redirect()->route('agent.login')->with(['success' => [__('Your profile deleted successfully!')]]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
    }
}
