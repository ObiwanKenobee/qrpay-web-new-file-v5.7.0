
@extends('merchant.layouts.master')

@section('breadcrumb')
    @include('merchant.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("merchant.dashboard"),
        ]
    ], 'active' => __("Dashboard")])
@endsection

@section('content')
<div class="body-wrapper">
    @include('user.components.dashboard-overview', ['ov' => $overview])
    {{-- @if (auth()->user()->developerApi && auth()->user()->developerApi->mode == payment_gateway_const()::ENV_SANDBOX) --}}
    @if (count($data['sandbox_fiat_wallets']) > 0 )
        <div class="sbx mt-30">
            <div class="sbx__head">
                <div class="sbx__title">
                    <span class="sbx__icon"><i class="las la-flask"></i></span>
                    <div>
                        <h5 class="mb-0">{{ __("Sandbox Wallet") }} <span class="sbx__badge">{{ __("Test Mode") }}</span></h5>
                        <span class="sbx__desc">{{ __("Test balance for the payment gateway API in sandbox mode. It is not real money.") }}</span>
                    </div>
                </div>
                <a href="{{ setRoute('merchant.developer.api.index') }}" class="sbx__btn"><i class="las la-key"></i> {{ __("API Keys") }}</a>
            </div>
            <div class="sbx__wallets">
                @foreach ($data['sandbox_fiat_wallets'] as $item)
                    <div class="sbx__wallet">
                        <img src="{{ get_image($item->currency->flag, 'currency-flag') }}" alt="{{ $item->currency->code }}">
                        <div class="sbx__wallet-body">
                            <span class="sbx__country">{{ $item->currency->country }}</span>
                            <strong class="sbx__amount">{{ get_amount($item->balance, null, get_wallet_precision($item->currency)) }} <span>{{ $item->currency->code }}</span></strong>
                        </div>
                        <span class="sbx__tag">{{ __("Sandbox") }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @push('css')
        <style>
            .sbx { padding: 20px 22px; border-radius: 20px; border: 1px dashed #c7d2fe; position: relative; overflow: hidden;
                background:
                    repeating-linear-gradient(135deg, rgba(99,102,241,.035) 0 12px, transparent 12px 24px),
                    linear-gradient(120deg, #f8faff, #fdfcff); }
            .sbx__head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
            .sbx__title { display: flex; align-items: center; gap: 12px; }
            .sbx__icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
                font-size: 22px; color: #4f46e5; background: #e0e7ff; flex-shrink: 0; }
            .sbx__badge { display: inline-block; margin-left: 6px; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 700;
                color: #b45309; background: #fef3c7; vertical-align: middle; }
            .sbx__desc { display: block; font-size: 13px; color: #6b7280; margin-top: 2px; }
            .sbx__btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
                color: #4f46e5; background: #fff; border: 1px solid #c7d2fe; transition: background .2s, color .2s; }
            .sbx__btn:hover { background: #4f46e5; color: #fff; }
            .sbx__wallets { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; margin-top: 16px; }
            .sbx__wallet { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 14px; background: #fff;
                border: 1px solid #e5e7eb; box-shadow: 0 4px 14px rgba(17,24,39,.04); position: relative; }
            .sbx__wallet img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
            .sbx__wallet-body { min-width: 0; }
            .sbx__country { display: block; font-size: 12px; color: #6b7280; }
            .sbx__amount { display: block; font-size: 20px; font-weight: 800; color: #111827; word-break: break-word; }
            .sbx__amount span { font-size: 13px; font-weight: 700; color: #4f46e5; }
            .sbx__tag { position: absolute; top: 10px; right: 10px; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
                color: #6366f1; background: #eef2ff; padding: 2px 7px; border-radius: 6px; }
            @media (max-width: 575px) { .sbx { padding: 16px; } .sbx__btn { width: 100%; justify-content: center; } }
        </style>
        @endpush
    @endif
    {{-- @endif --}}
    <div class="chart-area mt-30">
        <div class="row mb-20-none">
            <div class="col-xxl-12 col-xl-12 col-lg-12 mb-20">
                <div class="chart-wrapper">
                    <div class="dashboard-header-wrapper">
                        <h4 class="title">{{ __("Withdraw Money Chart") }}</h4>
                    </div>
                    <div class="chart-container">
                        <div id="chart1"  data-chart_one_data="{{ json_encode($chartData['chart_one_data']) }}" data-month_day="{{ json_encode($chartData['month_day']) }}" class="chart"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div class="dashboard-list-area mt-20">
        <div class="dashboard-header-wrapper">
            <h4 class="title">{{ __("Latest Transactions") }}</h4>
            <div class="dashboard-btn-wrapper">
                <div class="dashboard-btn mb-2">
                    <a href="{{ setRoute('merchant.transactions.index') }}" class="btn--base">{{__("View More")}}</a>
                </div>
            </div>
        </div>
        <div class="dashboard-list-wrapper">
            @include('merchant.components.transaction-log',compact("transactions"))
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    var chart1 = $('#chart1');
    var chart_one_data = chart1.data('chart_one_data');
    var month_day = chart1.data('month_day');
    var options = {
        series: [
            {
            name: "{{ __('Pending') }}",
            color: "#0C56DB",
            data: chart_one_data.pending_data
            }, {
            name: "{{ __('Completed') }}",
            color: "rgba(0, 227, 150, 0.85)",
            data: chart_one_data.success_data
            }, {
            name: "{{ __('Canceled') }}",
            color: "#dc3545",
            data: chart_one_data.canceled_data
            }, {
            name: "{{ __('Hold') }}",
            color: "#ded7e9",
            data: chart_one_data.hold_data
            }
        ],
        chart: {
            height: 350,
            type: "area",
            toolbar: {
                show: false,
            },
        },
        dataLabels: {
            enabled: false,
        },
        stroke: {
            curve: "smooth",
        },
        xaxis: {
            type: "datetime",
            categories:month_day,
        },
        tooltip: {
            x: {
                format: "dd/MM/yy HH:mm",
            },
        },
    };

    var chart = new ApexCharts(document.querySelector("#chart1"), options);
    chart.render();


</script>
@endpush
