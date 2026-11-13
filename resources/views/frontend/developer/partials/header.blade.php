<header class="dx-header">
    <div class="dx-header-left">
        <button type="button" class="dx-menu-btn" aria-label="{{ __('Menu') }}"><i class="las la-bars"></i></button>
        <a class="dx-logo" href="{{ setRoute('index') }}">
            <img src="{{ get_logo($basic_settings) }}" alt="site-logo">
        </a>
        <span class="dx-logo-badge">{{ __("Developer") }}</span>
    </div>
    <div class="dx-header-right">
        <div class="dx-env-switch" role="tablist" aria-label="{{ __('Environment') }}">
            <button type="button" data-env="sandbox">{{ __("Sandbox") }}</button>
            <button type="button" data-env="production">{{ __("Production") }}</button>
        </div>
        <a href="{{ setRoute('contact') }}" class="dx-header-link">{{ __("Support") }}</a>
        @auth("merchant")
            <a href="{{ setRoute('merchant.dashboard') }}" class="dx-header-btn"><i class="las la-th-large"></i><span>{{ __("Merchant Dashboard") }}</span></a>
        @else
            <a href="{{ setRoute('merchant.login') }}" class="dx-header-btn"><i class="las la-sign-in-alt"></i><span>{{ __("Login in to Dashboard") }}</span></a>
        @endauth
    </div>
</header>
