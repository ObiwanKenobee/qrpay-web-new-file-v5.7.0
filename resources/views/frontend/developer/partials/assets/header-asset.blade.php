<!-- favicon -->
<link rel="shortcut icon" href="{{ get_fav($basic_settings) }}" type="image/x-icon">
<!-- fontawesome css link -->
<!-- fontawesome css link -->
<link rel="stylesheet" href="{{ asset('frontend/') }}/css/fontawesome-all.min.css">
<!-- line-awesome-icon css -->
<link rel="stylesheet" href="{{ asset('frontend/') }}/css/line-awesome.min.css">
<!-- bootstrap css link -->
<link rel="stylesheet" href="{{ asset('frontend/') }}/css/bootstrap.min.css">
<!-- swipper css link -->
<link rel="stylesheet" href="{{ asset('frontend/') }}/css/swiper.min.css">
<!-- animate css link -->
<link rel="stylesheet" href="{{ asset('frontend/') }}/css/animate.css">

<link rel="stylesheet" href="{{ asset('backend/css/select2.min.css') }}">
<!-- nice select css -->
<link rel="stylesheet" href="{{ asset('frontend/css/nice-select.css') }}">
<!-- odometer css link -->
<link rel="stylesheet" href="{{ asset('frontend/') }}/css/odometer.css">
<!-- main style css link -->
<link rel="stylesheet" href="{{ asset('frontend/') }}/css/style.css">
<!-- code highlight + developer docs -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
<link rel="stylesheet" href="{{ asset('frontend/css/developer-docs.css') }}?v=1.0">
@php
    $color = @$basic_settings->base_color ?? '#4A8FCA';
@endphp

<style>
    :root {
        --primary-color: {{ $color }};
    }
</style>
