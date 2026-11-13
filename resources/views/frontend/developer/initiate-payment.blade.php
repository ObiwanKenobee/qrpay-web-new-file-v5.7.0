@extends('frontend.layouts.developer_master')

@php

    $snippets = \App\Http\Helpers\DeveloperDocs::request('POST', '/payment/create', [
        'amount'     => '100.00',
        'currency'   => 'USD',
        'return_url' => 'https://example.com/success',
        'cancel_url' => 'https://example.com/cancel',
        'custom'     => 'ORDER-123456789',
    ], 'YOUR_ACCESS_TOKEN');

    $token = '2zMRmT3KeYT2BWMAyGhqEfuw4tOYOfGXKeyKqehZ8mF1E35hMwE69gPpyo3e';
    $responses = [
        [200, __("Payment created"), [
            'message' => ['code' => 200, 'success' => ['CREATED']],
            'data'    => [
                'token'       => $token,
                'payment_url' => url('/') . '/pay/sandbox/v1/user/authentication/form/' . $token,
            ],
            'type'    => 'success',
        ]],
        [400, __("Validation failed"), [
            'message' => ['code' => 400, 'error' => ['The return url field must be a valid URL.']],
            'data'    => [],
            'type'    => 'error',
        ]],
        [401, __("Access token expired"), [
            'message' => ['code' => 401, 'error' => ['Request token is expired']],
            'data'    => [],
            'type'    => 'error',
        ]],
        [403, __("Missing or invalid access token"), [
            'message' => ['code' => 403, 'error' => ['Requested with invalid token!']],
            'data'    => [],
            'type'    => 'error',
        ]],
    ];
@endphp

@section('content')
<div class="dx-api">
    <div class="dx-api-doc">
        <div class="dx-eyebrow">{{ __("API Reference") }}</div>
        <h1 class="dx-title">{{ __("Initiate Payment") }}</h1>
        <p class="dx-lead">{{ __("Create a payment and get a checkout link. Send your customer to the payment_url to complete the payment.") }}</p>

        @include('frontend.developer.partials.endpoint', ['method' => 'POST', 'path' => '/payment/create'])

        @include('frontend.developer.partials.params', [
            'title'  => __("Headers"),
            'params' => [
                ['Authorization', 'string', true, __("Bearer token from the Access Token endpoint."), 'Bearer YOUR_ACCESS_TOKEN'],
            ],
        ])

        @include('frontend.developer.partials.params', [
            'title'  => __("Body Parameters"),
            'params' => [
                ['amount', 'decimal', true, __("Your Amount , Must be rounded at 2 precision."), '100.00'],
                ['currency', 'string', true, __("Currency Code, Must be in Upper Case (Alpha-3 code)"), 'USD'],
                ['return_url', 'url', true, __("Enter your return or success URL"), 'https://example.com/success'],
                ['cancel_url', 'url', true, __("Enter your cancel or failed URL"), 'https://example.com/cancel'],
                ['custom', 'string', false, __("Transaction id which can be used your project transaction"), 'ORDER-123456789'],
            ],
        ])

        <h3>{{ __("Payment flow") }}</h3>
        <ol>
            <li>{{ __("Create the payment with this endpoint.") }}</li>
            <li>{{ __("Redirect your customer to") }} <code>data.payment_url</code>.</li>
            <li>{{ __("The customer logs in and confirms the payment.") }}</li>
            <li>{{ __("The customer is sent back to your return_url with the payment result.") }} <a href="{{ setRoute('developer.check.status.payment') }}">{{ __("Read more") }}</a></li>
        </ol>

        <div class="dx-callout dx-callout--tip">
            <i class="las la-lightbulb"></i>
            <p>{{ __("Store the returned token and your custom value with your order. You will use them to match the payment result.") }}</p>
        </div>
    </div>

    <div class="dx-api-code">
        @include('frontend.developer.partials.code', ['snippets' => $snippets])
        @include('frontend.developer.partials.response', ['responses' => $responses])
    </div>
</div>
@endsection
