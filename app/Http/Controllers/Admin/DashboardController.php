<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pusher\PushNotifications\PushNotifications;
use App\Models\Admin\AdminNotification;
use App\Constants\GlobalConst;
use App\Constants\ModuleSetting as ModuleSettingConst;
use App\Constants\PaymentGatewayConst;
use App\Constants\SupportTicketConst;
use App\Http\Helpers\Response;
use App\Models\Admin\ModuleSetting;
use App\Http\Helpers\AdminDashboardStats as DashboardStats;
use App\Models\Agent;
use App\Models\Merchants\Merchant;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\User;
use App\Models\UserSupportTicket;


class DashboardController extends Controller
{
    // seconds the dashboard totals are kept (numbers can be up to this old)
    const STATS_CACHE_SECONDS = 15;

    /**
     * Dashboard service => Module Settings slug per account type that uses it
     */
    // transaction types shown in the boxes / charts (one grouped query each)
    const BOX_TYPES = [
        PaymentGatewayConst::TYPEADDMONEY, PaymentGatewayConst::TYPEMONEYOUT, PaymentGatewayConst::TYPETRANSFERMONEY, PaymentGatewayConst::TYPEMAKEPAYMENT,
        PaymentGatewayConst::BILLPAY, PaymentGatewayConst::MOBILETOPUP, PaymentGatewayConst::SENDREMITTANCE,
        PaymentGatewayConst::MONEYIN, PaymentGatewayConst::AGENTMONEYOUT, PaymentGatewayConst::TYPEPAYLINK, PaymentGatewayConst::MERCHANTPAYMENT,
    ];
    const CHART_TYPES = [
        PaymentGatewayConst::TYPEADDMONEY, PaymentGatewayConst::TYPEMONEYOUT, PaymentGatewayConst::TYPETRANSFERMONEY, PaymentGatewayConst::TYPEMAKEPAYMENT,
        PaymentGatewayConst::BILLPAY, PaymentGatewayConst::MOBILETOPUP, PaymentGatewayConst::SENDREMITTANCE,
        PaymentGatewayConst::MONEYIN, PaymentGatewayConst::GIFTCARD, PaymentGatewayConst::VIRTUALCARD, PaymentGatewayConst::TYPEPAYLINK,
    ];

    const SERVICE_MODULES = [
        'add_money'         => ['user' => ModuleSettingConst::ADD_MONEY, 'agent' => ModuleSettingConst::AGENT_ADD_MONEY],
        'money_out'         => ['user' => ModuleSettingConst::WITHDRAW_MONEY, 'agent' => ModuleSettingConst::AGENT_WITHDRAW_MONEY, 'merchant' => ModuleSettingConst::MERCHANT_WITHDRAW_MONEY],
        'send_money'        => ['user' => ModuleSettingConst::SEND_MONEY, 'agent' => ModuleSettingConst::AGENT_TRANSFER_MONEY],
        'make_payment'      => ['user' => ModuleSettingConst::MAKE_PAYMENT],
        'bill_pay'          => ['user' => ModuleSettingConst::BILL_PAY, 'agent' => ModuleSettingConst::AGENT_BILL_PAY],
        'mobile_topup'      => ['user' => ModuleSettingConst::MOBILE_TOPUP, 'agent' => ModuleSettingConst::AGENT_MOBILE_TOPUP],
        'remittance'        => ['user' => ModuleSettingConst::REMITTANCE_MONEY, 'agent' => ModuleSettingConst::AGENT_REMITTANCE_MONEY],
        'money_in'          => ['agent' => ModuleSettingConst::AGENT_MONEY_IN],
        'agent_money_out'   => ['user' => ModuleSettingConst::AGENTMONEYOUT],
        'collections'       => ['user' => ModuleSettingConst::PAY_LINK, 'merchant' => ModuleSettingConst::MERCHANT_PAY_LINK],
        'virtual_card'      => ['user' => ModuleSettingConst::VIRTUAL_CARD],
        'gift_card'         => ['user' => ModuleSettingConst::GIFTCARDS],
    ];

