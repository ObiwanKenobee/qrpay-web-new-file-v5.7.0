@props([
    'site_key',
    'extension',
    'position'  => 'start',
    'id'        => 'g-recaptcha-container',
    'callback'  => 'googleV2CaptchaCallback'
])

@php
    $position = match($position){
        'center'    => "justify-content-center",
        'start'     => "justify-content-start",
        'end'       => "justify-content-end",
    };
@endphp

@if ($extension->status ?? false)

    <div class="mb-4 d-flex {{ $position }}" id="{{ $id }}">
        <div class="g-recaptcha" data-sitekey="{{ $site_key }}" data-theme="light" data-callback="{{ $callback }}"></div>
    </div>

    @pushOnce('css')
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endPushOnce

    @pushOnce('script')
        <script>
            function googleV2CaptchaCallback(token){
                // handle token
            }
        </script>
    @endPushOnce
@endif
