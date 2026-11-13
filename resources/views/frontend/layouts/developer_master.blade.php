@php
    $dx_envs = App\Http\Helpers\DeveloperDocs::environments();
@endphp
<!DOCTYPE html>
<html lang="{{ get_default_language_code() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $basic_settings->sitename(__($page_title??'')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @include('frontend.developer.partials.assets.header-asset')

    @stack('css')
</head>
<body class="{{ selectedLangDir() ?? "ltr"}} dx-body">

@include('frontend.partials.preloader')

<div id="body-overlay" class="body-overlay"></div>

@include('frontend.developer.partials.header')

<div class="dx-shell" data-envs='@json($dx_envs)'>
    @include('frontend.developer.partials.side-nav')
    <main class="dx-main">
        @yield("content")
        @include('frontend.developer.partials.pager')
    </main>
</div>

@include('frontend.developer.partials.assets.footer-asset')
@include('frontend.partials.extensions.tawk-to')

@stack('script')

</body>
</html>
