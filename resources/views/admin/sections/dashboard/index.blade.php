@extends('admin.layouts.master')

@push('css')

@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Dashboard")])
@endsection

@section('content')
@php
    $symbol = get_default_currency_symbol();
    // badges: 2 decimals below 1K, K/M notation above (formatNumberInKNotation() takes int)
    $money = fn($amount) => $symbol.(abs($amount) < 1000 ? number_format($amount, 2) : formatNumberInKNotation((int) round($amount)));
    // transaction box: completed total, this month + pending (or last month), success rate circle
    $modules = $data['modules'];
    $can = $data['can'];
    $txBox = function(string $service, string $title, array $item, string $color, ?string $route = null, bool $show_pending = true) use ($money, $symbol, $modules, $can) {
        return [
            'enabled'   => ($modules[$service]['enabled'] ?? true) && $can['services'][$service],
            'off'       => $modules[$service]['off'] ?? [],
            'title'     => $title,
            'value'     => $symbol.get_amount($item['total'], null, get_wallet_precision()),
            'badges'    => array_values(array_filter([
                [__("This Month")." ".$money($item['this_month']), 'badge--info'],
                $show_pending
                    ? [__("Pending")." ".$money($item['pending'])." (".$item['pending_count'].")", 'badge--warning']
                    : [__("Last Month")." ".$money($item['last_month']), 'badge--warning'],
            ])),
            'percent'   => $item['success_rate'],
            'caption'   => __("Success Rate"),
            'color'     => $color,
            'route'     => $route,
        ];
    };
    // up to 4 boxes per row (2 on tablet), rows balanced so every row is filled (e.g. 3 => 3, 5 => 3 + 2, 7 => 4 + 3)
    $colFor = function(int $index, int $count) {
        $width = function(int $per_row) use ($index, $count) {
            $rows  = (int) ceil($count / $per_row);
            $base  = intdiv($count, $rows);
            $extra = $count % $rows;                         // the first $extra rows get one box more
            $row_size = $index < $extra * ($base + 1) ? $base + 1 : $base;
            return 12 / $row_size;
        };
        $xl = $width(4);
        $lg = $width(2);
        // no col-xxxl-*: the theme turns those into 25% on wide screens
        return "col-xxl-{$xl} col-xl-{$xl} col-lg-{$lg} col-md-{$lg} col-sm-12";
    };
    $f = $data['money_flow']; $s = $data['services']; $am = $data['agents_merchants'];
@endphp
@if (!$can['any'])
    {{-- admin without permission for any dashboard part --}}
    <div class="dashboard-area">
        <div class="dashboard-welcome text-center">
            <div class="dashboard-welcome-icon"><i class="las la-user-shield"></i></div>
            <h3 class="title">{{ __("Welcome") }}, {{ auth()->user()->fullname }}</h3>
            <p>{{ __("You don't have permission to view dashboard statistics.") }}</p>
            <p>{{ __("Use the menu to open the sections you can manage, or contact the Super Admin for access.") }}</p>
        </div>
    </div>
@else
    <div class="dashboard-area">
        <div class="dashboard-item-area">
@php
    $sections = [
        __("Money Flow") => [
            $txBox('add_money', __("Add Money"), $f['add_money'], '#10c469', 'admin.add.money.index'),
            $txBox('money_out', __("Withdraw Money"), $f['money_out'], '#ff9f43', 'admin.money.out.index'),
            $txBox('send_money', __("Send Money"), $f['send_money'], '#8b7fd6', 'admin.send.money.index', false),
            $txBox('make_payment', __("Make Payment"), $f['make_payment'], '#3b82f6', 'admin.make.payment.index', false),
        ],
        __("Services") => [
            $txBox('bill_pay', __("Bill Pay"), $s['bill_pay'], '#0ea5e9', 'admin.bill.pay.index'),
            $txBox('mobile_topup', __("Mobile Topup"), $s['mobile_topup'], '#8b5cf6', 'admin.mobile.topup.index'),
            $txBox('remittance', __("Remittance"), $s['remittance'], '#f59e0b', 'admin.remitance.index'),
        ],
        __("Agents & Merchants") => [
            $txBox('money_in', __("Money In"), $am['money_in'], '#22c55e', 'admin.money.in.index', false),
            $txBox('agent_money_out', __("Agent Money Out"), $am['agent_money_out'], '#ef4444', 'admin.agent.money.out.index', false),
            $txBox('collections', __("Merchant Collections"), $am['collections'], '#6366f1', 'admin.payment.link.index', false),
            [
                'enabled'   => ($modules['virtual_card']['enabled'] ?? true) && $can['services']['virtual_card'],
                'title'     => __("Total Virtual Cards"),
                'value'     => $data['cards']['virtual_cards'],
                'badges'    => [[__("active")." ".$data['cards']['active_cards'], 'badge--info'], [__("Inactive")." ".$data['cards']['inactive_cards'], 'badge--warning']],
                'percent'   => $data['cards']['percent'],
                'caption'   => __("active"),
                'color'     => '#a192d9',
            ],
        ],
    ];
