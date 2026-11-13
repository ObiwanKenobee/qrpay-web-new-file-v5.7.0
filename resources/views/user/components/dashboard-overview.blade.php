{{--
    Shared dashboard overview (user / agent / merchant).
    $ov = [
        'balance' => float, 'currency' => Currency (default), 'quick' => [[label, icon, tone, route], ...],
        'month' => [total, success, pending, rejected], 'pending_total' => int, 'pending_route' => route name,
        'features' => [[label, icon, tone, route, amount|number, count?, badge?], ...],
    ]
--}}
@push('css')
<style>
    .db-hero { height: 100%; padding: 22px; border-radius: 22px; color: #1f2937; position: relative; overflow: hidden;
        background:
            radial-gradient(circle at 100% 0%, rgba(99, 102, 241, .10) 0, transparent 45%),
            radial-gradient(circle at 0% 100%, rgba(99, 102, 241, .08) 0, transparent 40%),
            #fff;
        border: 1px solid #e7e9f0; box-shadow: 0 10px 30px rgba(17, 24, 39, .06); }
    .db-hero::after { content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image: radial-gradient(rgba(17, 24, 39, .06) 1px, transparent 1px); background-size: 18px 18px;
        -webkit-mask-image: linear-gradient(135deg, transparent 40%, #000 100%); mask-image: linear-gradient(135deg, transparent 40%, #000 100%); }
    .db-hero > * { position: relative; z-index: 1; }
    .db-hero__top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .db-hero__balance { padding: 16px 18px; border-radius: 16px;
        background: linear-gradient(120deg, #eef2ff 0%, #f5f3ff 45%, #ecfeff 100%);
        border: 1px solid #e0e7ff; }
    .db-hero__label { font-size: 13px; font-weight: 600; letter-spacing: .02em; color: #6b7280; display: inline-flex; align-items: center; gap: 6px; }
    .db-hero__label::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 0 4px rgba(22, 163, 74, .15); }
    .db-hero__amount { color: #111827; margin: 6px 0 0; font-size: 34px; font-weight: 800; letter-spacing: -.01em; word-break: break-word; }
    .db-hero__flag { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 4px 14px rgba(0,0,0,.12); }
    .db-hero__amount .db-hero__unit { font-size: 17px; font-weight: 600; color: #6b7280; }
    .db-balance { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .db-balance__value { display: none; }
    .db-balance__mask { letter-spacing: 4px; }
    .db-balance.is-shown .db-balance__value { display: inline; }
    .db-balance.is-shown .db-balance__mask { display: none; }
    .db-balance__toggle {
        display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; margin-top: 6px;
        border: 1px solid color-mix(in srgb, var(--primary-color) 15%, #fff); border-radius: 20px;
        background: #fff; color: var(--primary-color); font-size: 18px; transition: background .2s, color .2s;
    }
    .db-balance__toggle:hover { background: var(--primary-color); color: #fff; }
    .db-balance__timer { font-size: 12px; font-weight: 700; }
    .db-balance__timer:empty { display: none; }
    .db-quick-title { display: block; margin: 18px 0 0; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #9ca3af; }
    .db-actions { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-top: 10px; }
    .db-actions a { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 6px 12px; border-radius: 14px;
        background: #fff; border: 1px solid #eef0f4; color: #374151; font-size: 12.5px; font-weight: 600; text-align: center; line-height: 1.25;
        transition: transform .2s, box-shadow .2s, border-color .2s; }
    .db-actions__icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 21px;
        color: var(--tone); background: color-mix(in srgb, var(--tone) 12%, #fff); transition: transform .2s, background .2s, color .2s; }
    .db-actions a:hover { color: #111827; transform: translateY(-3px); border-color: color-mix(in srgb, var(--tone) 30%, #fff);
        box-shadow: 0 10px 22px color-mix(in srgb, var(--tone) 18%, transparent); }
    .db-actions a:hover .db-actions__icon { background: var(--tone); color: #fff; transform: scale(1.05); }
    .db-panel { padding: 22px; border-radius: 20px; background: #EBEDEF; border: 1px solid #ded7e9; }
    .db-panel__head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 14px; }
    .db-panel__sub { font-size: 13px; opacity: .65; }
    .db-month { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .db-month__item { padding: 12px 14px; border-radius: 12px; background: #fff; border-left: 4px solid var(--primary-color); }
    .db-month__item span { display: block; font-size: 13px; opacity: .7; }
    .db-month__item strong { font-size: 22px; }
    .db-month__item.is-green { border-left-color: #16a34a; }
    .db-month__item.is-yellow { border-left-color: #f5a623; }
    .db-month__item.is-red { border-left-color: #e5484d; }
    .db-rate { margin-top: 14px; }
    .db-rate__head { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
    .db-rate__track { height: 8px; border-radius: 20px; background: rgba(0,0,0,.08); overflow: hidden; }
    .db-rate__track div { height: 100%; background: #16a34a; border-radius: 20px; }
    .db-pending { display: flex; align-items: center; gap: 8px; margin-top: 14px; padding: 10px 14px; border-radius: 12px;
        background: rgba(245,166,35,.12); color: #b7791f; font-size: 13px; font-weight: 600; }
    .db-pending:hover { color: #b7791f; background: rgba(245,166,35,.2); }
    .db-stat { display: flex; align-items: center; gap: 14px; padding: 18px; border-radius: 16px; background: #EBEDEF; border: 1px solid #ded7e9; height: 100%; transition: transform .2s, box-shadow .2s; }
    .db-stat, .db-stat:hover { color: inherit; }
    .db-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.06); }
    .db-stat__icon { width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 24px;
        color: var(--tone); background: color-mix(in srgb, var(--tone) 14%, #fff); }
    .db-stat__label { display: block; font-size: 13px; opacity: .7; }
    .db-stat__value { margin: 2px 0 0; font-size: 20px; font-weight: 700; word-break: break-word; }
    .db-stat__value span { font-size: 13px; opacity: .7; font-weight: 600; }
    .db-stat__hint { display: inline-block; margin-top: 4px; padding: 1px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; color: var(--tone); background: color-mix(in srgb, var(--tone) 12%, transparent); }
    .tone-green { --tone: #16a34a; } .tone-blue { --tone: #2563eb; } .tone-purple { --tone: #7c3aed; } .tone-orange { --tone: #ea580c; }
    .tone-teal { --tone: #0d9488; } .tone-red { --tone: #dc2626; } .tone-pink { --tone: #db2777; }
    @media (max-width: 1399px) { .db-actions { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (max-width: 575px) {
        .db-hero { padding: 18px; } .db-hero__amount { font-size: 26px; }
        .db-actions { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    /* themed: resting look stays colorful; the site base color (--primary-color) is used for actions / interaction states */
    .db-themed { --pc: var(--primary-color); }
    /* eye button: small round icon button, pill with countdown while the balance is shown */
    .db-balance { align-items: center; margin-top: 6px; }
    .db-balance .db-hero__amount { margin: 0; line-height: 1.1; }
    .db-balance__mask { font-size: 26px; letter-spacing: 3px; position: relative; top: -2px; }
    .db-themed .db-balance__toggle { width: 36px; height: 36px; padding: 0; margin: 0; justify-content: center; border-radius: 50%;
        background: #fff; border: 1px solid #e5e7eb; color: var(--pc); font-size: 18px; box-shadow: 0 2px 6px rgba(17, 24, 39, .06);
        transition: background .2s, color .2s, border-color .2s, width .2s; }
    .db-themed .db-balance__toggle:hover { background: var(--pc); border-color: var(--pc); color: #fff; }
    .db-themed .db-balance.is-shown .db-balance__toggle { width: auto; padding: 0 12px; border-radius: 20px; gap: 6px; }
    .db-themed .db-actions a:hover { border-color: var(--pc); box-shadow: 0 10px 22px color-mix(in srgb, var(--pc) 14%, transparent); }
    .db-themed .db-actions a:hover .db-actions__icon { background: var(--pc); color: #fff; }
    .db-themed .db-actions a:focus-visible { outline: 2px solid var(--pc); outline-offset: 2px; }
    .db-themed .db-stat:hover { border-color: var(--pc); }
    .db-themed .db-month__item:first-child { border-left-color: var(--pc); }
    .db-themed .db-pending:hover { box-shadow: inset 0 0 0 1px var(--pc); }
    /* balance card: vivid gradient card with white text (light page, colorful at rest) */
    .db-hero__balance { position: relative; overflow: hidden; padding: 22px 24px; border: 0; border-radius: 18px; color: #fff;
        background: linear-gradient(120deg, #4338ca 0%, #5b5bd6 55%, #7c6cf0 100%); box-shadow: 0 12px 26px -16px rgba(67, 56, 202, .55); }
    /* agent / merchant: balance card made from the panel's own base color (user panel keeps the indigo card) */
    .db-panel-agent .db-hero__balance, .db-panel-merchant .db-hero__balance {
        background: linear-gradient(120deg, color-mix(in srgb, var(--primary-color) 82%, #000) 0%, var(--primary-color) 55%, color-mix(in srgb, var(--primary-color) 72%, #fff) 100%);
        box-shadow: 0 12px 26px -16px color-mix(in srgb, var(--primary-color) 70%, transparent); }
    /* plain container: no dotted texture / glow around the balance card */
    .db-hero { background: #fff; box-shadow: 0 6px 20px rgba(17, 24, 39, .05); }
    .db-hero::after { display: none; }
    .db-hero__balance::before, .db-hero__balance::after { content: ""; position: absolute; border-radius: 50%; pointer-events: none; }
    .db-hero__balance::before { width: 220px; height: 220px; right: -70px; top: -110px; background: rgba(255, 255, 255, .10); }
    .db-hero__balance::after { width: 160px; height: 160px; right: 110px; bottom: -110px; background: rgba(255, 255, 255, .08); }
    .db-hero__balance > * { position: relative; z-index: 1; }
    .db-hero__balance .db-hero__label { color: rgba(255, 255, 255, .85); }
    .db-hero__balance .db-hero__label::before { background: #4ade80; box-shadow: 0 0 0 4px rgba(74, 222, 128, .25); }
    .db-hero__balance .db-hero__amount, .db-hero__balance .db-hero__unit { color: #fff; }
    .db-hero__balance .db-hero__unit { opacity: .8; }
    .db-hero__balance .db-hero__flag { border-color: rgba(255, 255, 255, .7); box-shadow: 0 6px 16px rgba(0, 0, 0, .25); }
    .db-hero .db-hero__balance .db-balance__toggle { background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .35); color: #fff; box-shadow: none; }
    .db-hero .db-hero__balance .db-balance__toggle:hover { background: #fff; border-color: #fff; color: var(--primary-color); }
    /* quick access */
    .db-quick-title { display: flex; align-items: center; gap: 10px; color: #6b7280; }
    .db-quick-title::after { content: ""; flex: 1 1 auto; height: 1px; background: linear-gradient(90deg, #e5e7eb, transparent); }
    .db-actions a { border-color: #eef0f4; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .db-actions__icon { width: 44px; height: 44px; border-radius: 13px;
        background: linear-gradient(135deg, color-mix(in srgb, var(--tone) 10%, #fff), color-mix(in srgb, var(--tone) 22%, #fff)); }
    .db-actions a:hover { box-shadow: 0 12px 24px -10px color-mix(in srgb, var(--tone) 45%, transparent); }
</style>
@endpush
@php
    // which panel shows the overview (agent / merchant balance card uses that panel's base color)
    $db_panel = request()->is('agent', 'agent/*') ? 'agent' : (request()->is('merchant', 'merchant/*') ? 'merchant' : 'user');
@endphp
<div class="dashboard-area mt-10 db-panel-{{ $db_panel }} {{ !empty($ov['themed']) ? 'db-themed' : '' }}">
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{ __("Overview") }}</h3>
        </div>
        @php
            $wp   = get_wallet_precision();
            $base = @$ov['currency']->code;
            $month = $ov['month'];
            $success_rate = $month['total'] > 0 ? round($month['success'] / $month['total'] * 100) : 0;
        @endphp

        <div class="row mb-20-none">
            {{-- balance hero --}}
            <div class="col-xl-7 mb-20">
                <div class="db-hero">
                    <div class="db-hero__top db-hero__balance">
                        <div>
                            <span class="db-hero__label">{{ __("Total Balance") }}</span>
                            <div class="db-balance" data-seconds="10">
                                <h2 class="db-hero__amount">
                                    <span class="db-balance__value" aria-hidden="true">{{ get_amount($ov['balance'], null, $wp) }}</span>
                                    <span class="db-balance__mask">••••••</span>
                                    <span class="db-hero__unit">{{ $base }}</span>
                                </h2>
                                <button type="button" class="db-balance__toggle" aria-pressed="false" title="{{ __("Show Balance") }}">
                                    <i class="las la-eye"></i><span class="db-balance__timer"></span>
                                </button>
                            </div>
                        </div>
                        <img class="db-hero__flag" src="{{ @$ov['currency']->currencyImage }}" alt="">
                    </div>
                    <span class="db-quick-title">{{ __("Quick Access") }}</span>
                    <div class="db-actions">
                        @foreach ($ov['quick'] as $q)
                            <a href="{{ setRoute($q['route']) }}" class="tone-{{ $q['tone'] ?? 'blue' }}"><span class="db-actions__icon"><i class="{{ $q['icon'] }}"></i></span>{{ $q['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- this month --}}
            <div class="col-xl-5 mb-20">
                <div class="db-panel h-100">
                    <div class="db-panel__head">
                        <h5 class="title mb-0">{{ __("This Month") }}</h5>
                        <span class="db-panel__sub">{{ now()->format('F Y') }}</span>
                    </div>
                    <div class="db-month">
                        <div class="db-month__item"><span>{{ __("Transactions") }}</span><strong>{{ $month['total'] }}</strong></div>
                        <div class="db-month__item is-green"><span>{{ __("Successful") }}</span><strong>{{ $month['success'] }}</strong></div>
                        <div class="db-month__item is-yellow"><span>{{ __("Pending") }}</span><strong>{{ $month['pending'] }}</strong></div>
                        <div class="db-month__item is-red"><span>{{ __("Rejected") }}</span><strong>{{ $month['rejected'] }}</strong></div>
                    </div>
                    <div class="db-rate">
                        <div class="db-rate__head"><span>{{ __("Success Rate") }}</span><strong>{{ $success_rate }}%</strong></div>
                        <div class="db-rate__track"><div style="width: {{ $success_rate }}%"></div></div>
                    </div>
                    @if ($ov['pending_total'] > 0)
                        <a href="{{ setRoute($ov['pending_route']) }}" class="db-pending">
                            <i class="las la-hourglass-half"></i>
                            <span>{{ $ov['pending_total'] }} {{ __("transactions are pending") }}</span>
                            <i class="las la-arrow-right ms-auto"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- stats --}}
        <div class="row mb-20-none mt-10">
            @foreach ($ov['features'] as $f)
                <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-6 col-sm-12 mb-20">
                    <a href="{{ $f['route'] ? setRoute($f['route']) : 'javascript:void(0)' }}" class="db-stat tone-{{ $f['tone'] }}">
                        <span class="db-stat__icon"><i class="{{ $f['icon'] }}"></i></span>
                        <div class="db-stat__body">
                            <span class="db-stat__label">{{ $f['label'] }}</span>
                            @isset($f['number'])
                                <h4 class="db-stat__value">{{ $f['number'] }}</h4>
                            @else
                                <h4 class="db-stat__value">{{ get_amount($f['amount'], null, $wp) }} <span>{{ $base }}</span></h4>
                            @endisset
                            @isset($f['badge'])
                                <span class="db-stat__hint">{{ $f['badge'] }}</span>
                            @elseif(isset($f['count']))
                                <span class="db-stat__hint">{{ $f['count'] }} {{ __("Transactions") }}</span>
                            @endisset
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@push('script')
<script>
    // balance: hidden by default, shown for a few seconds on click, then hidden again
    (function () {
        var box = document.querySelector('.db-balance');
        if (!box) return;
        var btn = box.querySelector('.db-balance__toggle'), timer = box.querySelector('.db-balance__timer'), icon = btn.querySelector('i');
        var seconds = parseInt(box.dataset.seconds, 10) || 10, left = 0, tick = null;
        var showText = @json(__("Show Balance")), hideText = @json(__("Hide Balance"));
        function hide() {
            clearInterval(tick); tick = null; left = 0;
            box.classList.remove('is-shown'); timer.textContent = '';
            icon.className = 'las la-eye'; btn.title = showText; btn.setAttribute('aria-pressed', 'false');
            box.querySelector('.db-balance__value').setAttribute('aria-hidden', 'true');
        }
        function show() {
            box.classList.add('is-shown'); left = seconds; timer.textContent = left + 's';
            icon.className = 'las la-eye-slash'; btn.title = hideText; btn.setAttribute('aria-pressed', 'true');
            box.querySelector('.db-balance__value').setAttribute('aria-hidden', 'false');
            clearInterval(tick);
            tick = setInterval(function () { left--; if (left <= 0) { hide(); } else { timer.textContent = left + 's'; } }, 1000);
        }
        btn.addEventListener('click', function () { box.classList.contains('is-shown') ? hide() : show(); });
        document.addEventListener('visibilitychange', function () { if (document.hidden) hide(); });
    })();

</script>
@endpush
