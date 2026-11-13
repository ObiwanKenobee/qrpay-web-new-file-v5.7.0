@extends('frontend.layouts.developer_master')

@php
    $site = __(@$basic_settings->site_name);
@endphp

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Getting Started") }}</div>
    <h1 class="dx-title">{{ __("Authentication") }}</h1>
    <p class="dx-lead">{{ __("To access the") }} {{ $site }} {{ __("Developer API, you’ll need an API key. You can obtain your API key by logging in to your") }} {{ $site }} {{ __("merchant account and navigating to the API section. Collect") }} <strong>{{ __("Client/Primary Key") }}</strong> &amp; <strong>{{ __("secret Key") }}</strong> {{ __("Carefully. Keep your API key confidential and do not share it publicly.") }}</p>

    <h2>{{ __("How it works") }}</h2>
    <div class="dx-table-wrap">
        <table class="dx-table">
            <thead>
                <tr><th>{{ __("Step") }}</th><th>{{ __("What you send") }}</th><th>{{ __("What you get") }}</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="dx-badge tone-purple">1</span></td>
                    <td><code>client_id</code> + <code>secret_id</code> → <code>/authentication/token</code></td>
                    <td><code>access_token</code> ({{ __("valid for 10 minutes") }})</td>
                </tr>
                <tr>
                    <td><span class="dx-badge tone-blue">2</span></td>
                    <td><code>Authorization: Bearer access_token</code> → <code>/payment/create</code></td>
                    <td><code>payment_url</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>{{ __("Sandbox and production keys") }}</h2>
    <p>{{ __("Your merchant dashboard has a separate key pair for each mode. Use sandbox keys while testing and switch to production keys when you go live.") }}</p>

    <div class="dx-btns">
        <a href="{{ setRoute('merchant.developer.api.index') }}" class="dx-btn dx-btn--primary"><i class="las la-key"></i> {{ __("Get your API keys") }}</a>
        <a href="{{ setRoute('developer.access.token') }}" class="dx-btn dx-btn--ghost">{{ __("Access Token") }} <i class="las la-arrow-right"></i></a>
    </div>

    <div class="dx-callout dx-callout--warn">
        <i class="las la-user-plus"></i>
        <p>{{ __("If you don't have any merchant account please") }} <a href="{{ setRoute('merchant.register') }}">{{ __("Register") }}</a> {{ __("to continue") }}</p>
    </div>
</div>
@endsection
