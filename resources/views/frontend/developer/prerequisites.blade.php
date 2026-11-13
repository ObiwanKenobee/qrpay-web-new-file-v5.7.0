@extends('frontend.layouts.developer_master')

@php
    $site = __(@$basic_settings->site_name);
@endphp

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Getting Started") }}</div>
    <h1 class="dx-title">{{ __("Prerequisites") }}</h1>
    <p class="dx-lead">{{ __("Before you begin integrating the") }} {{ $site }} {{ __("Developer API, make sure you have") }}:</p>
</div>

<div class="dx-cards">
    <div class="dx-card">
        <div class="dx-card-icon tone-purple"><i class="las la-store"></i></div>
        <h4>{{ __("Merchant account") }}</h4>
        <p>{{ __("An active") }} {{ $site }} {{ __("merchant account.") }}</p>
    </div>
    <div class="dx-card">
        <div class="dx-card-icon tone-blue"><i class="las la-laptop-code"></i></div>
        <h4>{{ __("API knowledge") }}</h4>
        <p>{{ __("Basic knowledge of REST APIs and JSON in any server-side language.") }}</p>
    </div>
    <div class="dx-card">
        <div class="dx-card-icon tone-green"><i class="las la-server"></i></div>
        <h4>{{ __("Secure server") }}</h4>
        <p>{{ __("A secure and accessible web server to handle API requests.") }}</p>
    </div>
</div>

<div class="dx-callout dx-callout--info dx-article">
    <i class="las la-info-circle"></i>
    <p>{{ __("Never call the API from a browser or mobile app. Your secret key must stay on your server.") }}</p>
</div>
@endsection
