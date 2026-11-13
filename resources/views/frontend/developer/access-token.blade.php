@extends('frontend.layouts.developer_master')

@php

    $snippets = \App\Http\Helpers\DeveloperDocs::request('POST', '/authentication/token', [
        'client_id' => 'YOUR_CLIENT_ID',
        'secret_id' => 'YOUR_SECRET_KEY',
    ]);

    $responses = [
        [200, __("Access token generated"), [
            'message' => ['code' => 200, 'success' => ['SUCCESS']],
            'data'    => [
                'access_token' => 'nyXPO8Re5SXP1c5gMqHbW6DQ5BfQdbYGpuWVjEQAP76SUT7YfdngoFzDGSNHTvmzq8AjPRrCyzxzukrJvOlSSwtAPAqjvAQJdse4YOnlHasD3vg6EYg6qyKxSiHeXBoRluD2NbZzxN3sAYVqd9q1XCAl7oaW3BbJl2ktEQWBUuNYMZPQaDyNEGwxoY389TCNJvxVcroveYxPJkYANvnaxOy16aE9Qp6EBClSjvK17WR3cJupTXlUhgw9ddpv1gDSlbDJvzKutrQX7XJqwk1GW1Dm6aK4PTn1D4mvMVqiOqQKigTzcEi2KPQnkoM86ONw3X8SxttFOfesdSwxKJMXuQpdnFHOjo',
                'expire_time'  => 600,
            ],
            'type'    => 'success',
        ]],
        [400, __("Invalid or mismatched credentials"), [
            'message' => ['code' => 400, 'error' => ['Requested credentials is invalid']],
            'data'    => [],
            'type'    => 'error',
        ]],
        [404, __("Merchant not found"), [
            'message' => ['code' => 404, 'error' => ["Merchant doesn't exists or credentials is invalid"]],
            'data'    => [],
            'type'    => 'error',
        ]],
    ];
@endphp

@section('content')
<div class="dx-api">
    <div class="dx-api-doc">
        <div class="dx-eyebrow">{{ __("API Reference") }}</div>
        <h1 class="dx-title">{{ __("Get Access Token") }}</h1>
        <p class="dx-lead">{{ __("Exchange your API keys for a short-lived access token. Every payment request needs a fresh token.") }}</p>

        @include('frontend.developer.partials.endpoint', ['method' => 'POST', 'path' => '/authentication/token'])

        @include('frontend.developer.partials.params', [
            'title'  => __("Body Parameters"),
            'params' => [
                ['client_id', 'string', true, __("Enter merchant API client/primary key"), null],
                ['secret_id', 'string', true, __("Enter merchant API secret key"), null],
            ],
        ])

        <h3>{{ __("Response fields") }}</h3>
        @include('frontend.developer.partials.params', [
            'params' => [
                ['data.access_token', 'string', true, __("Send this as a Bearer token when you create the payment."), null],
                ['data.expire_time', 'integer', true, __("Token lifetime in seconds."), '600'],
            ],
        ])

        <div class="dx-callout dx-callout--warn">
            <i class="las la-clock"></i>
            <p>{{ __("The token expires after 10 minutes and can create only one payment. Request a new token for every payment.") }}</p>
        </div>
        <div class="dx-callout dx-callout--info">
            <i class="las la-info-circle"></i>
            <p>{{ __("Sandbox keys work only with the sandbox base URL, and production keys only with the production base URL.") }}</p>
        </div>
    </div>

    <div class="dx-api-code">
        @include('frontend.developer.partials.code', ['snippets' => $snippets])
        @include('frontend.developer.partials.response', ['responses' => $responses])
    </div>
</div>
@endsection
