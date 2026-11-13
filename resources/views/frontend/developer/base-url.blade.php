@extends('frontend.layouts.developer_master')

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Getting Started") }}</div>
    <h1 class="dx-title">{{ __("Base URL") }}</h1>
    <p class="dx-lead">{{ __("The base URL for API requests is") }}:</p>

    <div class="dx-env-row">
        <span class="dx-badge tone-amber">{{ __("Sandbox") }}</span>
        <code>{{ url('/') }}/pay/sandbox/api/v1</code>
        <button type="button" class="dx-copy" data-copy-text="{{ url('/') }}/pay/sandbox/api/v1" title="{{ __('Copy') }}"><i class="las la-copy"></i></button>
    </div>
    <div class="dx-env-row">
        <span class="dx-badge tone-green">{{ __("Production") }}</span>
        <code>{{ url('/') }}/pay/api/v1</code>
        <button type="button" class="dx-copy" data-copy-text="{{ url('/') }}/pay/api/v1" title="{{ __('Copy') }}"><i class="las la-copy"></i></button>
    </div>

    <h2>{{ __("Environments") }}</h2>
    <div class="dx-table-wrap">
        <table class="dx-table">
            <thead>
                <tr><th>{{ __("Mode") }}</th><th>{{ __("Use it for") }}</th><th>{{ __("Money moves") }}</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="dx-badge tone-amber">{{ __("Sandbox") }}</span></td>
                    <td>{{ __("Building and testing your integration") }}</td>
                    <td>{{ __("No") }}</td>
                </tr>
                <tr>
                    <td><span class="dx-badge tone-green">{{ __("Production") }}</span></td>
                    <td>{{ __("Live payments from real customers") }}</td>
                    <td>{{ __("Yes") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="dx-callout dx-callout--tip">
        <i class="las la-exchange-alt"></i>
        <p>{{ __("Use the Sandbox / Production switch at the top of the page. Every code sample on this site updates to the selected base URL.") }}</p>
    </div>
</div>
@endsection
