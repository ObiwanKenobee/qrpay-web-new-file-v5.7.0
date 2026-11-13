@extends('user.layouts.master')

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __(@$page_title)])
@endsection

@php
    $card_number = preg_replace('/\D/', '', (string) $myCard->card_number);
    $card_groups = $card_number ? str_split($card_number, 4) : [];
    $masked      = $card_number ? '•••• •••• •••• '.substr($card_number, -4) : '---- ---- ---- ----';
    $full        = $card_groups ? implode(' ', $card_groups) : $masked;
    $is_active   = (bool) $myCard->is_active;
    $brand       = ucwords($myCard->card_brand ?: "Visa");

    // card's own billing address (Strowallet API); default Strowallet US billing address when the card has none
    $has_billing = !empty($myCard->address) || !empty($myCard->city) || !empty($myCard->state) || !empty($myCard->postal) || !empty($myCard->country);
    $billing = $has_billing ? [
        __("address")           => $myCard->address,
        __("Billing City")      => $myCard->city,
        __("Billing State")     => $myCard->state,
        __("Billing Zip Code")  => $myCard->postal,
        __("Billing Country")   => $myCard->country,
    ] : [
        __("Billing State")     => "3401 N. Miami, Ave. Ste 230",
        __("Billing City")      => "Miami",
        __("Billing Zip Code")  => "33127",
        __("Billing Country")   => "United State",
    ];
    $billing = array_filter($billing, fn($value) => !empty($value));

    $info = [
        ['icon' => 'la-credit-card',     'tone' => 'blue',   'label' => __("Card Type"),         'value' => ucwords($myCard->card_type)],
        ['icon' => 'la-certificate',     'tone' => 'purple', 'label' => __("Card Brand"),        'value' => $brand],
        ['icon' => 'la-fingerprint',     'tone' => 'teal',   'label' => __("cardI d"),           'value' => $myCard->card_id, 'copy' => $myCard->card_id],
        ['icon' => 'la-user',            'tone' => 'orange', 'label' => __("Card Holder Name"),  'value' => $myCard->card_holder_name],
        ['icon' => 'la-calendar',        'tone' => 'green',  'label' => __("expiration"),        'value' => $myCard->expiry ?: "00/00"],
    ];
@endphp

