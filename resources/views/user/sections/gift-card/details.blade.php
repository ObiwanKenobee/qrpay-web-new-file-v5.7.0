@extends('user.layouts.master')
@push('css')
<style>
    .gc-buy-panel,
    .gc-summary {
        padding: 25px;
        background: #EBEDEF;
        border: 1px solid #ded7e9;
        border-radius: 20px;
    }
    .gc-summary {
        position: sticky;
        top: 100px;
    }
    .gc-product-head {
        display: flex;
        align-items: center;
        gap: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #ded7e9;
    }
    .gc-product-thumb {
        width: 150px;
        aspect-ratio: 16 / 10;
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
        background: #fff;
    }
    .gc-product-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .gc-product-title {
        margin: 0 0 10px;
        word-break: break-word;
    }
    .gc-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .gc-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: color-mix(in srgb, var(--primary-color) 10%, transparent);
        color: var(--primary-color);
    }
    .gc-step {
        padding-top: 22px;
    }
    .gc-step + .gc-step {
        margin-top: 4px;
        border-top: 1px dashed #ded7e9;
    }
    .gc-step-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
    }
    .gc-step-title .gc-step-num {
        width: 30px;
        height: 30px;
        line-height: 30px;
        border-radius: 50%;
        text-align: center;
        font-weight: 700;
        font-size: 14px;
        color: #fff;
        background: var(--primary-color);
        flex-shrink: 0;
    }
    .gc-step-title h5 {
        margin: 0;
    }
    .gc-hint {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        opacity: .75;
    }
    .gc-summary-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 15px;
        border-bottom: 1px dashed #ded7e9;
    }
    .gc-summary-head img {
        width: 52px;
        height: 52px;
        object-fit: cover;
        border-radius: 10px;
        flex-shrink: 0;
    }
    .gc-summary-title {
        margin: 0;
    }
    .gc-summary-sub {
        display: block;
        font-size: 13px;
        opacity: .75;
    }
    .gc-summary-group {
        display: block;
        margin: 18px 0 8px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        opacity: .7;
    }
    .gc-summary .preview-list-wrapper {
        background: #fff;
    }
    .gc-summary .preview-list-item {
        padding: 10px 15px;
    }
    .gc-summary .preview-list-right span {
        word-break: break-word;
    }
    .gc-buy-action {
        margin-top: 10px;
        padding-top: 20px;
        border-top: 1px dashed #ded7e9;
    }
    .gc-buy-action .buyBtn {
        width: 100%;
    }
    .gc-buy-note {
        display: block;
        margin-top: 10px;
        font-size: 12px;
        text-align: center;
        opacity: .8;
    }
    .gc-buy-action .buyBtn[disabled] {
        opacity: .65;
        cursor: not-allowed;
    }
    .gc-limits {
        padding: 5px 15px;
        background: #fff;
        border: 1px solid #ded7e9;
        border-radius: 10px;
    }
    .limit-meter { padding: 10px 0; }
    .limit-meter + .limit-meter { border-top: 1px solid #ded7e9; }
    .limit-meter__head { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; font-size: 14px; }
    .limit-meter__value strong { font-weight: 700; }
    .limit-meter__of { opacity: .65; font-size: 13px; }
    .limit-meter__track { display: flex; height: 9px; border-radius: 20px; overflow: hidden; background: rgba(0, 0, 0, .1); }
    .limit-meter__left { background: var(--primary-color); transition: width .35s ease; }
    .limit-meter__pending {
        background: repeating-linear-gradient(45deg, var(--primary-color), var(--primary-color) 4px, transparent 4px, transparent 8px);
        opacity: .45;
        transition: width .35s ease;
    }
    .limit-meter.is-low .limit-meter__left { background: #f5a623; }
    .limit-meter.is-low .limit-meter__pending { background: repeating-linear-gradient(45deg, #f5a623, #f5a623 4px, transparent 4px, transparent 8px); }
    .limit-meter.is-over .limit-meter__left { background: #e5484d; }
    .limit-meter.is-over .limit-meter__pending { background: #e5484d; opacity: .35; }
    .limit-meter.is-low .limit-meter__note { color: #c77c00; opacity: 1; }
    .limit-meter.is-over .limit-meter__note { color: #e5484d; opacity: 1; font-weight: 600; }
    .limit-meter.is-unlimited .limit-meter__left { width: 100% !important; opacity: .35; }
    .limit-meter.is-unlimited .limit-meter__of { display: none; }
    .limit-meter__foot { display: flex; justify-content: space-between; gap: 10px; margin-top: 6px; font-size: 12px; opacity: .75; }
    .limit-meter__note { text-align: right; }
    /* quantity stepper */
    .gc-qty { display: flex; align-items: stretch; gap: 8px; }
    .gc-qty .form--control { text-align: center; font-weight: 600; -moz-appearance: textfield; }
    .gc-qty .form--control::-webkit-outer-spin-button,
    .gc-qty .form--control::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .gc-qty-btn {
        width: 46px; flex-shrink: 0; border-radius: 10px; border: 1px solid #ded7e9;
        background: #fff; color: var(--primary-color); font-size: 16px; transition: all .2s ease;
    }
    .gc-qty-btn:hover:not([disabled]) { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
    .gc-qty-btn[disabled] { opacity: .45; cursor: not-allowed; }
    /* live info tiles beside quantity / wallet */
    .gc-stat {
        display: flex; align-items: center; gap: 12px; min-height: 45px;
        padding: 10px 14px; border-radius: 10px; border: 1px solid transparent;
    }
    .gc-stat-icon {
        width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center; font-size: 20px; color: #fff;
    }
    .gc-stat-value { display: block; font-size: 17px; font-weight: 700; line-height: 1.3; word-break: break-word; }
    .gc-stat-sub { display: block; font-size: 12px; opacity: .8; word-break: break-word; }
    .gc-stat--green { background: #e8f7ee; border-color: #c6ead3; }
    .gc-stat--green .gc-stat-icon { background: #16a34a; }
    .gc-stat--green .gc-stat-value { color: #15803d; }
    .gc-stat--blue { background: #eaf2ff; border-color: #cbdcfb; }
    .gc-stat--blue .gc-stat-icon { background: #2563eb; }
    .gc-stat--blue .gc-stat-value { color: #1d4ed8; }
    .gc-stat .balance-after.is-short { color: #e5484d; font-weight: 600; }
    @media only screen and (max-width: 991px) {
        .gc-summary {
            position: static;
        }
    }
    @media only screen and (max-width: 575px) {
        .gc-buy-panel,
        .gc-summary {
            padding: 15px;
        }
        .gc-product-head {
            flex-direction: column;
            align-items: flex-start;
        }
        .gc-product-thumb {
            width: 100%;
        }
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
            'name'  => __("Gift Cards"),
            'url'   => setRoute("user.gift.card.list"),
        ]
    ], 'active' => __($page_title)])
@endsection
@php
    $is_range       = ($product['denominationType'] ?? '') == "RANGE";
    $card_currency  = $product['recipientCurrencyCode'] ?? '';
    $pin_enabled    = $basic_settings->user_pin_verification == true;
@endphp
@section('content')
<div class="body-wrapper">
    <form id="giftBuyForm" class="card-form mt-20" action="{{ setRoute('user.gift.card.order') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="exchange_rate">
        <input type="hidden" name="product_id" value="{{ $product['productId'] }}">
        <input type="hidden" name="g_qty">
        <input type="hidden" name="g_unit_price" value="0" class="g_unit_price">
        <input type="hidden" name="g_recipient_email">
        <input type="hidden" name="g_receipient_country">
        <input type="hidden" name="g_recipient_phone_code">
        <input type="hidden" name="g_recipient_phone">
        <input type="hidden" name="g_recipient_iso">
        <input type="hidden" name="g_from_name">
        <input type="hidden" name="wallet_currency">
        <input type="hidden" name="receiver_currency">

        <div class="row mb-20-none">
            <div class="col-xl-8 col-lg-7 mb-20">
                <div class="gc-buy-panel">
                    <div class="gc-product-head">
                        <div class="gc-product-thumb">
                            <img src="{{ $product['logoUrls'][0] ?? '' }}" alt="{{ $product['productName'] }}">
                        </div>
                        <div>
                            <h4 class="gc-product-title">{{ $product['productName'] }}</h4>
                            <div class="gc-chips">
                                @if (!empty($product['brand']['brandName']))
                                    <span class="gc-chip"><i class="las la-tag"></i>{{ $product['brand']['brandName'] }}</span>
                                @endif
                                @if (!empty($product['country']['name']))
                                    <span class="gc-chip"><i class="las la-globe"></i>{{ $product['country']['name'] }}</span>
                                @endif
                                <span class="gc-chip"><i class="las la-coins"></i>{{ $card_currency }}</span>
                                @if ($is_range)
                                    <span class="gc-chip">{{ get_amount($product['minRecipientDenomination'] ?? 0) }} - {{ get_amount($product['maxRecipientDenomination'] ?? 0) }} {{ $card_currency }}</span>
                                @else
                                    <span class="gc-chip">{{ __("Fixed Amount") }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 1: Amount + Quantity --}}
                    <div class="gc-step">
                        <div class="gc-step-title">
                            <span class="gc-step-num">1</span>
                            <h5>{{ __("Amount") }} &amp; {{ __("quantity") }}</h5>
                        </div>
                        <div class="row">
                            @if ($is_range)
                                <div class="col-12 form-group">
                                    <label>{{ __("Amount") }} ({{ $card_currency }}) <span>*</span></label>
                                    <input type="number" step="any" name="amount" class="form--control enter-amount" value="{{ old('amount') }}" placeholder="{{ __("enter Amount") }}">
                                    <span class="gc-hint">{{ __("Select an amount between") }} {{ get_amount($product['minRecipientDenomination'] ?? 0) }} {{ $card_currency }} - {{ get_amount($product['maxRecipientDenomination'] ?? 0) }} {{ $card_currency }}</span>
                                </div>
                            @else
                                <div class="col-12 form-group">
                                    <label>{{ __("Amount") }} <span>*</span></label>
                                    <div class="gift-card-radio-wrapper">
                                        @foreach ($product['fixedRecipientDenominations'] ?? [] as $key => $price)
                                            <div class="gift-card-radio-item">
                                                <input type="radio" id="level-{{ $key + 1 }}" name="amount" value="{{ $price }}" class="radio_amount" {{ $key == 0 ? 'checked' : '' }}>
                                                <label for="level-{{ $key + 1 }}">{{ $price }} {{ $card_currency }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-6 form-group">
                                <label>{{ __("quantity") }} <span>*</span></label>
                                <div class="gc-qty">
                                    <button type="button" class="gc-qty-btn" data-step="-1" aria-label="-1"><i class="las la-minus"></i></button>
                                    <input type="number" class="form--control" value="{{ old('quantity', 1) }}" min="1" step="1" name="quantity" id="quantityInput" inputmode="numeric">
                                    <button type="button" class="gc-qty-btn" data-step="1" aria-label="+1"><i class="las la-plus"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __("Card Total") }}</label>
                                <div class="gc-stat gc-stat--green">
                                    <span class="gc-stat-icon"><i class="las la-gift"></i></span>
                                    <div>
                                        <strong class="gc-stat-value total-price">--</strong>
                                        <span class="gc-stat-sub card-total-calc">--</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Receiver --}}
                    <div class="gc-step">
                        <div class="gc-step-title">
                            <span class="gc-step-num">2</span>
                            <h5>{{ __("Receiver") }}</h5>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>{{ __("receiver Email") }} <span>*</span></label>
                                <input type="email" name="receiver_email" class="form--control" placeholder="{{ __("enter Receiver Email Address") }}" value="{{ old("receiver_email") }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __("country") }} <span>*</span></label>
                                <select name="country" class="form--control select2-auto-tokenize country-select"></select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __("phone Number") }} <span>*</span></label>
                                <div class="input-group">
                                    <div class="input-group-text phone-code">+</div>
                                    <input class="phone-code" type="hidden" name="phone_code" />
                                    <input type="text" class="form--control" placeholder="{{ __("enter Phone Number") }}" name="phone" value="{{ old('phone') }}">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __("From Name") }} <span>*</span></label>
                                <input type="text" name="from_name" class="form--control" placeholder="{{ __("Your Name") }}" value="{{ old('from_name') }}">
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: Pay With --}}
                    <div class="gc-step">
                        <div class="gc-step-title">
                            <span class="gc-step-num">3</span>
                            <h5>{{ __("Pay With") }}</h5>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>{{ __("my Wallet") }} <span>*</span></label>
                                <select class="form--control nice-select currency" name="currency">
                                    @foreach ($currencies ?? [] as $item)
                                        <option
                                            value="{{ $item->code }}"
                                            data-id="{{ $item->id }}"
                                            data-rate="{{ $item->rate }}"
                                            data-symbol="{{ $item->symbol }}"
                                            data-type="{{ $item->type }}"
                                            {{ get_default_currency_code() == $item->code ? "selected" : "" }}
                                        >{{ $item->name . " (" . $item->code . ")" }}</option>
                                    @endforeach
                                </select>
                                <code class="d-block mt-10 text-end balance-show"></code>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __("You Pay") }}</label>
                                <div class="gc-stat gc-stat--blue">
                                    <span class="gc-stat-icon"><i class="las la-wallet"></i></span>
                                    <div>
                                        <strong class="gc-stat-value pay-in-total">--</strong>
                                        <span class="gc-stat-sub">{{ __("Fees & Charges") }}: <span class="fees-total">--</span></span>
                                        <span class="gc-stat-sub">{{ __("Remaining Balance") }}: <span class="balance-after">--</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="gc-buy-action">
                        <button type="submit" class="btn--base buyBtn confirmedBtn"><i class="las la-gift me-1"></i> {{ __("buy Now") }}</button>
                        <span class="gc-buy-note"><i class="las la-envelope"></i> {{ __("The gift card code is sent to the receiver email.") }}</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-5 mb-20">
                <div class="gc-summary">
                    <div class="gc-summary-head">
                        <img src="{{ $product['logoUrls'][0] ?? '' }}" alt="{{ $product['productName'] }}">
                        <div>
                            <h5 class="gc-summary-title">{{ __("Order Summary") }}</h5>
                            <span class="gc-summary-sub product-name">{{ $product['productName'] }}</span>
                        </div>
                    </div>
                    <span class="gc-summary-group">{{ __("Receiver Information") }}</span>
                    <div class="preview-list-wrapper">
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-envelope', 'label' => __("receiver Email"), 'value' => '--', 'valueClass' => 'receiver-email'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-flag', 'label' => __("Receiver Country"), 'value' => '--', 'valueClass' => 'receiver-country'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-phone', 'label' => __("Receiver Phone"), 'value' => '--', 'valueClass' => 'receiver-phone'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-user', 'label' => __("From Name"), 'value' => '--', 'valueClass' => 'from-name'])
                    </div>
                    <span class="gc-summary-group">{{ __("Payment Summary") }}</span>
                    <div class="preview-list-wrapper">
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-receipt', 'label' => __("Unit Price"), 'value' => '--', 'valueClass' => 'unit-price'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-layer-group', 'label' => __("quantity"), 'value' => '--', 'valueClass' => 'total-quantity'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-money-bill', 'label' => __("total Price"), 'value' => '--', 'valueClass' => 'total-price'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-exchange-alt', 'label' => __("Exchange Rate"), 'value' => '--', 'valueClass' => 'rate-show'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-money-check-alt', 'label' => __("Conversion Amount"), 'value' => '--', 'class' => 'text--info', 'valueClass' => 'conversion-amount'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-battery-half', 'label' => __("Total Fees & Charges"), 'value' => '--', 'class' => 'text--warning', 'valueClass' => 'fees-show'])
                        @include('user.sections.gift-card.partials.preview-item', ['icon' => 'las la-money-check-alt', 'label' => __("Total Payable Amount"), 'value' => '--', 'class' => 'text--info', 'last' => true, 'valueClass' => 'pay-in-total'])
                    </div>
                    <span class="gc-summary-group">{{ __("Limit Information") }}</span>
                    <div class="gc-limits">
                        @foreach ([['daily', __("Daily Limit"), __("Resets at midnight")], ['monthly', __("Monthly Limit"), __("Resets on the 1st of every month")]] as [$limit_key, $limit_label, $limit_reset])
                            <div class="limit-meter" data-limit="{{ $limit_key }}">
                                <div class="limit-meter__head">
                                    <span>{{ $limit_label }}</span>
                                    <span class="limit-meter__value">
                                        <strong class="{{ $limit_key }}-remaining">--</strong>
                                        <span class="limit-meter__of">{{ __("left of") }} <span class="limit-{{ $limit_key }}">--</span></span>
                                    </span>
                                </div>
                                <div class="limit-meter__track" role="progressbar" aria-label="{{ $limit_label }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="limit-meter__left" style="width: 100%"></div>
                                    <div class="limit-meter__pending" style="width: 0%"></div>
                                </div>
                                <div class="limit-meter__foot">
                                    <span>{{ $limit_reset }}</span>
                                    <span class="limit-meter__note"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @if ($pin_enabled)
            @include('user.components.modal.pin-check')
        @endif
    </form>
