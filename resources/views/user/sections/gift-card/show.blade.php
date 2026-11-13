@extends('user.layouts.master')
@push('css')
<style>
    .gc-show-card {
        padding: 25px;
        background: #EBEDEF;
        border: 1px solid #ded7e9;
        border-radius: 20px;
        height: 100%;
    }
    .gc-show-thumb {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        aspect-ratio: 16 / 10;
        background: #fff;
    }
    .gc-show-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .gc-show-thumb .badge {
        position: absolute;
        top: 12px;
        right: 12px;
    }
    .gc-show-brand {
        display: block;
        margin-top: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--primary-color);
    }
    .gc-show-name {
        margin: 4px 0 0;
        word-break: break-word;
    }
    .gc-show-amount-label {
        display: block;
        margin-top: 20px;
        font-size: 13px;
        opacity: .75;
    }
    .gc-show-amount {
        font-size: 32px;
        font-weight: 700;
        line-height: 1.2;
        color: var(--primary-color);
        word-break: break-word;
    }
    .gc-show-meta {
        margin-top: 20px;
        border-top: 1px solid #ded7e9;
        padding-top: 15px;
    }
    .gc-show-meta-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 6px 0;
        font-size: 14px;
    }
    .gc-show-meta-row .value {
        font-weight: 600;
        text-align: right;
        word-break: break-all;
    }
    .gc-copy-btn {
        border: 0;
        background: color-mix(in srgb, var(--primary-color) 10%, transparent);
        color: var(--primary-color);
        width: 28px;
        height: 28px;
        border-radius: 50%;
        margin-left: 6px;
        flex-shrink: 0;
        line-height: 28px;
        padding: 0;
    }
    .gc-show-note {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        padding: 12px 15px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--primary-color) 7%, transparent);
        font-size: 13px;
    }
    .gc-show-note i {
        font-size: 20px;
        color: var(--primary-color);
    }
    .gc-show-section + .gc-show-section {
        margin-top: 25px;
    }
    .gc-show-section .gc-show-section-title {
        margin-bottom: 12px;
    }
    @media only screen and (max-width: 575px) {
        .gc-show-card { padding: 15px; }
        .gc-show-amount { font-size: 26px; }
    }
</style>
@endpush
@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ],
        [
            'name'  => __("My Gift Card"),
            'url'   => setRoute("user.gift.card.index"),
        ]
    ], 'active' => __($page_title)])
@endsection
@php
    $product        = $order['product'] ?? [];
    $brand          = $product['brand']['brandName'] ?? null;
    $card_country   = $product['countryCode'] ?? null;
    $card_currency  = $giftCard->card_currency;
    $wallet_currency = $giftCard->user_wallet_currency;
