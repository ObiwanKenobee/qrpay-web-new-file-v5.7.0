@extends('frontend.layouts.developer_master')

@php
    $lang = selectedLang();
    $intro_slug = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::DEVELOPER_INTRO);
    $intro = App\Models\Admin\SiteSections::getData( $intro_slug)->first();
    $intro_lang = @$intro->value->language->$lang;
@endphp

@section('content')
<div class="dx-hero">
    <div>
        <div class="dx-eyebrow">{{ __("Developer") }}</div>
        <h1 class="dx-title">{{ __(@$intro_lang->heading) }}</h1>
        <p class="dx-lead">{{ __(@$intro_lang->details) }}</p>
        <div class="dx-btns">
            <a href="{{ setRoute('developer.access.token') }}" class="dx-btn dx-btn--primary"><i class="las la-rocket"></i> {{ __("Get Started") }}</a>
            <a href="{{ setRoute('developer.examples') }}" class="dx-btn dx-btn--ghost"><i class="las la-code"></i> {{ __("Examples") }}</a>
        </div>
    </div>
    @if (@$intro->value->images->intro_image)
        <div class="dx-hero-media">
            <img src="{{ get_image(@$intro->value->images->intro_image,'site-section') }}" alt="image">
        </div>
    @endif
</div>

<div class="dx-article">
    <h2 id="introduction">{{ __("Introduction") }}</h2>
    <p>{{ __(@$intro_lang->intro_details) }}</p>

    <h2 id="quick-start">{{ __("Quick start") }}</h2>
    <p>{{ __("Accept your first payment in four steps.") }}</p>
</div>

<div class="dx-cards">
    <a href="{{ setRoute('developer.authentication') }}" class="dx-card">
        <div class="dx-card-icon tone-purple"><i class="las la-key"></i></div>
        <div class="dx-card-step">{{ __("Step") }} 1</div>
        <h4>{{ __("Get your API keys") }}</h4>
        <p>{{ __("Copy your client ID and secret key from the merchant dashboard.") }}</p>
    </a>
    <a href="{{ setRoute('developer.access.token') }}" class="dx-card">
        <div class="dx-card-icon tone-blue"><i class="las la-lock"></i></div>
        <div class="dx-card-step">{{ __("Step") }} 2</div>
        <h4>{{ __("Get Access Token") }}</h4>
        <p>{{ __("Exchange your keys for a short-lived access token.") }}</p>
    </a>
    <a href="{{ setRoute('developer.initiate.payment') }}" class="dx-card">
        <div class="dx-card-icon tone-green"><i class="las la-credit-card"></i></div>
        <div class="dx-card-step">{{ __("Step") }} 3</div>
        <h4>{{ __("Initiate Payment") }}</h4>
        <p>{{ __("Create a payment and redirect your customer to the checkout page.") }}</p>
    </a>
    <a href="{{ setRoute('developer.check.status.payment') }}" class="dx-card">
        <div class="dx-card-icon tone-amber"><i class="las la-check-circle"></i></div>
        <div class="dx-card-step">{{ __("Step") }} 4</div>
        <h4>{{ __("Check Payment Status") }}</h4>
        <p>{{ __("Read the payment result on your return URL and complete the order.") }}</p>
    </a>
</div>

<div class="dx-article">
    <h2 id="base-url">{{ __("Base URL") }}</h2>
    <div class="dx-env-row">
        <span class="dx-badge tone-amber">{{ __("Sandbox") }}</span>
        <code>{{ url('/') }}/pay/sandbox/api/v1</code>
    </div>
    <div class="dx-env-row">
        <span class="dx-badge tone-green">{{ __("Production") }}</span>
        <code>{{ url('/') }}/pay/api/v1</code>
    </div>
</div>
@endsection