@endphp
            {{-- services turned off in Module Settings for every account type, or without admin permission, are hidden --}}
            {{-- the first visible section title sits on the banner --}}
            @php $has_boxes = false; @endphp
            @foreach ($sections as $section_title => $boxes)
                @php $boxes = array_values(array_filter($boxes, fn($box) => $box['enabled'])); @endphp
                @if (count($boxes))
                    <h6 class="dashboard-section-title @if(!$has_boxes) on-banner @endif">{{ $section_title }}</h6>
                    <div class="row">
                        @foreach ($boxes as $box)
                            @include('admin.sections.dashboard.stat-box', $box + ['col' => $colFor($loop->index, count($boxes))])
                        @endforeach
                    </div>
                    @php $has_boxes = true; @endphp
                @endif
            @endforeach

            @if (in_array(true, $can['platform'], true))
            <h6 class="dashboard-section-title @if(!$has_boxes) on-banner @endif">{{ __("Platform") }}</h6>
            @php
                $platform_boxes = [];
                if ($can['platform']['profit']) $platform_boxes[] = [
                    'title'     => __("Admin Profits"),
                    'value'     => $symbol.get_amount($data['profit']['total'], null, get_wallet_precision()),
                    'badges'    => [[__("This Month")." ".$money($data['profit']['this_month']), 'badge--info'], [__("Last Month")." ".$money($data['profit']['last_month']), 'badge--warning']],
                    'percent'   => $data['profit']['growth'],
                    'caption'   => __("vs Last Month"),
                    'color'     => '#f05050',
                    'route'     => 'admin.profit.logs.index',
                ];
                foreach ([
                    'users'     => [__("Total Users"), '#10c469', 'admin.users.index'],
                    'agents'    => [__("Total Agents"), '#ff9f43', 'admin.agents.index'],
                    'merchants' => [__("Total Merchants"), '#8b7fd6', 'admin.merchants.index'],
                ] as $key => [$title, $color, $route]) {
                    if (!$can['platform'][$key]) continue;
                    $platform_boxes[] = [
                        'title'     => $title,
                        'value'     => $data['accounts'][$key]['total'],
                        'badges'    => [[__("active")." ".$data['accounts'][$key]['active'], 'badge--info'], [__("Pending KYC")." ".$data['accounts'][$key]['pending_kyc'], 'badge--warning']],
                        'percent'   => $data['accounts'][$key]['kyc_percent'],
                        'caption'   => __("KYC Verified"),
                        'color'     => $color,
                        'route'     => $route,
                    ];
                }
                if ($can['platform']['tickets']) $platform_boxes[] = [
                    'title'     => __("Total Supports Tickets"),
                    'value'     => $data['tickets']['total'],
                    'badges'    => [[__("active")." ".$data['tickets']['active'], 'badge--info'], [__("Pending")." ".$data['tickets']['pending'], 'badge--warning']],
                    'percent'   => $data['tickets']['solved_percent'],
                    'caption'   => __("Solved"),
                    'color'     => '#0ea5e9',
                    'route'     => 'admin.support.ticket.index',
                ];
            @endphp
            <div class="row">
                @foreach ($platform_boxes as $box)
                    @include('admin.sections.dashboard.stat-box', $box + ['col' => $colFor($loop->index, count($platform_boxes))])
                @endforeach
            </div>
            @endif
        </div>
    </div>

    @php
        $show_volume  = count($data['volume_chart']) > 0;
        $show_pending = count($data['pending_actions']) > 0;
        $account_keys = $can['accounts'];
    @endphp
    <div class="chart-area mt-15">
        <div class="row">
            @if ($show_volume)
            <div class="{{ $show_pending ? 'col-xxl-8 col-xl-8' : 'col-xxl-12 col-xl-12' }} col-lg-12 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Monthly Transaction Volume") }} ({{ get_default_currency_code() }})</h5>
                    </div>
                    <div class="chart-container">
                        <div id="volume-chart" data-series="{{ json_encode($data['volume_chart']) }}" data-month_day="{{ json_encode($data['month_day']) }}"></div>
                    </div>
                </div>
            </div>
            @endif
            @if ($show_pending)
            <div class="{{ $show_volume ? 'col-xxl-4 col-xl-4' : 'col-xxl-12 col-xl-12' }} col-lg-12 mb-15">
                <div class="chart-wrapper h-100">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Pending Actions") }}</h5>
                    </div>
                    <ul class="dashboard-pending-list">
                        @foreach ($data['pending_actions'] as $action)
                            <li>
                                <span>{{ $action['title'] }}</span>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="badge {{ $action['count'] > 0 ? 'badge--warning' : 'badge--success' }}">{{ $action['count'] }}</span>
                                    @if (admin_permission_by_name($action['route']))
                                        <a href="{{ setRoute($action['route']) }}" class="text--base"><i class="las la-arrow-right"></i></a>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
            @if ($can['services']['add_money'])
            <div class="col-xxl-6 col-xl-6 col-lg-6 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Monthly Add Money Chart") }}</h5>
                    </div>
                    <div class="chart-container">
                        <div id="chart1" data-chart_one_data="{{ json_encode($data['chart_one_data']) }}" data-month_day="{{ json_encode($data['month_day']) }}" class="sales-chart"></div>
                    </div>
                </div>
            </div>
            @endif
            @if ($can['services']['money_out'])
            <div class="col-xxl-6 col-xl-6 col-lg-6 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Monthly Withdraw Chart") }}</h5>
                    </div>
                    <div class="chart-container">
                        <div id="chart3" data-chart_three_data="{{ json_encode($data['chart_three_data']) }}" data-month_day="{{ json_encode($data['month_day']) }}" class="sales-chart"></div>
                    </div>
                </div>
            </div>
            @endif
            @if (count($data['service_chart']['labels']))
            <div class="col-xxl-6 col-xl-6 col-lg-12 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Service Usage (This Month)") }}</h5>
                    </div>
                    <div class="chart-container">
                        <div id="service-chart" data-labels="{{ json_encode($data['service_chart']['labels']) }}" data-values="{{ json_encode($data['service_chart']['data']) }}"></div>
                    </div>
                </div>
            </div>
            @endif
            @if (count($account_keys))
            <div class="col-xxl-6 col-xl-6 col-lg-12 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Accounts Overview") }}</h5>
                    </div>
                    <div class="chart-container">
                        @php
                            $account_series = [__("active") => 0, __("banned") => 1, __("Unverified") => 2];
                            $account_titles = ['users' => __('Users'), 'agents' => __('Agents'), 'merchants' => __('Merchants')];
                        @endphp
                        <div id="accounts-chart" data-categories="{{ json_encode(array_map(fn($key) => $account_titles[$key], $account_keys)) }}" data-series="{{ json_encode(array_merge(
                            collect($account_series)->map(fn($index, $name) => ['name' => $name, 'data' => array_map(fn($key) => $data['accounts'][$key]['chart'][$index], $account_keys)])->values()->all(),
                            [['name' => __('Pending KYC'), 'data' => array_map(fn($key) => $data['accounts'][$key]['pending_kyc'], $account_keys)]]
                        )) }}"></div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @if ($can['services']['add_money'])
    <div class="table-area  mt-15">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Latest Add Money") }}</h5>
                <a href="{{ setRoute('admin.add.money.index') }}" class="btn--base">{{__("Add Money")}}</a>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("web_trx_id") }}</th>
                            <th>{{ __("Full Name") }}</th>
                            <th>{{ __("User Type") }}</th>
                            <th>{{ __("Phone") }}</th>
                            <th>{{ __("Amount") }}</th>
                            <th>{{ __("Method") }}</th>
                            <th>{{ __(("Status")) }}</th>
                            <th>{{ __("Time") }}</th>
                            <th>{{__("action")}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['transactions'] ??[]  as $key => $item)

                        <tr>
                            <td>{{ $item->trx_id }}</td>
                            <td>
                                @if($item->user_id != null)
                                <a href="{{ setRoute('admin.users.details',$item->creator->username) }}">{{ $item->creator->fullname }}</a>
                                @elseif($item->agent_id != null)
                                <a href="{{ setRoute('admin.agents.details',$item->creator->username) }}">{{ $item->creator->fullname }}</a>
                                @endif

                            <td>
                                @if($item->user_id != null)
                                     {{ __("USER") }}
                                @elseif($item->agent_id != null)
                                     {{ __("AGENT") }}
                                @elseif($item->merchant_id != null)
                                     {{ __("MERCHANT") }}
                                @endif

                            </td>
                            <td>
                                {{ $item->creator->full_mobile ?? '' }}
                            </td>

                            <td>{{get_amount($item->request_amount,$item->creator_wallet->currency->code,get_wallet_precision($item->creator_wallet->currency))}}</td>
                            <td><span class="text--info">{{ @$item->currency->name }}</span></td>
                            <td>
                                <span class="{{ $item->stringStatus->class }}">{{ __($item->stringStatus->value) }}</span>
                            </td>
                            <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>
                            <td>
                                @include('admin.components.link.info-default',[
                                    'href'          => setRoute('admin.add.money.details', $item->id),
                                    'permission'    => "admin.add.money.details",
                                ])

                            </td>
                        </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 9])
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
    @endif
