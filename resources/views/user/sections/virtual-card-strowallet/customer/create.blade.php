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
    // pre-fill from the user's profile
    $user_country = $user->address->country ?? "";
    $country_iso3 = collect(get_all_countries() ?? [])->firstWhere('name', $user_country)->iso3 ?? '';
    $d = [
        'name'          => $user->fullname ?? "",
        'first_name'    => $user->firstname ?? "",
        'last_name'     => $user->lastname ?? "",
        'email'         => $user->email ?? "",
        'line1'         => $user->address->address ?? "",
        'city'          => $user->address->city ?? "",
        'state'         => $user->address->state ?? "",
        'postal_code'   => $user->address->zip ?? "",
        'country'       => $country_iso3,
        'phone_code'    => $user->mobile_code ?? "",
        'phone'         => $user->mobile ?? "",
    ];
@endphp

@section('content')
<div class="body-wrapper">
    @include('user.sections.virtual-card-strowallet.customer.partials.form', ['d' => $d, 'customer_kyc' => null, 'locked' => false])
</div>
@endsection