@endphp
@section('content')
<div class="body-wrapper">
    <div class="dashboard-header-wrapper mt-20">
        <h4 class="title">{{ __($page_title) }}</h4>
        <div class="dashboard-btn-wrapper">
            <div class="dashboard-btn">
                <a href="{{ setRoute('user.gift.card.index') }}" class="btn--base"><i class="las la-arrow-left me-1"></i> {{ __("My Gift Card") }}</a>
            </div>
        </div>
    </div>
    <div class="row mb-20-none">
        <div class="col-xl-5 col-lg-5 mb-20">
            <div class="gc-show-card">
                <div class="gc-show-thumb">
                    <img src="{{ $giftCard->card_image }}" alt="{{ $giftCard->card_name }}">
                    <span class="{{ $giftCard->stringStatus->class }}">{{ __($giftCard->stringStatus->value) }}</span>
                </div>
                @if ($brand)
                    <span class="gc-show-brand">{{ $brand }}</span>
                @endif
                <h4 class="gc-show-name">{{ $giftCard->card_name }}</h4>

                <span class="gc-show-amount-label">{{ __("Total Payable Amount") }}</span>
                <div class="gc-show-amount">{{ get_amount($giftCard->total_payable, $wallet_currency) }}</div>

                <div class="gc-show-meta">
                    <div class="gc-show-meta-row">
                        <span>{{ __("TRX ID") }}</span>
                        <span class="d-flex align-items-center">
                            <span class="value" id="gc-trx-id">{{ $giftCard->trx_id }}</span>
                            <button type="button" class="gc-copy-btn" data-copy-target="#gc-trx-id" title="{{ __("copy") }}"><i class="las la-copy"></i></button>
                        </span>
                    </div>
                    <div class="gc-show-meta-row">
                        <span>{{ __("Date") }}</span>
                        <span class="value">{{ $giftCard->created_at?->format('d M Y, h:i A') }}</span>
                    </div>
                </div>

                <div class="gc-show-note">
                    <i class="las la-envelope"></i>
                    <span>{{ __("The gift card code is sent to the receiver email.") }}</span>
                </div>
            </div>
        </div>
        <div class="col-xl-7 col-lg-7 mb-20">
            <div class="gc-show-card">
                <div class="gc-show-section">
                    <h5 class="gc-show-section-title">{{ __("card Information") }}</h5>
                    <div class="preview-list-wrapper">
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-gift', 'label' => __("Card Name"), 'value' => $giftCard->card_name])
                        @if ($brand)
                            @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-tag', 'label' => __("brand"), 'value' => $brand])
                        @endif
                        @if ($card_country)
                            @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-globe', 'label' => __("country"), 'value' => $card_country])
                        @endif
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-receipt', 'label' => __("Card Unit Price"), 'value' => get_amount($giftCard->card_amount, $card_currency)])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-layer-group', 'label' => __("Card Quantity"), 'value' => $giftCard->qty])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-money-bill', 'label' => __("Card Total Price"), 'value' => get_amount($giftCard->card_total_amount, $card_currency)])
                    </div>
                </div>

                <div class="gc-show-section">
                    <h5 class="gc-show-section-title">{{ __("Receiver") }}</h5>
                    <div class="preview-list-wrapper">
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-envelope', 'label' => __("receiver Email"), 'value' => $giftCard->recipient_email])
                        @if ($giftCard->recipient_phone)
                            @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-phone', 'label' => __("Receiver Phone"), 'value' => '+' . ltrim($giftCard->recipient_phone, '+')])
                        @endif
                        @if ($giftCard->recipient_country_iso2)
                            @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-flag', 'label' => __("Receiver Country"), 'value' => $giftCard->recipient_country_iso2])
                        @endif
                    </div>
                </div>

                <div class="gc-show-section">
                    <h5 class="gc-show-section-title">{{ __("Payment Summary") }}</h5>
                    <div class="preview-list-wrapper">
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-wallet', 'label' => __("wallet"), 'value' => $wallet_currency])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-exchange-alt', 'label' => __("Exchange Rate"), 'value' => get_amount(1, $card_currency) . ' = ' . get_amount($giftCard->exchange_rate, $wallet_currency, 4)])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-money-check-alt', 'label' => __("Conversion Amount"), 'value' => get_amount($giftCard->conversion_amount, $wallet_currency), 'class' => 'text--info'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-battery-half', 'label' => __("Total Charge"), 'value' => get_amount($giftCard->total_charge, $wallet_currency), 'class' => 'text--warning'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-money-check-alt', 'label' => __("Total Payable Amount"), 'value' => get_amount($giftCard->total_payable, $wallet_currency), 'class' => 'text--info', 'last' => true])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('script')
<script>
    $(document).on('click', '.gc-copy-btn', function () {
        var text = $.trim($($(this).data('copy-target')).text());
        var done = function () { throwMessage('success', ['{{ __("Copied") }}']); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
            return;
        }
        var temp = $('<textarea>').val(text).css({position: 'fixed', opacity: 0}).appendTo('body');
        temp[0].select();
        document.execCommand('copy');
        temp.remove();
        done();
    });
</script>
@endpush
