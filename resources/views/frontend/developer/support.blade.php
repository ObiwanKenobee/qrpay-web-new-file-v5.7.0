@extends('frontend.layouts.developer_master')

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Resources") }}</div>
    <h1 class="dx-title">{{ __("Support") }}</h1>
    <p class="dx-lead">{{ __("If you encounter any issues or need assistance, please reach out to our dedicated developer support team") }}</p>
</div>

<div class="dx-cards">
    <a href="{{ setRoute('contact') }}" class="dx-card">
        <div class="dx-card-icon tone-blue"><i class="las la-headset"></i></div>
        <h4>{{ __("Contact Us") }}</h4>
        <p>{{ __("Send us your question with the token or trx_id of the payment.") }}</p>
    </a>
    <a href="{{ setRoute('developer.faq') }}" class="dx-card">
        <div class="dx-card-icon tone-purple"><i class="las la-question-circle"></i></div>
        <h4>{{ __("FAQ") }}</h4>
        <p>{{ __("Answers to the most common integration questions.") }}</p>
    </a>
    <a href="https://github.com/appdevsx/QRPay-Gateway-Example.git" target="_blank" rel="noopener" class="dx-card">
        <div class="dx-card-icon tone-green"><i class="lab la-github"></i></div>
        <h4>{{ __("Examples") }}</h4>
        <p>{{ __("A ready-made project you can clone and run.") }}</p>
    </a>
</div>

<div class="dx-article">
    <p>{{ __("Thank you for choosing") }} {{ __( @$basic_settings->site_name) }} {{ __("Payment Gateway Solutions! We look forward to seeing your integration thrive and provide a seamless payment experience for your valued customers.") }}</p>
</div>
@endsection