    /**
     * Dashboard service / platform item => admin permission (log page route) needed to see it
     */
    const SERVICE_PERMISSIONS = [
        'add_money'         => 'admin.add.money.index',
        'money_out'         => 'admin.money.out.index',
        'send_money'        => 'admin.send.money.index',
        'make_payment'      => 'admin.make.payment.index',
        'bill_pay'          => 'admin.bill.pay.index',
        'mobile_topup'      => 'admin.mobile.topup.index',
        'remittance'        => 'admin.remitance.index',
        'money_in'          => 'admin.money.in.index',
        'agent_money_out'   => 'admin.agent.money.out.index',
        'collections'       => 'admin.payment.link.index',
        'virtual_card'      => 'admin.virtual.card.logs',
        'gift_card'         => 'admin.gift.card.index',
    ];
    const PLATFORM_PERMISSIONS = [
        'profit'    => 'admin.profit.logs.index',
        'users'     => 'admin.users.index',
        'agents'    => 'admin.agents.index',
        'merchants' => 'admin.merchants.index',
        'tickets'   => 'admin.support.ticket.index',
    ];

    /**
     * Dashboard: core QRPay metrics (single currency: every amount is in the default currency) (money flow, services, agents & merchants, platform)
     * Amounts are in the default currency (request_amount / creator wallet currency rate, same rule as amountOnBaseCurrency()).
     * Super admin sees everything, other admins only the parts their role has permission for.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __("Dashboard");

        // totals are cached briefly (shared by all admins) so the page opens fast even with a lot of transactions
        $data = Cache::remember('admin-dashboard-stats:v2:'.app()->getLocale(), self::STATS_CACHE_SECONDS, fn() => $this->dashboardStats());
        $data = $this->forAuthAdmin($data);
        $data['transactions'] = $data['can']['services']['add_money'] ? $this->latestAddMoney() : collect();

        return view('admin.sections.dashboard.index',compact(
            'page_title','data'
        ));
    }

    /**
     * Keep only what the logged in admin has permission to see
     */
    private function forAuthAdmin(array $data): array
    {
        $services = array_map(fn($route) => admin_permission_by_name($route), self::SERVICE_PERMISSIONS);
        $platform = array_map(fn($route) => admin_permission_by_name($route), self::PLATFORM_PERMISSIONS);

        $data['pending_actions'] = array_values(array_filter($data['pending_actions'], fn($action) => admin_permission_by_name($action['route'])));

        $data['volume_chart'] = array_values(array_map(
            fn($series) => ['name' => $series['name'], 'data' => $series['data']],
            array_filter($data['volume_chart'], fn($series) => $services[$series['service']]),
        ));

        $service_chart = ['labels' => [], 'data' => []];
        foreach($data['service_chart']['services'] as $index => $service) {
            if(!$services[$service]) continue;
            $service_chart['labels'][] = $data['service_chart']['labels'][$index];
            $service_chart['data'][] = $data['service_chart']['data'][$index];
        }
        $data['service_chart'] = $service_chart;

        $data['can'] = [
            'services'  => $services,
            'platform'  => $platform,
            'accounts'  => array_keys(array_filter(array_intersect_key($platform, array_flip(['users', 'agents', 'merchants'])))),
            'any'       => in_array(true, $services, true) || in_array(true, $platform, true) || count($data['pending_actions']) > 0,
        ];

        return $data;
    }

    /**
     * Latest add money list with everything the table shows loaded together (no query per row)
     */
    private function latestAddMoney()
    {
        $transactions = Transaction::with(['user', 'agent', 'merchant', 'user_wallet.currency', 'agent_wallet.currency', 'merchant_wallet.currency', 'currency'])
                            ->where('type', PaymentGatewayConst::TYPEADDMONEY)->latest()->take(5)->get();
        // creator / creator_wallet are picked per row (user, agent or merchant), point them to the loaded relations
        foreach($transactions as $transaction) {
            $transaction->setRelation('creator', $transaction->user ?? $transaction->agent ?? $transaction->merchant);
            $transaction->setRelation('creator_wallet', $transaction->user_wallet ?? $transaction->agent_wallet ?? $transaction->merchant_wallet);
        }
        return $transactions;
    }

