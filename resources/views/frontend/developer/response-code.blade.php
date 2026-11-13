@extends('frontend.layouts.developer_master')

@php
    $codes = [
        [200, 'tone-green', __("OK"), __("The request succeeded."), 'SUCCESS, CREATED'],
        [400, 'tone-amber', __("Bad Request"), __("Validation failed or credentials are invalid."), 'Requested credentials is invalid'],
        [401, 'tone-amber', __("Unauthorized"), __("The access token has expired. Request a new one."), 'Request token is expired'],
        [403, 'tone-red', __("Forbidden"), __("The access token is missing or invalid."), 'Requested with invalid token!'],
        [404, 'tone-red', __("Not Found"), __("The merchant for these credentials was not found."), "Merchant doesn't exists or credentials is invalid"],
        [500, 'tone-red', __("Server Error"), __("Something went wrong on our side. Try again."), 'Failed to create payment! Please try again'],
    ];
@endphp

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Resources") }}</div>
    <h1 class="dx-title">{{ __("Response Codes") }}</h1>
    <p class="dx-lead">{{ __(@$basic_settings->site_name) }} {{__("API responses include standard HTTP status codes to indicate the success or failure of a request. Successful responses will have a status code of")}} <strong>{{ __("200 OK") }}</strong>, {{ __("while various error conditions will be represented by different status codes along with error messages in the response body.") }}</p>

    <div class="dx-table-wrap">
        <table class="dx-table">
            <thead>
                <tr><th>{{ __("Code") }}</th><th>{{ __("Meaning") }}</th><th>{{ __("Example message") }}</th></tr>
            </thead>
            <tbody>
                @foreach ($codes as $code)
                    <tr>
                        <td><span class="dx-badge {{ $code[1] }}">{{ $code[0] }}</span></td>
                        <td><strong>{{ $code[2] }}</strong><br><span class="text-muted">{{ $code[3] }}</span></td>
                        <td><code>{{ $code[4] }}</code></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>{{ __("Response format") }}</h2>
    <p>{{ __("Every response has the same shape. Check type first, then read data or message.error.") }}</p>
</div>

<div class="dx-article">
    @include('frontend.developer.partials.response', [
        'title' => __("Response format"),
        'responses' => [
            [200, __("Success"), ['message' => ['code' => 200, 'success' => ['SUCCESS']], 'data' => ['...' => '...'], 'type' => 'success']],
            [400, __("Error"), ['message' => ['code' => 400, 'error' => ['Error message']], 'data' => [], 'type' => 'error']],
        ],
    ])
</div>
@endsection
