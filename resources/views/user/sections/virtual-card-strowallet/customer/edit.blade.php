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
    // Once Strowallet accepted the card holder (customer_id exists) it can not be changed
    $card_holder_locked = !empty($customer_kyc->customer_id);
    $d = [
        'name'                      => $customer_kyc->name ?? "",
        'first_name'                => $customer_kyc->first_name ?? "",
        'last_name'                 => $customer_kyc->last_name ?? "",
        'dob'                       => optional($customer_kyc->dob ?? null)->format('Y-m-d'),
        'place_of_birth'            => $customer_kyc->place_of_birth ?? "",
        'id_type'                   => $customer_kyc->id_type ?? "",
        'id_number'                 => $customer_kyc->id_number ?? "",
        'email'                     => $customer_kyc->email ?? auth()->user()->email,
        'line1'                     => $customer_kyc->line1 ?? "",
        'city'                      => $customer_kyc->city ?? "",
        'state'                     => $customer_kyc->state ?? "",
        'postal_code'               => $customer_kyc->postal_code ?? "",
        'country'                   => $customer_kyc->country ?? "",
        'phone_code'                => $customer_kyc->phone_code ?? auth()->user()->mobile_code,
        'phone'                     => $customer_kyc->phone ?? auth()->user()->mobile,
        'account_purpose'           => $customer_kyc->account_purpose ?? "",
        'employment_status'         => $customer_kyc->employment_status ?? "",
        'occupation'                => $customer_kyc->occupation ?? "",
        'annual_salary'             => isset($customer_kyc->annual_salary) ? getAmount($customer_kyc->annual_salary, 2) : "",
        'expected_monthly_volume'   => isset($customer_kyc->expected_monthly_volume) ? getAmount($customer_kyc->expected_monthly_volume, 2) : "",
    ];
@endphp

@section('content')
<div class="body-wrapper">
    @include('user.sections.virtual-card-strowallet.customer.partials.form', ['d' => $d, 'customer_kyc' => $customer_kyc, 'locked' => $card_holder_locked])
</div>
@endsection
