@extends('agent.layouts.master')
@php
   $pricison = get_precision($gateway);
@endphp
@push('css')

@endpush

@section('breadcrumb')
    @include('agent.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("agent.dashboard"),
        ]
    ], 'active' => __("Manual Payment")])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="dashboard-area mt-10">
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{__(@$page_title)}}</h3>
        </div>
    </div>
    <div class="row mb-30-none justify-content-center">
        <div class="col-lg-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">
                            @php
                                echo @$gateway->desc;
                            @endphp
                        </h5>
                    </div>
                    <div class="dash-payment-body">
                        <form class="card-form" action="{{ setRoute("agent.add.money.manual.payment.confirmed") }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                @foreach ($gateway->input_fields as $item)
                                @if ($item->type == "select")
                                    <div class="col-lg-12 form-group">
                                        <label for="{{ $item->name }}">{{ $item->label }}
                                            @if($item->required == true)
                                            <span class="text-danger">*</span>
                                            @else
                                            <span class="">( {{ __("Optional") }} )</span>
                                            @endif
                                        </label>
                                        <select name="{{ $item->name }}" id="{{ $item->name }}" class="form--control nice-select">
                                            <option selected disabled>Choose One</option>
                                            @foreach ($item->validation->options as $innerItem)
                                                <option value="{{ $innerItem }}">{{ $innerItem }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif ($item->type == "file")
                                    <div class="col-lg-12 form-group">
                                        <label for="{{ $item->name }}">{{ $item->label }}
                                            @if($item->required == true)
                                            <span class="text-danger">*</span>
                                            @else
                                            <span class="">( {{ __("Optional") }} )</span>
                                            @endif
                                        </label>
                                        <input type="{{ $item->type }}" class="form--control" name="{{ $item->name }}" value="{{ old($item->name) }}">
                                    </div>
                                @elseif ($item->type == "text")
                                    <div class="col-lg-12 form-group">
                                        <label for="{{ $item->name }}">{{ $item->label }}
                                            @if($item->required == true)
                                            <span class="text-danger">*</span>
                                            @else
                                            <span class="">( {{ __("Optional") }} )</span>
                                            @endif
                                        </label>
                                        <input type="{{ $item->type }}" class="form--control" placeholder="{{ ucwords(str_replace('_',' ', $item->name)) }}" name="{{ $item->name }}" value="{{ old($item->name) }}">
                                    </div>
                                @elseif ($item->type == "textarea")
                                    <div class="col-lg-12 form-group">
                                        @include('admin.components.form.textarea',[
                                            'label'     => $item->label,
                                            'name'      => $item->name,
                                            'value'     => old($item->name),
                                        ])
                                    </div>
                                @endif
                            @endforeach
                                <div class="col-xl-12 col-lg-12">
                                    <button type="submit" class="btn--base w-100 btn-loading"> {{ __("Confirm Payment") }}

                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{__("Payment Information")}}</h5>
                    </div>
                    <div class="dash-payment-body">
                        @php
                            $ps_amount = @$hasData->data->amount;
                            $ps_wp     = get_wallet_precision();
                        @endphp
                        @include('user.components.payment-summary', [
                            'gateway' => $gateway->name ?? null,
                            'rows' => [
                                ['icon' => 'las la-receipt',      'label' => __("Entered Amount"),       'value' => number_format((float) @$ps_amount->requested_amount, $ps_wp) . ' ' . @$ps_amount->default_currency],
                                ['icon' => 'las la-exchange-alt', 'label' => __("Exchange Rate"),        'value' => '1 ' . get_default_currency_code() . ' = ' . number_format((float) @$ps_amount->sender_cur_rate, $pricison) . ' ' . @$ps_amount->sender_cur_code],
                                ['icon' => 'las la-battery-half', 'label' => __("Total Fees & Charges"), 'value' => number_format((float) @$ps_amount->total_charge, $pricison) . ' ' . @$ps_amount->sender_cur_code, 'class' => 'text--warning'],
                                ['icon' => 'lab la-get-pocket',   'label' => __("Will Get"),             'value' => number_format((float) @$ps_amount->requested_amount, $ps_wp) . ' ' . @$ps_amount->default_currency, 'class' => 'text--success'],
                            ],
                            'total' => ['label' => __("Total Payable Amount"), 'value' => number_format((float) @$ps_amount->total_amount, $pricison) . ' ' . @$ps_amount->sender_cur_code],
                        ])
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection

@push('script')

@endpush