</div>
@endsection
@push('script')
<script>
    (function () {
        var amountType      = @json($is_range ? 'RANGE' : 'FIXED');
        var receiverCode    = @json($card_currency);
        var productName     = @json($product['productName']);
        var minLimit        = parseFloat(@json($is_range ? ($product['minRecipientDenomination'] ?? 0) : 0));
        var maxLimit        = parseFloat(@json($is_range ? ($product['maxRecipientDenomination'] ?? 0) : 0));
        var fixedCharge     = parseFloat(@json(getAmount($cardCharge->fixed_charge ?? 0)));
        var percentCharge   = parseFloat(@json(getAmount($cardCharge->percent_charge ?? 0)));
        var pinEnabled      = @json($pin_enabled);
        var dailyLimit      = parseFloat(@json(getAmount($cardCharge->daily_limit ?? 0)));
        var monthlyLimit    = parseFloat(@json(getAmount($cardCharge->monthly_limit ?? 0)));
        var chargeId        = @json($cardCharge->id ?? null);
        var limitUrl        = "{{ route('global.get.total.transactions') }}";
        var limitRemaining  = { daily: null, monthly: null };
        var limitTimer      = null;
        var limitXhr        = null;
        var receiverRate    = NaN;
        var walletBalance   = NaN;
        var $form           = $('#giftBuyForm');
        var $buyBtns        = $form.find('.buyBtn, .finalConfirmed');

        window.gcFmt = function (value, decimals) {
            var n = parseFloat(value);
            return isFinite(n) ? n.toFixed(decimals === undefined ? 2 : decimals) : '--';
        };
        function text(value) {
            return (value === undefined || value === null || $.trim(value) === '') ? '--' : value;
        }

        function wallet() {
            var $opt = $("select[name=currency] :selected");
            return {
                id:     $opt.attr('data-id'),
                code:   $opt.val(),
                rate:   parseFloat($opt.attr('data-rate')),
                symbol: $opt.attr('data-symbol') || '',
            };
        }
        function getUnitPrice() {
            if (amountType === 'RANGE') return parseFloat($('.enter-amount').val());
            return parseFloat($('input[name=amount]:checked').val());
        }
        function getQty() {
            var qty = parseInt($('#quantityInput').val(), 10);
            return isFinite(qty) && qty > 0 ? qty : 1;
        }

        function getExchangeRate() {
            $.ajax({
                type: 'get',
                url: "{{ route('global.receiver.wallet.currency') }}",
                data: { code: receiverCode },
                success: function (data) {
                    receiverRate = parseFloat(data && data.rate);
                    updateExchangeRate();
                    getPreview();
                    fetchLimits();
                },
                error: function () {
                    receiverRate = NaN;
                    updateExchangeRate();
                    getPreview();
                }
            });
        }
        function updateExchangeRate() {
            var rate = wallet().rate / receiverRate;
            $("input[name=exchange_rate]").val(isFinite(rate) ? rate : '');
            return rate;
        }

        function senderBalance() {
            $.ajax({
                type: 'get',
                url: "{{ route('global.user.wallet.balance') }}",
                data: { id: wallet().id },
                success: function (data) {
                    walletBalance = parseFloat(data);
                    $('.balance-show').text("{{ __('Available Balance') }}: " + wallet().symbol + gcFmt(data));
                    getPreview();
                }
            });
        }

        function feesCalculation(conversionAmount) {
            var fixed   = wallet().rate * fixedCharge;
            var percent = (parseFloat(conversionAmount) / 100) * percentCharge;
            return { fixed: fixed, percent: percent, total: fixed + percent };
        }

        window.getPreview = function () {
            var w           = wallet();
            var qty         = getQty();
            var unitPrice   = getUnitPrice();
            var totalPrice  = unitPrice * qty;
            var rate        = updateExchangeRate();
            var conversion  = totalPrice * rate;
            var charges     = feesCalculation(conversion);
            var payable     = conversion + charges.total;
            var $country    = $(".country-select :selected");
            var phoneCode   = $country.data('mobile-code');
            var phone       = $("input[name=phone]").val();
            var email       = $("input[name=receiver_email]").val();
            var fromName    = $("input[name=from_name]").val();

            $('.product-name').text(productName);
            $('.unit-price').text(isFinite(unitPrice) ? gcFmt(unitPrice) + ' ' + receiverCode : '--');
            $('.total-quantity').text(qty);
            $('.total-price').text(isFinite(totalPrice) ? gcFmt(totalPrice) + ' ' + receiverCode : '--');
            $('.receiver-email').text(text(email));
            $('.receiver-country').text($country.data('iso2') ? $country.val() : '--');
            $('.receiver-phone').text(phone ? (phoneCode ? '+' + String(phoneCode).replace('+', '') + ' ' : '') + phone : '--');
            $('.from-name').text(text(fromName));
            $('.rate-show').text(isFinite(rate) ? '1 ' + receiverCode + ' = ' + gcFmt(rate, 4) + ' ' + w.code : '--');
            $('.conversion-amount').text(isFinite(conversion) ? gcFmt(conversion, 4) + ' ' + w.code : '--');
            $('.fees-show').text(isFinite(charges.total) ? gcFmt(charges.fixed, 4) + ' ' + w.code + ' + ' + gcFmt(percentCharge, 4) + '% = ' + gcFmt(charges.total, 4) + ' ' + w.code : '--');
            $('.pay-in-total').text(isFinite(payable) ? gcFmt(payable, 4) + ' ' + w.code : '--');

            // inline tiles beside quantity / wallet
            $('.card-total-calc').text(isFinite(unitPrice) ? gcFmt(unitPrice) + ' ' + receiverCode + ' × ' + qty : '--');
            $('.fees-total').text(isFinite(charges.total) ? gcFmt(charges.total, 4) + ' ' + w.code : '--');
            var after = walletBalance - payable;
            $('.balance-after')
                .text(isFinite(after) ? (after < 0 ? "{{ __('Sorry, insufficient balance') }}" : w.symbol + gcFmt(after)) : '--')
                .toggleClass('is-short', isFinite(after) && after < 0);
            $('.gc-qty-btn[data-step="-1"]').prop('disabled', qty <= 1);

            // hidden inputs used by the order request
            $("input[name=g_qty]").val(qty);
            $("input[name=g_unit_price]").val(isFinite(unitPrice) ? unitPrice : '');
            $("input[name=g_recipient_email]").val(email);
            $("input[name=g_receipient_country]").val($country.val() || '');
            $("input[name=g_recipient_phone_code]").val(phoneCode || '');
            $("input[name=g_recipient_phone]").val(phone);
            $("input[name=g_recipient_iso]").val($country.data('iso2') || '');
            $("input[name=g_from_name]").val(fromName);
            $("input[name=wallet_currency]").val(w.code);
            $("input[name=receiver_currency]").val(receiverCode);

            renderLimitBars(conversion);
        };

        // Daily / monthly limit bars (wallet currency): solid = left after this order, striped = this order
        function renderLimitBars(orderAmount) {
            var w       = wallet();
            var totals  = { daily: dailyLimit * w.rate, monthly: monthlyLimit * w.rate };
            var amount  = parseFloat(orderAmount);
            amount = isFinite(amount) && amount > 0 ? amount : 0;
            var over_any = false;

            $('.limit-meter').each(function () {
                var meter = $(this);
                var key   = meter.data('limit');
                var total = totals[key];

                if (!(total > 0)) {
                    meter.addClass('is-unlimited').removeClass('is-over is-low');
                    meter.find('.' + key + '-remaining').text("{{ __('unlimited') }}");
                    meter.find('.limit-meter__pending').css('width', '0%');
                    meter.find('.limit-meter__note').text('');
                    return;
                }
                meter.removeClass('is-unlimited');

                var remaining = limitRemaining[key] === null ? total : Math.max(Math.min(limitRemaining[key], total), 0);
                var pending   = Math.min(amount, remaining);
                var left      = Math.max(remaining - amount, 0);
                var over      = amount > remaining;
                over_any = over_any || over;

                meter.find('.' + key + '-remaining').text(gcFmt(left) + ' ' + w.code);
                meter.find('.limit-' + key).text(gcFmt(total) + ' ' + w.code);
                meter.find('.limit-meter__left').css('width', (left / total * 100) + '%');
                meter.find('.limit-meter__pending').css('width', (pending / total * 100) + '%');
                meter.find('.limit-meter__track').attr('aria-valuenow', Math.round(left / total * 100));
                // base color below 70% used, yellow from 70%, red when fully used / exceeded
                var full = over || left <= 0;
                meter.toggleClass('is-over', full).toggleClass('is-low', !full && (1 - left / total) >= 0.7);

                var note = '';
                if (over) note = "{{ __('Limit exceeded') }}";
                else if (amount > 0) note = "{{ __('This transaction') }}: " + gcFmt(amount) + ' ' + w.code;
                meter.find('.limit-meter__note').text(note);
            });

            $form.data('blocked', over_any ? "{{ __('Limit exceeded') }}" : '');
        }

        // ask the server for the remaining limits (debounced); the ajaxSuccess listener below reads the result
        function fetchLimits() {
            if (!chargeId || (!(dailyLimit > 0) && !(monthlyLimit > 0))) return;
            clearTimeout(limitTimer);
            limitTimer = setTimeout(function () {
                if (limitXhr) limitXhr.abort();
                var conversion = getUnitPrice() * getQty() * updateExchangeRate();
                limitXhr = $.ajax({
                    type: 'POST',
                    url: limitUrl,
                    data: {
                        _token:             $('meta[name="csrf-token"]').attr('content'),
                        user_field:         'user_id',
                        user_id:            @json(userGuard()['user']->id),
                        transaction_type:   @json(payment_gateway_const()::GIFTCARD),
                        currency_id:        wallet().id,
                        sender_amount:      isFinite(conversion) ? conversion : 0,
                        charge_id:          chargeId,
                        attribute:          @json(payment_gateway_const()::SEND),
                    }
                });
            }, 300);
        }
        $(document).ajaxSuccess(function (event, xhr, settings) {
            if (settings.url !== limitUrl) return;
            var data = (xhr.responseJSON || {}).data || {};
            if (data.remainingDailyTxnSelected !== undefined) limitRemaining.daily = parseFloat(data.remainingDailyTxnSelected);
            if (data.remainingMonthlyTxnSelected !== undefined) limitRemaining.monthly = parseFloat(data.remainingMonthlyTxnSelected);
            getPreview();
        });

        window.validateInputField = function () {
            var unitPrice = parseFloat($("input[name=g_unit_price]").val());
            if (!isFinite(unitPrice) || unitPrice <= 0) {
                throwMessage('error', ['{{ __("Amount Field Is Required") }}']);
                return false;
            }
            if (amountType === 'RANGE') {
                if (unitPrice < minLimit) {
                    throwMessage('error', ['{{ __("Please follow the mimimum limit") }}']);
                    return false;
                }
                if (unitPrice > maxLimit) {
                    throwMessage('error', ['{{ __("Please follow the maximum limit") }}']);
                    return false;
                }
            }
            var receiverEmail = $.trim($("input[name=receiver_email]").val());
            if (receiverEmail === '') {
                throwMessage('error', ['{{ __("Receiver Email Field Is Required") }}']);
                return false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(receiverEmail)) {
                throwMessage('error', ['{{ __("Invalid Email Format") }}']);
                return false;
            }
            var iso2 = $(".country-select :selected").data('iso2');
            if (iso2 === undefined || iso2 === '') {
                throwMessage('error', ['{{ __("Country Field Is Required") }}']);
                return false;
            }
            if ($.trim($("input[name=phone]").val()) === '') {
                throwMessage('error', ['{{ __("Receiver Phone Field Is Required") }}']);
                return false;
            }
            if ($.trim($("input[name=from_name]").val()) === '') {
                throwMessage('error', ['{{ __("From Name Field Is Required") }}']);
                return false;
            }
            return true;
        };

        function refresh() {
            getPreview();
            fetchLimits();
        }

        // quantity: digits only, minimum 1
        $('#quantityInput').on('input', function () {
            var digits = this.value.replace(/[^0-9]/g, '');
            if (digits !== this.value) this.value = digits;
        }).on('blur change', function () {
            this.value = getQty();
            refresh();
        });

        $('.gc-qty-btn').on('click', function () {
            $('#quantityInput').val(Math.max(getQty() + parseInt($(this).data('step'), 10), 1));
            refresh();
        });

        $('.enter-amount').on('focusout', function () {
            var value = parseFloat($(this).val());
            if (!isFinite(value)) return;
            if (value < minLimit) throwMessage('error', ['{{ __("Please follow the mimimum limit") }}']);
            else if (value > maxLimit) throwMessage('error', ['{{ __("Please follow the maximum limit") }}']);
        });

        $(document).on('input change', '.enter-amount, .radio_amount, #quantityInput, input[name=receiver_email], input[name=phone], input[name=from_name]', refresh);
        $(document).on('change', '.country-select', function () {
            placePhoneCode($(".country-select :selected").attr("data-mobile-code"));
            refresh();
        });
        $('select[name=currency]').on('change', function () {
            limitRemaining = { daily: null, monthly: null };
            senderBalance();
            refresh();
        });

        $form.on('submit', function (e) {
            getPreview();
            if (validateInputField() !== true) {
                e.preventDefault();
                return false;
            }
            if (!isFinite(parseFloat($("input[name=exchange_rate]").val()))) {
                e.preventDefault();
                throwMessage('error', ['{{ __("Exchange rate is not available. Please try again.") }}']);
                return false;
            }
            if ($form.data('blocked')) {
                e.preventDefault();
                throwMessage('error', [$form.data('blocked')]);
                return false;
            }
            if (pinEnabled && $.trim($form.find('input[name=pin]').val()) === '') {
                e.preventDefault();
                $('#checkPin').modal('show');
                return false;
            }
            $buyBtns.prop('disabled', true).text('{{ __("Processing") }}');
        });

        // re-enable when the page is restored from the back/forward cache
        $(window).on('pageshow', function (e) {
            if (e.originalEvent && e.originalEvent.persisted) {
                $buyBtns.prop('disabled', false);
                window.location.reload();
            }
        });

        $(document).ready(function () {
            getAllCountries("{{ setRoute('global.countries') }}");
            senderBalance();
            getPreview();
            getExchangeRate();
        });
    })();
</script>
@endpush