@section('content')
<div class="body-wrapper">
    <div class="row justify-content-center mt-10 mb-20-none">
        <div class="col-xl-8 mb-20">
            <div class="swc">
                {{-- card face --}}
                <div class="swc-card {{ $is_active ? '' : 'is-frozen' }}">
                    <div class="swc-card__top">
                        <span class="swc-card__type">{{ __(ucwords($myCard->card_type ?: "Virtual")) }} {{ __("card") }}</span>
                        <span class="swc-status {{ $is_active ? 'is-on' : 'is-off' }}">
                            <i class="las {{ $is_active ? 'la-check-circle' : 'la-snowflake' }}"></i>
                            {{ $is_active ? __("active") : __("Freeze") }}
                        </span>
                    </div>
                    <div class="swc-card__balance">
                        <span>{{ __("Current Balance") }}</span>
                        <strong>{{ get_amount(@$myCard->balance, $myCard->currency) }}</strong>
                    </div>
                    <div class="swc-card__number">
                        <span class="swc-pan" data-masked="{{ $masked }}" data-full="{{ $full }}">{{ $masked }}</span>
                        @if ($card_number)
                            <button type="button" class="swc-icon-btn" data-swc-toggle="pan" title="{{ __("Show") }}"><i class="las la-eye"></i></button>
                            <button type="button" class="swc-icon-btn" data-swc-copy="{{ $card_number }}" title="{{ __("Copy") }}"><i class="las la-copy"></i></button>
                        @endif
                    </div>
                    <div class="swc-card__bottom">
                        <div>
                            <small>{{ __("Card Holder Name") }}</small>
                            <span>{{ $myCard->card_holder_name ?: '---' }}</span>
                        </div>
                        <div>
                            <small>{{ __("expiration") }}</small>
                            <span>{{ $myCard->expiry ?: '00/00' }}</span>
                        </div>
                        <div>
                            <small>{{ __("Cvv") }}</small>
                            <span class="swc-cvv" data-masked="•••" data-full="{{ $myCard->cvv ?: '***' }}">•••</span>
                            <button type="button" class="swc-icon-btn swc-icon-btn--sm" data-swc-toggle="cvv" title="{{ __("Show") }}"><i class="las la-eye"></i></button>
                        </div>
                        <div class="swc-card__brand">{{ $brand }}</div>
                    </div>
                </div>

                {{-- quick actions --}}
                <div class="swc-actions">
                    <a href="{{ setRoute('user.strowallet.virtual.card.fund.page', $myCard->id) }}" class="swc-action">
                        <i class="las la-hand-holding-usd"></i><span>{{ __("fund") }}</span>
                    </a>
                    <a href="{{ setRoute('user.strowallet.virtual.card.withdraw.page', $myCard->card_id) }}" class="swc-action">
                        <i class="las la-money-bill-wave"></i><span>{{ __("withdraw") }}</span>
                    </a>
                    <a href="{{ setRoute('user.strowallet.virtual.card.transaction', $myCard->card_id) }}" class="swc-action">
                        <i class="las la-exchange-alt"></i><span>{{ __("Transactions") }}</span>
                    </a>
                    <a href="{{ setRoute('user.strowallet.virtual.card.index') }}" class="swc-action">
                        <i class="las la-th-large"></i><span>{{ __("My Card") }}</span>
                    </a>
                </div>

                {{-- card information --}}
                <div class="swc-panel">
                    <div class="swc-panel__head">
                        <h5 class="title">{{ __("Card Details") }}</h5>
                    </div>
                    <ul class="swc-list">
                        @foreach ($info as $row)
                            @continue(empty($row['value']))
                            <li>
                                <span class="swc-list__icon tone-{{ $row['tone'] }}"><i class="las {{ $row['icon'] }}"></i></span>
                                <span class="swc-list__label">{{ $row['label'] }}</span>
                                <span class="swc-list__value">
                                    {{ $row['value'] }}
                                    @isset($row['copy'])
                                        <button type="button" class="swc-icon-btn swc-icon-btn--sm" data-swc-copy="{{ $row['copy'] }}" title="{{ __("Copy") }}"><i class="las la-copy"></i></button>
                                    @endisset
                                </span>
                            </li>
                        @endforeach
                        <li>
                            <span class="swc-list__icon swc-status-icon tone-{{ $is_active ? 'green' : 'red' }}"><i class="las {{ $is_active ? 'la-lock-open' : 'la-snowflake' }}"></i></span>
                            <span class="swc-list__label">{{ __("Status") }}</span>
                            <span class="swc-list__value">
                                <div class="toggle-container">
                                    @include('admin.components.form.switcher',[
                                        'name'          => 'is_active',
                                        'value'         => old('is_active',@$myCard->is_active ),
                                        'options'       => [__('UnFreeze') => 1,__('Freeze') => 0],
                                        'onload'        => true,
                                        'data_target'   => @$myCard->id,
                                    ])
                                </div>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xl-4 mb-20">
            <div class="swc-panel">
                <div class="swc-panel__head">
                    <h5 class="title"><span class="swc-list__icon tone-blue"><i class="las la-map-marker"></i></span>{{ __("Billing Address") }}</h5>
                    <button type="button" class="swc-icon-btn" data-swc-copy="{{ implode(', ', $billing) }}" title="{{ __("Copy") }}"><i class="las la-copy"></i></button>
                </div>
                <ul class="swc-billing">
                    @foreach ($billing as $label => $value)
                        <li>
                            <small>{{ $label }}</small>
                            <span>{{ $value }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="swc-note"><i class="las la-info-circle"></i> {{ __("Use this billing address for online payments with this card.") }}</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('css')
<style>
    .swc-card { position: relative; overflow: hidden; padding: 24px 26px; border-radius: 20px; color: #fff; background: linear-gradient(135deg, #2563eb 0%, #7c3aed 60%, #db2777 120%); box-shadow: 0 18px 40px -18px rgba(76, 29, 149, .55); }
    .swc-card::before, .swc-card::after { content: ""; position: absolute; border-radius: 50%; background: rgba(255,255,255,.08); }
    .swc-card::before { width: 260px; height: 260px; right: -80px; top: -110px; }
    .swc-card::after { width: 180px; height: 180px; right: 90px; bottom: -120px; }
    .swc-card > * { position: relative; z-index: 1; }
    .swc-card.is-frozen { background: linear-gradient(135deg, #64748b 0%, #334155 100%); }
    .swc-card__top { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .swc-card__type { font-size: 13px; font-weight: 600; letter-spacing: .6px; text-transform: uppercase; opacity: .9; }
    .swc-status { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,.18); }
    .swc-status.is-on i { color: #86efac; }
    .swc-status.is-off i { color: #bae6fd; }
    .swc-card__balance { margin: 22px 0 18px; }
    .swc-card__balance span { display: block; font-size: 13px; opacity: .85; }
    .swc-card__balance strong { display: block; font-size: 30px; font-weight: 700; line-height: 1.25; color: #fff; }
    .swc-card__number { display: flex; align-items: center; gap: 8px; font-size: 21px; font-weight: 600; letter-spacing: 2px; font-family: "Courier New", monospace; }
    .swc-card__number .swc-pan { margin-right: 4px; }
    .swc-card__bottom { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 14px 28px; margin-top: 20px; }
    .swc-card__bottom small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; opacity: .75; }
    .swc-card__bottom span { font-size: 15px; font-weight: 600; }
    .swc-card__brand { margin-left: auto; font-size: 22px; font-weight: 800; font-style: italic; letter-spacing: 1px; }
    .swc-card .swc-icon-btn { color: #fff; background: rgba(255,255,255,.18); border-color: transparent; }
    .swc-card .swc-icon-btn:hover { background: rgba(255,255,255,.32); color: #fff; }

    .swc-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border-radius: 50%; border: 1px solid #e5e7eb; background: #fff; color: var(--primary-color); font-size: 16px; cursor: pointer; transition: all .2s; }
    .swc-icon-btn:hover { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
    .swc-icon-btn--sm { width: 26px; height: 26px; font-size: 14px; margin-left: 4px; vertical-align: middle; }

    .swc-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 18px 0; }
    .swc-action { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 14px 8px; border-radius: 14px; background: #fff; border: 1px solid #ececf3; color: #1f2937; font-size: 14px; font-weight: 600; transition: all .2s; }
    .swc-action i { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 22px; background: color-mix(in srgb, var(--primary-color) 10%, #fff); color: var(--primary-color); transition: all .2s; }
    .swc-action:hover { border-color: var(--primary-color); color: var(--primary-color); transform: translateY(-2px); }
    .swc-action:hover i { background: var(--primary-color); color: #fff; }

    .swc-panel { background: #fff; border: 1px solid #ececf3; border-radius: 16px; padding: 18px 20px; }
    .swc-panel__head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 8px; }
    .swc-panel__head .title { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 17px; }
    .swc-list { list-style: none; margin: 0; padding: 0; }
    .swc-list li { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px dashed #e5e7eb; }
    .swc-list li:last-child { border-bottom: 0; }
    .swc-list__icon { flex: 0 0 auto; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 18px; }
    .swc-list__label { flex: 1 1 auto; font-size: 14px; color: #6b7280; }
    .swc-list__value { font-size: 14px; font-weight: 600; color: #111827; text-align: right; word-break: break-all; display: inline-flex; align-items: center; justify-content: flex-end; }
    .swc-list__value .toggle-container { margin: 0; }
    .tone-blue   { background: #dbeafe; color: #1d4ed8; }
    .tone-purple { background: #ede9fe; color: #6d28d9; }
    .tone-teal   { background: #ccfbf1; color: #0f766e; }
    .tone-orange { background: #ffedd5; color: #c2410c; }
    .tone-green  { background: #dcfce7; color: #15803d; }
    .tone-red    { background: #fee2e2; color: #b91c1c; }

    .swc-billing { list-style: none; margin: 0; padding: 0; }
    .swc-billing li { padding: 10px 12px; margin-bottom: 8px; border-radius: 10px; background: #f8fafc; }
    .swc-billing small { display: block; font-size: 12px; color: #6b7280; }
    .swc-billing span { font-size: 14px; font-weight: 600; color: #111827; }
    .swc-note { margin: 6px 0 0; font-size: 12px; color: #6b7280; }

    @media (max-width: 575px) {
        .swc-card { padding: 20px; }
        .swc-card__number { font-size: 17px; letter-spacing: 1px; }
        .swc-card__balance strong { font-size: 24px; }
        .swc-actions { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@endpush

@push('script')
<script>
    var SWC_STATUS_URL = "{{ setRoute('user.strowallet.virtual.card.change.status') }}";
    $(document).ready(function(){
        switcherAjax(SWC_STATUS_URL);
    });

    // card face, badge and status icon follow the Freeze / UnFreeze switch as soon as it is saved
    var SWC_TEXT = { on: @json(__("active")), off: @json(__("Freeze")) };
    function swcStatus(active) {
        $('.swc-card').toggleClass('is-frozen', !active);
        $('.swc-status').toggleClass('is-on', active).toggleClass('is-off', !active)
            .html('<i class="las ' + (active ? 'la-check-circle' : 'la-snowflake') + '"></i> ' + (active ? SWC_TEXT.on : SWC_TEXT.off));
        $('.swc-status-icon').toggleClass('tone-green', active).toggleClass('tone-red', !active)
            .find('i').attr('class', 'las ' + (active ? 'la-lock-open' : 'la-snowflake'));
    }
    $(document).ajaxSuccess(function (event, xhr, settings) {
        if (settings.url !== SWC_STATUS_URL) return;
        // the switcher writes the new value into its input after the request finishes
        setTimeout(function () { swcStatus($('input[name=is_active]').val() == 1); }, 0);
    });

    // show / hide card number and CVV
    $(document).on('click', '[data-swc-toggle]', function () {
        var $target = $(this).data('swc-toggle') === 'pan' ? $('.swc-pan') : $('.swc-cvv');
        var shown = $target.text().trim() === String($target.data('full')).trim();
        $target.text(shown ? $target.data('masked') : $target.data('full'));
        $(this).find('i').toggleClass('la-eye', shown).toggleClass('la-eye-slash', !shown);
    });

    // copy card number / card id / billing address
    $(document).on('click', '[data-swc-copy]', function () {
        var $icon = $(this).find('i');
        navigator.clipboard.writeText(String($(this).data('swc-copy')));
        notification('success', @json(__("Copied Successfully")));
        $icon.removeClass('la-copy').addClass('la-check');
        setTimeout(function () { $icon.removeClass('la-check').addClass('la-copy'); }, 1500);
    });
</script>
@endpush