    /**
     * All dashboard numbers and chart data (cached by index())
     * @return array
     */
    private function dashboardStats(): array
    {
        // bring the daily summary up to date (only changed days), the dashboard reads the summary, not millions of rows
        DashboardStats::sync();

        $this_month_start   = now()->startOfMonth();
        $last_month_start   = now()->subMonthNoOverflow()->startOfMonth();
        $last_month_end     = now()->subMonthNoOverflow()->endOfMonth();
        $this_month         = [$this_month_start, now()->endOfDay()];
        $last_month         = [$last_month_start, $last_month_end];

        // transaction boxes: ONE grouped query for every box (rows per type + attribute), boxes are built from it in PHP
        $box_rows = $this->boxRows(self::BOX_TYPES, $this_month, $last_month);
        $box = fn(string|array $types, ?string $attribute = PaymentGatewayConst::SEND) => $this->boxFromRows($box_rows, (array) $types, $attribute);

        // service on/off from Module Settings (per account type)
        $module_status = ModuleSetting::pluck('status', 'slug');
        $modules = [];
        foreach(self::SERVICE_MODULES as $service => $slugs) {
            $off = [];
            foreach($slugs as $account => $slug) {
                if(!(bool) ($module_status[$slug] ?? true)) $off[] = __(ucfirst($account));
            }
            $modules[$service] = [
                'enabled'   => count($off) < count($slugs), // on for at least one account type
                'off'       => $off,
            ];
        }
        $enabled = fn(string $service) => $modules[$service]['enabled'] ?? true;

        $money_flow = [
            'add_money'     => $box(PaymentGatewayConst::TYPEADDMONEY, null),
            'money_out'     => $box(PaymentGatewayConst::TYPEMONEYOUT),
            'send_money'    => $box(PaymentGatewayConst::TYPETRANSFERMONEY),
            'make_payment'  => $box(PaymentGatewayConst::TYPEMAKEPAYMENT),
        ];
        $services = [
            'bill_pay'      => $box(PaymentGatewayConst::BILLPAY),
            'mobile_topup'  => $box(PaymentGatewayConst::MOBILETOPUP),
            'remittance'    => $box(PaymentGatewayConst::SENDREMITTANCE),
        ];
        $agents_merchants = [
            'money_in'          => $box(PaymentGatewayConst::MONEYIN),
            'agent_money_out'   => $box(PaymentGatewayConst::AGENTMONEYOUT),
            // payment link + merchant gateway (API) payments, money received by merchants/users (receiver row only)
            'collections'       => $box([PaymentGatewayConst::TYPEPAYLINK, PaymentGatewayConst::MERCHANTPAYMENT], PaymentGatewayConst::RECEIVED),
        ];

        // virtual cards (active provider)
        $cards = activeCardData();
        $card_percent = $cards['virtual_cards'] > 0 ? ($cards['active_cards'] / $cards['virtual_cards']) * 100 : 0;

        // admin profit
        $profit = $this->profitSums($this_month, $last_month); // total, this_month, last_month in one query
        $profit['growth'] = $this->growthPercent($profit['this_month'], $profit['last_month']);

        // accounts: users, agents and merchants in ONE query (UNION ALL)
        $accounts = [];
        foreach($this->accountRows() as $row) {
            $total = (int) $row->total;
            $accounts[$row->account] = [
                'total'         => $total,
                'active'        => (int) $row->active,
                'pending_kyc'   => (int) $row->pending_kyc,
                'kyc_percent'   => $total > 0 ? ((int) $row->kyc_verified / $total) * 100 : 0,
                // chart: active, banned, sms unverified, total
                'chart'         => [(int) $row->active, (int) $row->banned, (int) $row->sms_unverified, $total],
            ];
        }

        // support tickets: one query (same statuses as the model scopes)
        $ticket_row = UserSupportTicket::toBase()->selectRaw('
                            COUNT(*) as total,
                            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as solved
                        ', [SupportTicketConst::ACTIVE, SupportTicketConst::PENDING, SupportTicketConst::DEFAULT, SupportTicketConst::SOLVED])->first();
        $tickets = [
            'total'     => (int) $ticket_row->total,
            'active'    => (int) $ticket_row->active,
            'pending'   => (int) $ticket_row->pending,
            'solved'    => (int) $ticket_row->solved,
        ];
        $tickets['solved_percent'] = $tickets['total'] > 0 ? ($tickets['solved'] / $tickets['total']) * 100 : 0;

        // things waiting for admin
        $pending_actions = [
            ['title' => __("Pending Add Money"),        'count' => $money_flow['add_money']['pending_count'],   'route' => 'admin.add.money.pending',       'service' => 'add_money'],
            ['title' => __("Pending Withdraw"),         'count' => $money_flow['money_out']['pending_count'],   'route' => 'admin.money.out.pending',       'service' => 'money_out'],
            ['title' => __("Pending Remittance"),       'count' => $services['remittance']['pending_count'],    'route' => 'admin.remitance.pending',       'service' => 'remittance'],
            ['title' => __("Pending Bill Pay"),         'count' => $services['bill_pay']['pending_count'],      'route' => 'admin.bill.pay.pending',        'service' => 'bill_pay'],
            ['title' => __("Pending Mobile Topup"),     'count' => $services['mobile_topup']['pending_count'],  'route' => 'admin.mobile.topup.pending',    'service' => 'mobile_topup'],
            ['title' => __("Pending User KYC"),         'count' => $accounts['users']['pending_kyc'],           'route' => 'admin.users.kyc.unverified'],
            ['title' => __("Pending Agent KYC"),        'count' => $accounts['agents']['pending_kyc'],          'route' => 'admin.agents.kyc.unverified'],
            ['title' => __("Pending Merchant KYC"),     'count' => $accounts['merchants']['pending_kyc'],       'route' => 'admin.merchants.kyc.unverified'],
            ['title' => __("Pending Tickets"),          'count' => $tickets['pending'],                         'route' => 'admin.support.ticket.index'],
        ];
        // disabled service: keep the row only while something is still waiting
        $pending_actions = array_values(array_filter($pending_actions, fn($action) => !isset($action['service']) || $enabled($action['service']) || $action['count'] > 0));

        // charts: ONE grouped query for this month (type, attribute, status, day), all charts are built from it in PHP
        $month_day = [];
        foreach(CarbonPeriod::create($this_month_start, now()->endOfMonth()->startOfDay()) as $day) {
            $month_day[] = $day->toDateString();
        }
        $month_rows = DB::table(DashboardStats::TABLE)
                        ->whereIn('type', self::CHART_TYPES)
                        ->whereBetween('day', [$this_month_start->toDateString(), now()->endOfMonth()->toDateString()])
                        ->selectRaw("type, NULLIF(attribute, '') as attribute, status, day, SUM(trx_count) as total_count, SUM(amount) as total_amount")
                        ->groupBy('type', 'attribute', 'status', 'day')
                        ->get()
                        ->map(function($row) { $row->day = (string) $row->day; return $row; });
        // rows of a type (and attribute rule: SEND/RECEIVED row or no attribute; null = all rows)
        $rows_of = fn(string $type, ?string $attribute) => $month_rows->filter(fn($row) => $row->type === $type && ($attribute === null || $row->attribute === null || $row->attribute === $attribute));

        $status_chart = function(string $type) use ($month_day, $rows_of) {
            $by_day_status = [];
            foreach($rows_of($type, null) as $row) {
                $by_day_status[$row->day][$row->status] = ($by_day_status[$row->day][$row->status] ?? 0) + (int) $row->total_count;
            }
            $series = [];
            foreach(['pending_data' => 2, 'success_data' => 1, 'canceled_data' => 4, 'hold_data' => 3] as $key => $status) {
                $series[$key] = array_map(fn($day) => $by_day_status[$day][$status] ?? 0, $month_day);
            }
            return $series;
        };

        // daily successful volume in default currency
        $volume_types = [
            __("Add Money")     => [PaymentGatewayConst::TYPEADDMONEY, null, 'add_money'],
            __("Withdraw")      => [PaymentGatewayConst::TYPEMONEYOUT, PaymentGatewayConst::SEND, 'money_out'],
            __("Send Money")    => [PaymentGatewayConst::TYPETRANSFERMONEY, PaymentGatewayConst::SEND, 'send_money'],
            __("Make Payment")  => [PaymentGatewayConst::TYPEMAKEPAYMENT, PaymentGatewayConst::SEND, 'make_payment'],
        ];
        $volume_chart = [];
        foreach($volume_types as $label => [$type, $attribute, $service]) {
            if(!$enabled($service)) continue;
            $by_day = [];
            foreach($rows_of($type, $attribute) as $row) {
                if((int) $row->status !== PaymentGatewayConst::STATUSSUCCESS) continue;
                $by_day[$row->day] = ($by_day[$row->day] ?? 0) + (float) $row->total_amount;
            }
            $volume_chart[] = [
                'service'   => $service,
                'name'      => $label,
                'data'  => array_map(fn($day) => round($by_day[$day] ?? 0, 2), $month_day),
            ];
        }

        // this month's completed transactions per service (up to today)
        $service_usage = [
            __("Send Money")        => [PaymentGatewayConst::TYPETRANSFERMONEY, PaymentGatewayConst::SEND, 'send_money'],
            __("Make Payment")      => [PaymentGatewayConst::TYPEMAKEPAYMENT, PaymentGatewayConst::SEND, 'make_payment'],
            __("Bill Pay")          => [PaymentGatewayConst::BILLPAY, PaymentGatewayConst::SEND, 'bill_pay'],
            __("Mobile Topup")      => [PaymentGatewayConst::MOBILETOPUP, PaymentGatewayConst::SEND, 'mobile_topup'],
            __("Remittance")        => [PaymentGatewayConst::SENDREMITTANCE, PaymentGatewayConst::SEND, 'remittance'],
            __("Money In")          => [PaymentGatewayConst::MONEYIN, PaymentGatewayConst::SEND, 'money_in'],
            __("Gift Card")         => [PaymentGatewayConst::GIFTCARD, PaymentGatewayConst::SEND, 'gift_card'],
            __("Virtual Card")      => [PaymentGatewayConst::VIRTUALCARD, null, 'virtual_card'],
            __("Payment Link")      => [PaymentGatewayConst::TYPEPAYLINK, PaymentGatewayConst::RECEIVED, 'collections'],
        ];
        $today = now()->toDateString();
        $service_chart = ['labels' => [], 'data' => [], 'services' => []];
        foreach($service_usage as $label => [$type, $attribute, $service]) {
            if(!$enabled($service)) continue;
            $service_chart['services'][] = $service;
            $service_chart['labels'][] = $label;
            $service_chart['data'][] = $rows_of($type, $attribute)
                ->filter(fn($row) => (int) $row->status === PaymentGatewayConst::STATUSSUCCESS && $row->day <= $today)
                ->sum(fn($row) => (int) $row->total_count);
        }

        $data = [
            'money_flow'        => $money_flow,
            'services'          => $services,
            'agents_merchants'  => $agents_merchants,
            'cards'             => $cards + ['percent' => $card_percent],
            'profit'            => $profit,
            'accounts'          => $accounts,
            'tickets'           => $tickets,
            'pending_actions'   => $pending_actions,
            'modules'           => $modules,

            'chart_one_data'    => $status_chart(PaymentGatewayConst::TYPEADDMONEY),
            'chart_three_data'  => $status_chart(PaymentGatewayConst::TYPEMONEYOUT),
            'volume_chart'      => $volume_chart,
            'service_chart'     => $service_chart,
            'month_day'         => $month_day,

        ];

        return $data;
    }

    /**
     * Box numbers for all box types in ONE query on the daily summary, one row per type + attribute:
     * completed total / this month / last month, pending amount + count, completed + rejected counts
     */
    private function boxRows(array $types, array $this_month, array $last_month)
    {
        $success    = PaymentGatewayConst::STATUSSUCCESS;
        $pending    = PaymentGatewayConst::STATUSPENDING;
        $rejected   = PaymentGatewayConst::STATUSREJECTED;

        return DB::table(DashboardStats::TABLE)->whereIn('type', $types)->selectRaw("
                    type, NULLIF(attribute, '') as attribute,
                    SUM(CASE WHEN status = {$success} THEN amount ELSE 0 END) as total,
                    SUM(CASE WHEN status = {$success} AND day BETWEEN ? AND ? THEN amount ELSE 0 END) as this_month,
                    SUM(CASE WHEN status = {$success} AND day BETWEEN ? AND ? THEN amount ELSE 0 END) as last_month,
                    SUM(CASE WHEN status = {$pending} THEN amount ELSE 0 END) as pending,
                    SUM(CASE WHEN status = {$pending} THEN trx_count ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = {$success} THEN trx_count ELSE 0 END) as success_count,
                    SUM(CASE WHEN status = {$rejected} THEN trx_count ELSE 0 END) as rejected_count
                ", [$this_month[0]->toDateString(), $this_month[1]->toDateString(), $last_month[0]->toDateString(), $last_month[1]->toDateString()])
                ->groupBy('type', 'attribute')
                ->get();
    }

    /**
     * One box from the grouped rows (attribute rule: SEND/RECEIVED row or no attribute; null = all rows)
     */
    private function boxFromRows($rows, array $types, ?string $attribute): array
    {
        $box = ['total' => 0.0, 'this_month' => 0.0, 'last_month' => 0.0, 'pending' => 0.0, 'pending_count' => 0];
        $success_count = $rejected_count = 0;
        foreach($rows as $row) {
            if(!in_array($row->type, $types, true)) continue;
            if($attribute !== null && $row->attribute !== null && $row->attribute !== $attribute) continue;
            $box['total']           += (float) $row->total;
            $box['this_month']      += (float) $row->this_month;
            $box['last_month']      += (float) $row->last_month;
            $box['pending']         += (float) $row->pending;
            $box['pending_count']   += (int) $row->pending_count;
            $success_count          += (int) $row->success_count;
            $rejected_count         += (int) $row->rejected_count;
        }
        $finished = $success_count + $rejected_count;
        $box['success_rate'] = $finished > 0 ? ($success_count / $finished) * 100 : 0;
        return $box;
    }

    /**
     * Users, agents and merchants counts in ONE query (UNION ALL), one row per account type
     */
    private function accountRows()
    {
        $select = fn(string $table, string $account) => DB::table($table)->selectRaw("
                        ? as account,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as banned,
                        SUM(CASE WHEN sms_verified = 0 THEN 1 ELSE 0 END) as sms_unverified,
                        SUM(CASE WHEN kyc_verified = ? THEN 1 ELSE 0 END) as kyc_verified,
                        SUM(CASE WHEN kyc_verified = ? THEN 1 ELSE 0 END) as pending_kyc
                    ", [$account, GlobalConst::APPROVED, GlobalConst::PENDING]);

        return $select('users', 'users')
                ->unionAll($select('agents', 'agents'))
                ->unionAll($select('merchants', 'merchants'))
                ->get();
    }

    /**
     * Admin profit in default currency (total, this month, last month) in ONE query on the daily summary
     * (converted like totalAdminProfits(): add money charge with the gateway rate, others with the wallet rate).
     */
    private function profitSums(array $this_month, array $last_month): array
    {
        $success = PaymentGatewayConst::STATUSSUCCESS;
        $row = DB::table(DashboardStats::TABLE)->selectRaw("
                    SUM(CASE WHEN status = {$success} THEN profit ELSE 0 END) as total,
                    SUM(CASE WHEN status = {$success} AND day BETWEEN ? AND ? THEN profit ELSE 0 END) as this_month,
                    SUM(CASE WHEN status = {$success} AND day BETWEEN ? AND ? THEN profit ELSE 0 END) as last_month
                ", [$this_month[0]->toDateString(), $this_month[1]->toDateString(), $last_month[0]->toDateString(), $last_month[1]->toDateString()])
                ->first();

        return [
            'total'         => (float) ($row->total ?? 0),
            'this_month'    => (float) ($row->this_month ?? 0),
            'last_month'    => (float) ($row->last_month ?? 0),
        ];
    }

    /**
     * This month compared to last month for the pie (0-100). No last month data: 100 when this month has data.
     */
    private function growthPercent(float $this_month, float $last_month): float
    {
        if($last_month <= 0) return $this_month > 0 ? 100 : 0;
        return min(100, ($this_month / $last_month) * 100);
    }

    /**
     * Logout Admin From Dashboard
     * @return view
     */
    public function logout(Request $request) {

        // $push_notification_setting = BasicSettingsProvider::get()->push_notification_config;

        // if($push_notification_setting) {
        //     $method = $push_notification_setting->method ?? false;

        //     if($method == "pusher") {
        //         $instant_id     = $push_notification_setting->instance_id ?? false;
        //         $primary_key    = $push_notification_setting->primary_key ?? false;

        //         if($instant_id && $primary_key) {
        //             $pusher_instance = new PushNotifications([
        //                 "instanceId"    => $instant_id,
        //                 "secretKey"     => $primary_key,
        //             ]);

        //             $pusher_instance->deleteUser("".Auth::user()->id."");
        //         }
        //     }

        // }

        $admin = auth()->user();
        try{
            $admin->update([
                'last_logged_out'   => now(),
                'login_status'      => false,
            ]);
        }catch(Exception $e) {
            // Handle Error
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }


    /**
     * Function for clear admin notification
     */
    public function notificationsClear() {
        $admin = auth()->user();
        if(!$admin) {
            return false;
        }
        try{
            $notifications = AdminNotification::auth()->where('clear_at',null)->get();
            foreach( $notifications as $notify){
                $notify->clear_at = now();
                $notify->save();

            }
        }catch(Exception $e) {
            $error = ['error' => [__("Something went wrong! Please try again.")]];
            return Response::error($error,null,404);
        }

        $success = ['success' => [__("Notifications clear successfully!")]];
        return Response::success($success,null,200);
    }
}