@endif
@endsection

@push('css')
<style>
    .dashboard-section-title { margin: 6px 0 12px; font-size: 13px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; opacity: .7; }
    .dashboard-section-title.on-banner { color: #ffffff; opacity: .9; }
    .dashboard-welcome { position: relative; z-index: 1; max-width: 640px; margin: 40px auto; padding: 48px 32px; background: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
    .dashboard-welcome-icon { width: 84px; height: 84px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(90,82,120,.1); color: #5a5278; font-size: 44px; }
    .dashboard-welcome .title { margin-bottom: 12px; }
    .dashboard-welcome p { margin-bottom: 6px; opacity: .8; }
    .dashboard-pie-caption { font-size: 11px; opacity: .75; white-space: nowrap; }
    .dashboard-pending-list { list-style: none; margin: 0; padding: 0; }
    .dashboard-pending-list li { display: flex; justify-content: space-between; align-items: center; padding: 11px 2px; border-bottom: 1px solid rgba(128,128,128,.15); font-size: 14px; }
    .dashboard-pending-list li:last-child { border-bottom: 0; }
    /* box title link + circle text: readable in light and dark mode */
    .dashboard-content .left .title .dashboard-box-link { color: inherit; }
    .dashboard-content .left .title .dashboard-box-link:hover { color: var(--pie-color, #8b7fd6); text-decoration: underline; }
    .dashbord-item .chart.dashboard-pie span { color: var(--pie-color); }
    .dark-version .dashboard-content .left .title { color: #ffffff; }
    .dark-version .dashboard-content .left .title .dashboard-box-link:hover { color: #ffffff; opacity: .8; }
    .dark-version .dashbord-item .chart.dashboard-pie span { color: #ffffff; }
    .dark-version .dashboard-pie-caption { color: #c9cdd6; opacity: 1; }
    .dark-version .dashboard-section-title { color: #c9cdd6; opacity: 1; }
    .dark-version .dashboard-section-title.on-banner { color: #ffffff; }
    .dark-version .dashboard-welcome { background: #313844; box-shadow: none; }
    .dark-version .dashboard-welcome .title, .dark-version .dashboard-welcome p { color: #ffffff; }
    .dark-version .dashboard-welcome-icon { background: rgba(139, 127, 214, .2); color: #b9b0f0; }
    .dark-version .dashboard-pending-list li { color: #ffffff; border-bottom-color: rgba(255, 255, 255, .1); }
</style>
@endpush

@push('script')
<script>
// status bar chart (Pending / Completed / Canceled / Hold)
// charts the admin has no permission for are not on the page
function statusChart(selector, data, month_day) {
    if (!document.querySelector(selector)) return;
    new ApexCharts(document.querySelector(selector), {
        series: [
            {name: "{{ __('Pending') }}",   color: "#ff9f43", data: data.pending_data},
            {name: "{{ __('Completed') }}", color: "#10c469", data: data.success_data},
            {name: '{{ __("Canceled") }}',  color: "#f05050", data: data.canceled_data},
            {name: '{{ __("Hold") }}',      color: "#a192d9", data: data.hold_data},
        ],
        chart: {type: 'bar', height: 350, stacked: true, toolbar: {show: false}, zoom: {enabled: true}},
        plotOptions: {bar: {horizontal: false, borderRadius: 10}},
        xaxis: {type: 'datetime', categories: month_day},
        legend: {position: 'bottom', offsetX: 40},
        fill: {opacity: 1},
    }).render();
}
statusChart("#chart1", $('#chart1').data('chart_one_data'), $('#chart1').data('month_day'));
statusChart("#chart3", $('#chart3').data('chart_three_data'), $('#chart3').data('month_day'));

// daily completed volume in default currency
if (document.querySelector("#volume-chart")) new ApexCharts(document.querySelector("#volume-chart"), {
    series: $('#volume-chart').data('series'),
    colors: ['#10c469', '#ff9f43', '#8b7fd6', '#3b82f6'],
    chart: {type: 'bar', height: 350, toolbar: {show: false}, zoom: {enabled: false}},
    plotOptions: {bar: {horizontal: false, columnWidth: '70%', borderRadius: 3}},
    dataLabels: {enabled: false},
    xaxis: {type: 'datetime', categories: $('#volume-chart').data('month_day')},
    yaxis: {labels: {formatter: (value) => "{{ get_default_currency_symbol() }}" + Number(value).toLocaleString()}},
    legend: {position: 'bottom'},
}).render();

// completed transactions per service this month
if (document.querySelector("#service-chart")) new ApexCharts(document.querySelector("#service-chart"), {
    series: [{name: "{{ __('Transactions') }}", data: $('#service-chart').data('values')}],
    colors: ['#8b7fd6'],
    chart: {type: 'bar', height: 350, toolbar: {show: false}},
    plotOptions: {bar: {horizontal: true, borderRadius: 6, barHeight: '60%'}},
    dataLabels: {enabled: true},
    xaxis: {categories: $('#service-chart').data('labels'), tickAmount: Math.max(1, Math.min(5, Math.max(...$('#service-chart').data('values')))), labels: {formatter: (value) => Math.round(value)}},
}).render();

// users / agents / merchants: active, banned, unverified, pending KYC
if (document.querySelector("#accounts-chart")) {
var accountMax = Math.max(1, ...$('#accounts-chart').data('series').flatMap(series => series.data));
new ApexCharts(document.querySelector("#accounts-chart"), {
    series: $('#accounts-chart').data('series'),
    colors: ['#10c469', '#f03d30', '#ff9f43', '#8b7fd6'],
    chart: {type: 'bar', height: 350, toolbar: {show: false}},
    plotOptions: {bar: {horizontal: false, columnWidth: '55%', borderRadius: 4}},
    dataLabels: {enabled: true},
    xaxis: {categories: $('#accounts-chart').data('categories')},
    yaxis: {tickAmount: Math.min(5, accountMax), labels: {formatter: (value) => Math.round(value)}},
    legend: {position: 'bottom'},
}).render();
}

// stat box circles
$(function() {
    $('.dashboard-pie').each(function() {
        var color = $(this).data('color');
        $(this).easyPieChart({size: 80, barColor: color, scaleColor: false, lineWidth: 5, trackColor: color + ($('body').hasClass('dark-version') ? '40' : '33'), lineCap: 'circle', animate: 3000});
    });
});
</script>
@endpush
