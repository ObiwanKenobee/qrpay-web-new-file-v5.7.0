@extends('frontend.layouts.developer_master')

@php
    $practices = [
        ['las la-lock', 'tone-green', __("Use HTTPS"), __("Use secure HTTPS connections for all API requests.")],
        ['las la-user-secret', 'tone-purple', __("Keep keys secret"), __("Store your keys in environment variables. Never put them in frontend code or a public repository.")],
        ['las la-sync', 'tone-blue', __("One token per payment"), __("Request a new access token for every payment. Tokens expire after 10 minutes.")],
        ['las la-check-double', 'tone-amber', __("Verify the result"), __("Match the token or custom value and the amount with your order before you deliver it.")],
        ['las la-bug', 'tone-red', __("Handle errors"), __("Implement robust error handling to handle potential issues gracefully.")],
        ['las la-flask', 'tone-teal', __("Test in sandbox"), __("Test every flow in sandbox, including cancel and failed payments, before going live.")],
        ['las la-redo-alt', 'tone-blue', __("Stay up to date"), __("Regularly update your integration to stay current with any API changes or enhancements.")],
        ['las la-file-alt', 'tone-purple', __("Log requests"), __("Log the token, trx_id and response of each payment. It makes support much faster.")],
    ];
@endphp

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Resources") }}</div>
    <h1 class="dx-title">{{ __("Best Practices") }}</h1>
    <p class="dx-lead">{{ __("To ensure a smooth integration process and optimal performance, follow these best practices") }}:</p>
</div>

<div class="dx-cards">
    @foreach ($practices as $item)
        <div class="dx-card">
            <div class="dx-card-icon {{ $item[1] }}"><i class="{{ $item[0] }}"></i></div>
            <h4>{{ $item[2] }}</h4>
            <p>{{ $item[3] }}</p>
        </div>
    @endforeach
</div>
@endsection
