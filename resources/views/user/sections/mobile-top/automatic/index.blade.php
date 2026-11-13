@extends('user.layouts.master')

@push('css')
    <style>
        .input-group.mobile-code .nice-select{
            border-radius: 5px 0 0 5px !important;
        }
        .input-group.mobile-code .nice-select .list{
            width: auto !important;
        }
        .input-group.mobile-code .nice-select .list::-webkit-scrollbar {
            height: 20px;
            width: 3px;
            background: #F1F1F1;
            border-radius: 10px;
        }

        .input-group.mobile-code .nice-select .list::-webkit-scrollbar-thumb {
            background: #999;
            border-radius: 10px;
        }

        .input-group.mobile-code .nice-select .list::-webkit-scrollbar-corner {
            background: #999;
            border-radius: 10px;
        }
    </style>
@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __(@$page_title)])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="dashboard-area mt-10">
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{__(@$page_title)}}</h3>
        </div>
    </div>
    <div class="row mb-30-none">
        <div class="col-xl-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{ __("Recharge") }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <form class="card-form" action="{{ setRoute('user.mobile.topup.automatic.pay') }}" method="POST">
                            @csrf
                            <input type="hidden" name="country_code">
                            <input type="hidden" name="phone_code">
                            <input type="hidden" name="exchange_rate">
                            <input type="hidden" name="operator">
                            <input type="hidden" name="operator_id">
                            <div class="row">
                                <div class="col-xl-12 col-lg-12 form-group text-center">
                                    <div class="exchange-area">
                                        <code class="d-block text-center"><span class="fees-show">--</span></code>
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12 form-group">
                                    <label>{{ __("Mobile Number") }}<span>*</span></label>
                                    <div class="input-group mobile-code">
                                        <select class="form--control nice-select" name="mobile_code">
                                            @foreach(freedom_countries(global_const()::USER) ?? [] as $key => $code)
                                                <option value="{{ $code->iso2 }}"
                                                    data-mobile-code="{{ remove_speacial_char($code->mobile_code) }}"
                                                    {{ $code->name === auth()->user()->address->country ? 'selected' :'' }}
                                                    >
                                                    {{ $code->name." (+".remove_speacial_char($code->mobile_code).")" }}
                                                </option>
                                            @endforeach

                                        </select>
                                        <input type="text" class="form--control number-input" name="mobile_number" placeholder="{{ __("enter Mobile Number") }}" value="{{ old('mobile_number') }}">
                                        <span class="btn-ring-input"></span>
                                    </div>

                                </div>
                                <div  class="add_item">

                                </div>
                                <div class="col-xl-12 col-lg-12 form-group">
                                    <div class="note-area">
                                        <code class="d-block fw-bold">{{ __("Available Balance") }}: {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                                    </div>
                                </div>

                                @if($basic_settings->user_pin_verification == true)
                                        <div class="col-xl-12 col-lg-12">
                                            <button type="button" class="btn--base w-100 btn-loading mobileTopupBtn" data-bs-toggle="modal" data-bs-target="#checkPin">{{ __("Recharge Now") }} <i class="fas fa-mobile ms-1"></i></button>
                                        </div>
                                    </div>
                                    @include('user.components.modal.pin-check')
                                @else
                                        <div class="col-xl-12 col-lg-12">
                                            <button type="submit" class="btn--base w-100 btn-loading mobileTopupBtn">{{ __("Recharge Now") }} <i class="fas fa-mobile ms-1"></i></button>
                                        </div>
                                    </div>
                                @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{ __("Preview") }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="preview-list-wrapper">
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-plug"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Operator Name") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="topup-type">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-phone-volume"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Mobile Number") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="mobile-number">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-hand-holding-usd"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="request-amount">--</span>
                                </div>
                            </div>

                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-exchange-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Exchange Rate") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="rate-show">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-hand-holding-usd"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Conversion Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--info conversion-amount">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-hand-holding-usd"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Total Charge") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="fees">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-hand-holding-usd"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Total Payable") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--base last payable-total">--</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            {{-- limit section  --}}
            <div class="dash-payment-item-wrapper limit">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{__("Limit Information")}}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="preview-list-wrapper">
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-wallet"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Transaction Limit") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="limit-show">--</span>
                                </div>
                            </div>
                            @if ($topupCharge->daily_limit > 0)
                                <div class="preview-list-item">
                                    <div class="preview-list-left">
                                        <div class="preview-list-user-wrapper">
                                            <div class="preview-list-user-icon">
                                                <i class="las la-wallet"></i>
                                            </div>
                                            <div class="preview-list-user-content">
                                                <span>{{ __("Daily Limit") }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preview-list-right">
                                        <span class="limit-daily">--</span>
                                    </div>
                                </div>
                                <div class="preview-list-item">
                                    <div class="preview-list-left">
                                        <div class="preview-list-user-wrapper">
                                            <div class="preview-list-user-icon">
                                                <i class="las la-wallet"></i>
                                            </div>
                                            <div class="preview-list-user-content">
                                                <span>{{ __("Remaining Daily Limit") }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preview-list-right">
                                        <span class="daily-remaining">--</span>
                                    </div>
                                </div>
                            @endif
                            @if ($topupCharge->monthly_limit > 0)
                                <div class="preview-list-item">
                                    <div class="preview-list-left">
                                        <div class="preview-list-user-wrapper">
                                            <div class="preview-list-user-icon">
                                                <i class="las la-wallet"></i>
                                            </div>
                                            <div class="preview-list-user-content">
                                                <span>{{ __("Monthly Limit") }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preview-list-right">
                                        <span class="limit-monthly">--</span>
                                    </div>
                                </div>
                                <div class="preview-list-item">
                                    <div class="preview-list-left">
                                        <div class="preview-list-user-wrapper">
                                            <div class="preview-list-user-icon">
                                                <i class="las la-wallet"></i>
                                            </div>
                                            <div class="preview-list-user-content">
                                                <span>{{ __("Remaining Monthly Limit") }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preview-list-right">
                                        <span class="monthly-remaining">--</span>
                                    </div>
                                </div>
                            @endif

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')

<script>
    var defualCurrency = "{{ get_default_currency_code() }}";
    var defualCurrencyRate = "{{ get_default_currency_rate() }}";
    var walletCurrencyType = "{{ get_default_currency()->type }}";
    var walletCurrencyId = "{{ get_default_currency()->id }}";

    $('.mobileTopupBtn').attr('disabled',true);
    $("select[name=mobile_code]").change(function(){
        if(acceptVar().mobileNumber != '' ){
            checkOperator();
        }
    });
    $("input[name=mobile_number]").focusout(function(){
        checkOperator();
    });
    $(document).on("click",".radio_amount",function(){
        preview();
    });
    $(document).on("focusout","input[name=amount]",function(){
        var operator =  JSON.parse($("input[name=operator]").val());
        var denominationType = operator.denominationType;
        if(denominationType === "RANGE"){
            enterLimit();
        }
        preview();
    });
    $(document).on("keyup","input[name=amount]",function(){
        preview();
    });
    function acceptVar() {
        var selectedMobileCode = $("select[name=mobile_code] :selected");
        var mobileNumber = $("input[name=mobile_number]").val();
        var currencyCode = defualCurrency;
        var currencyRate = defualCurrencyRate;
        var currencyMinAmount ="{{getAmount($topupCharge->min_limit)}}";
        var currencyMaxAmount = "{{getAmount($topupCharge->max_limit)}}";
        var currencyFixedCharge = "{{getAmount($topupCharge->fixed_charge)}}";
        var currencyPercentCharge = "{{getAmount($topupCharge->percent_charge)}}";
        var currencyDailyLimit      = "{{getAmount($topupCharge->daily_limit)}}";
        var currencyMonthlyLimit      = "{{getAmount($topupCharge->monthly_limit)}}";

        if(walletCurrencyType == "CRYPTO"){
            var senderPrecison = "{{ get_precision_from_admin()['crypto_precision_value'] }}";
        }else{
            var senderPrecison = "{{  get_precision_from_admin()['fiat_precision_value'] }}";
        }

        return {
            selectedMobileCode:selectedMobileCode,
            mobileNumber:mobileNumber,
            currencyCode:currencyCode,
            currencyRate:currencyRate,
            currencyMinAmount:currencyMinAmount,
            currencyMaxAmount:currencyMaxAmount,
            currencyFixedCharge:currencyFixedCharge,
            currencyPercentCharge:currencyPercentCharge,

            sPrecison:senderPrecison,
            currencyDailyLimit:currencyDailyLimit,
            currencyMonthlyLimit:currencyMonthlyLimit,

        };
    }
    function checkOperator() {
        var url = '{{ route('user.mobile.topup.automatic.check.operator') }}';
        var mobile_code = acceptVar().selectedMobileCode.data('mobile-code');
        var phone = acceptVar().mobileNumber;
        var iso = acceptVar().selectedMobileCode.val();
        var token = '{{ csrf_token() }}';

        var data = {_token: token, mobile_code: mobile_code, phone: phone, iso: iso};

        $.post(url, data, function(response) {
            $('.btn-ring-input').show();
            if(response.status === true){
                var response_data = response.data;
                var exchangeRate =  parseFloat($("input[name=exchange_rate]").val());

                var destination_currency_code = response_data.destinationCurrencyCode;
                var destination_currency_symbol = response_data.destinationCurrencySymbol;
                var denominationType = response_data.denominationType;
                var destination_exchange_rate = response_data.fx.rate;
                $('.add_item').empty();
                $('.limit-show').empty();

                var minAmount = 0;
                var maxAmount = 0;
                if(denominationType === "RANGE"){
                    var senderCurrencyCode = response_data.senderCurrencyCode;
                    var supportsLocalAmounts = response_data.supportsLocalAmounts;
                    if(supportsLocalAmounts == true && destination_currency_code == senderCurrencyCode && response_data.localMinAmount == null && response_data.localMaxAmount == null){
                        minAmount = response_data.minAmount;
                        maxAmount = response_data.maxAmount;
                    }else if(supportsLocalAmounts == true && response_data.localMinAmount != null && response_data.localMaxAmount != null){
                        minAmount = response_data.localMinAmount;
                        maxAmount = response_data.localMaxAmount;

                    }else{
                        minAmount = response_data.minAmount;
                        maxAmount = response_data.maxAmount;
                    }

                    // Append the HTML code to the .add_item div for RANGE
                    $('.add_item').html(`
                        <div class="col-xxl-12 col-xl-12 col-lg-12 form-group">
                            <label>{{ __("Amount") }}<span>*</span></label>
                            <div class="input-group">
                                <input type="text" class="form--control number-input" required placeholder="{{__('enter Amount')}}" name="amount" value="{{ old("amount") }}">
                                <select class="form--control nice-select currency" name="currency">
                                    <option value="${destination_currency_code}">${destination_currency_code}</option>
                                </select>
                            </div>
                        </div>
                    `);
                    $("select[name=currency]").niceSelect();

                    $('.limit-show').html(`
                        <span class="limit-show"> ${minAmount+" "+destination_currency_code+" - "+maxAmount+" "+destination_currency_code}</span>
                    `);
                }else if(denominationType === "FIXED"){
                    var fixedAmounts = response_data.fixedAmounts;
                    // Multiply each value in fixedAmounts array by destination_exchange_rate
                    var multipliedAmounts = fixedAmounts.map(function(amount) {
                        return (amount * destination_exchange_rate).toFixed(acceptVar().sPrecison); // Set precision to two decimal places
                    });
                    // Generate radio input fields for each multiplied amount
                    var radioInputs = '';
                    $.each(multipliedAmounts, function(index, amount) {
                        // Check the first radio button by default
                        var checked = index === 0 ? 'checked' : '';
                        radioInputs += `
                            <div class="gift-card-radio-item">
                                <input type="radio" id="level-${index}" name="amount" value="${amount}" onclick="handleRadioClick(this)" class="radio_amount" ${checked}>
                                <label for="level-${index}">${amount} ${destination_currency_code}</label>
                            </div>
                        `;
                    });
                    // Append the HTML code to the .add_item div for FIXED with radio input fields
                    $('.add_item').html(`
                        <div class="col-xl-12 mb-20">
                            <label>{{ __("Amount") }}<span>*</span></label>
                            <div class="gift-card-radio-wrapper">
                                ${radioInputs}
                            </div>
                        </div>
                    `);

                }
                $("input[name=operator]").val(JSON.stringify(response_data));
                feesCalculation();
                getFee();
                getExchangeRate();
                // preview();
                if(denominationType === "FIXED"){
                    var firstRadio = $('input[type="radio"]:first');
                    firstRadio.prop('checked', true);
                    handleRadioClick(firstRadio[0]);
                }
                $('.mobileTopupBtn').attr('disabled',false);
                setTimeout(function() {
                    $('.btn-ring-input').hide();
                },1000);
            }else if(response.status === false && response.from === "error"){
                $('.add_item, .limit-show').empty();
                $('.fees-show, .rate-show, .topup-type, .mobile-number, .request-amount, .conversion-amount, .fees, .payable-total').html('--');
                $('input[name=phone_code], input[name=country_code],input[name=operator],input[name=operator_id],input[name=exchange_rate]').val('');
                $('.mobileTopupBtn').attr('disabled',true);
                setTimeout(function() {
                    $('.btn-ring-input').hide();
                    throwMessage('error',[response.message]);
                },1000);
                return false;
            }
        });
    }
    function feesCalculation() {
        var currencyCode = acceptVar().currencyCode;
        var currencyRate = acceptVar().currencyRate;
        var sender_amount = parseFloat(get_amount());
        sender_amount == "" ? (sender_amount = 0) : (sender_amount = sender_amount);

        var fixed_charge = acceptVar().currencyFixedCharge;
        var percent_charge = acceptVar().currencyPercentCharge;
        if ($.isNumeric(percent_charge) && $.isNumeric(fixed_charge) && $.isNumeric(sender_amount)) {
            // Process Calculation
            var fixed_charge_calc = parseFloat(currencyRate * fixed_charge);
            var percent_charge_calc = (parseFloat(sender_amount) / 100) * parseFloat(percent_charge);
            var total_charge = parseFloat(fixed_charge_calc) + parseFloat(percent_charge_calc);
            total_charge = parseFloat(total_charge).toFixed(acceptVar().sPrecison);
            // return total_charge;
            return {
                total: total_charge,
                fixed: fixed_charge_calc,
                percent: percent_charge,
            };
        } else {
            // return "--";
            return false;
        }
    }
    function getFee(){
        var currencyCode = acceptVar().currencyCode;
        var percent = acceptVar().currencyPercentCharge;
        var charges = feesCalculation();
        if (charges == false) {
            return false;
        }
        $(".fees-show").html("{{ __('TopUp Fee') }}: " + parseFloat(charges.fixed).toFixed(acceptVar().sPrecison) + " " + currencyCode + " + " + parseFloat(charges.percent).toFixed(acceptVar().sPrecison) + "%  ");

    }
    function getExchangeRate() {
            var walletCurrencyCode = acceptVar().currencyCode;
            var walletCurrencyRate = acceptVar().currencyRate;
            var operator =  JSON.parse($("input[name=operator]").val());
            var destination_currency_code = operator.destinationCurrencyCode;
            var denominationType = operator.denominationType;
            $.ajax({
                type:'get',
                    url:"{{ route('global.receiver.wallet.currency') }}",
                    data:{code:destination_currency_code},
                    success:function(data){
                        var receiverCurrencyCode = data.currency_code;
                        var receiverCurrencyRate = data.rate;
                        var exchangeRate = (walletCurrencyRate/receiverCurrencyRate);
                        $("input[name=exchange_rate]").val(exchangeRate);
                        $('.rate-show').html("1 " +receiverCurrencyCode + " = " + parseFloat(exchangeRate).toFixed(8) + " " + walletCurrencyCode);

                        if(denominationType === "RANGE"){
                            getLimit();
                            getDailyMonthlyLimit();
                            get_remaining_limits();

                        }

                        preview();
                    }
            });

    }
    function handleRadioClick(radio) {
            if (radio.checked) {
                amount = parseFloat(radio.value);
                $('.mobileTopupBtn').attr('disabled',false);

            }
        }
    function preview(){
        var sender_currency = acceptVar().currencyCode;
        var operator =  JSON.parse($("input[name=operator]").val());
        var destination_currency_code = operator.destinationCurrencyCode;
        var destination_fixed = operator.fees.local;
        var destination_percent = operator.fees.localPercentage;
        var exchangeRate =  parseFloat($("input[name=exchange_rate]").val());
        var senderAmount = parseFloat(get_amount());
        senderAmount == "" ? senderAmount = 0 : senderAmount = senderAmount;

        var conversion_amount = parseFloat(senderAmount) * parseFloat(exchangeRate);
        var phone_code = acceptVar().selectedMobileCode.data('mobile-code');
        var phone = "+"+phone_code+acceptVar().mobileNumber;
       // Fees
       var charges = feesCalculation();
        var total_charge = 0;
        if(senderAmount == 0){
            total_charge = 0;
        }else{
            total_charge = parseFloat(charges.total);
        }

        var payable = conversion_amount + total_charge;

        $('.topup-type').text(operator.name);
        $('.mobile-number').text(phone);
        $('.request-amount').text(parseFloat(senderAmount).toFixed(acceptVar().sPrecison) + " " + destination_currency_code);
        $('.conversion-amount').text(parseFloat(conversion_amount).toFixed(acceptVar().sPrecison) + " " + sender_currency);
        $('.fees').text(parseFloat(total_charge).toFixed(acceptVar().sPrecison) + " " + sender_currency);
        $('.payable-total').text(parseFloat(payable).toFixed(acceptVar().sPrecison) + " " + sender_currency);
        //hidden filed fill ups
        $('input[name=phone_code]').val(phone_code);
        $('input[name=country_code]').val(acceptVar().selectedMobileCode.val());
        $('input[name=operator_id]').val(operator.operatorId);

    }
    var amount = 0;
    function get_amount(){
        var operator =  JSON.parse($("input[name=operator]").val());
        var denominationType = operator.denominationType;
        if(denominationType === "RANGE"){
            amount =  amount = parseFloat($("input[name=amount]").val());
            if (!($.isNumeric(amount))) {
                amount = 0;
            }else{
                amount = amount;
            }
        }else{
            amount = amount;
        }
        return amount;
    }
    function enterLimit() {

        var exchangeRate =  parseFloat($("input[name=exchange_rate]").val());
        var operator =  JSON.parse($("input[name=operator]").val());

        // Get sender amount and ensure it is a valid number
        let senderAmount = parseFloat(get_amount()) || 0;
        senderAmount = parseFloat(senderAmount); // Convert to float for comparison

        var minAmount = 0;
        var maxAmount = 0;
        var destination_currency_code = operator.destinationCurrencyCode;
        var senderCurrencyCode = operator.senderCurrencyCode;
        var supportsLocalAmounts = operator.supportsLocalAmounts;

        if(supportsLocalAmounts == true && destination_currency_code == senderCurrencyCode && operator.localMinAmount == null && operator.localMaxAmount == null){
            minAmount = parseFloat(operator.minAmount);
            maxAmount = parseFloat(operator.maxAmount);
        }else if(supportsLocalAmounts == true && operator.localMinAmount != null && operator.localMaxAmount != null){
            minAmount = parseFloat(operator.localMinAmount);
            maxAmount = parseFloat(operator.localMaxAmount);

        }else{
            minAmount = parseFloat(operator.minAmount);
            maxAmount = parseFloat(operator.maxAmount);
        }

        if(supportsLocalAmounts == true){
            var minAmount = parseFloat(minAmount);
            var maxAmount = parseFloat(maxAmount);
        }else{
            var fxRate = operator.fx.rate;
            var minAmount = parseFloat(minAmount*fxRate);
            var maxAmount = parseFloat(maxAmount*fxRate);
        }

        // Validate sender amount against limits
        if (senderAmount < minAmount) {
            throwMessage('error', ['{{ __("Please follow the minimum limit") }}']);
            $('.mobileTopupBtn').attr('disabled', true);
        } else if (senderAmount > maxAmount) {
            throwMessage('error', ['{{ __("Please follow the maximum limit") }}']);
            $('.mobileTopupBtn').attr('disabled', true);
        } else {
            $('.mobileTopupBtn').attr('disabled', false);
        }
    }


    function getLimit(){
        var exchangeRate =  parseFloat($("input[name=exchange_rate]").val());
        var operator =  JSON.parse($("input[name=operator]").val());

        var minAmount = 0;
        var maxAmount = 0;
        var destination_currency_code = operator.destinationCurrencyCode;
        var senderCurrencyCode = operator.senderCurrencyCode;
        var supportsLocalAmounts = operator.supportsLocalAmounts;

        if(supportsLocalAmounts == true && destination_currency_code == senderCurrencyCode && operator.localMinAmount == null && operator.localMaxAmount == null){
            minAmount = parseFloat(operator.minAmount);
            maxAmount = parseFloat(operator.maxAmount);
        }else if(supportsLocalAmounts == true && operator.localMinAmount != null && operator.localMaxAmount != null){
            minAmount = parseFloat(operator.localMinAmount);
            maxAmount = parseFloat(operator.localMaxAmount);

        }else{
            minAmount = parseFloat(operator.minAmount);
            maxAmount = parseFloat(operator.maxAmount);
        }



        if($.isNumeric(minAmount) && $.isNumeric(maxAmount)) {
            if(supportsLocalAmounts == true){
                var min_limit_calc = parseFloat(minAmount).toFixed(acceptVar().sPrecison);
                var max_limit_clac = parseFloat(maxAmount).toFixed(acceptVar().sPrecison);
            }else{
                var fxRate = operator.fx.rate;
                var min_limit_calc = parseFloat(minAmount*fxRate).toFixed(acceptVar().sPrecison);
                var max_limit_clac = parseFloat(maxAmount*fxRate).toFixed(acceptVar().sPrecison);
            }
            console.log(min_limit_calc, max_limit_clac);


            $('.limit-show').html(`
                    <span class="limit-show"> ${min_limit_calc+" "+destination_currency_code+" - "+max_limit_clac+" "+destination_currency_code}</span>
                `);
            return {
                minLimit:min_limit_calc,
                maxLimit:max_limit_clac,
            };
        }else {
            $('.limit-show').html("--");
            return {
                minLimit:0,
                maxLimit:0,
            };
        }
    }
    function getDailyMonthlyLimit(){
        var exchangeRate =  parseFloat($("input[name=exchange_rate]").val());
        var operator =  JSON.parse($("input[name=operator]").val());
        var sender_currency = operator.destinationCurrencyCode;
        var daily_limit = acceptVar().currencyDailyLimit;
        var monthly_limit = acceptVar().currencyMonthlyLimit

        if($.isNumeric(daily_limit) && $.isNumeric(monthly_limit)) {
            if(daily_limit > 0 ){
                var daily_limit_calc = parseFloat(daily_limit / exchangeRate).toFixed(acceptVar().sPrecison);
                $('.limit-daily').html( daily_limit_calc + " " + sender_currency);
            }else{
                $('.limit-daily').html("");
            }

            if(monthly_limit > 0 ){
                var montly_limit_clac = parseFloat(monthly_limit / exchangeRate).toFixed(acceptVar().sPrecison);
                $('.limit-monthly').html( montly_limit_clac + " " + sender_currency);

            }else{
                $('.limit-monthly').html("");
            }

        }else {
            $('.limit-daily').html("--");
            $('.limit-monthly').html("--");
            return {
                dailyLimit:0,
                monthlyLimit:0,
            };
        }

    }
    function get_remaining_limits(){
        var csrfToken           = $('meta[name="csrf-token"]').attr('content');
        var user_field          = "user_id";
        var user_id             = "{{ userGuard()['user']->id }}";
        var transaction_type    = "{{ payment_gateway_const()::MOBILETOPUP }}";
        var currency_id         = walletCurrencyId;
        var sender_amount       = parseFloat(get_amount());
        var exchangeRate =  parseFloat($("input[name=exchange_rate]").val());
        var operator =  JSON.parse($("input[name=operator]").val());

        (sender_amount == "" || isNaN(sender_amount)) ? sender_amount = 0 : sender_amount = sender_amount;

        var charge_id           = "{{ $topupCharge->id }}";
        var attribute           = "{{ payment_gateway_const()::SEND }}"

        $.ajax({
            type: 'POST',
            url: "{{ route('global.get.total.transactions') }}",
            data: {
                _token:             csrfToken,
                user_field:         user_field,
                user_id:            user_id,
                transaction_type:   transaction_type,
                currency_id:        currency_id,
                sender_amount:      sender_amount,
                charge_id:          charge_id,
                attribute:          attribute,
                rate:               exchangeRate,
            },
            success: function(response) {
                var sender_currency = operator.destinationCurrencyCode;
                var status  = response.status;
                var message = response.message;
                var amount_data = response.data;

                if(status == false){
                    $('.mobileTopupBtn').attr('disabled',true);
                    $('.daily-remaining').html(amount_data.remainingDailyTxnSelected + " " + sender_currency);
                    $('.monthly-remaining').html(amount_data.remainingMonthlyTxnSelected + " " + sender_currency);
                    throwMessage('error',[message]);
                    return false;
                }else{
                    $('.mobileTopupBtn').attr('disabled',false);
                    $('.daily-remaining').html(amount_data.remainingDailyTxnSelected + " " + sender_currency);
                    $('.monthly-remaining').html(amount_data.remainingMonthlyTxnSelected + " " + sender_currency);
                }
            },
        });
    }

</script>

@endpush
