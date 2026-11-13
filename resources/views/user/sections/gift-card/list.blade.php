@extends('user.layouts.master')

@push('css')
<style>
    .gc-filter {
        background: #EBEDEF;
        border: 1px solid #ded7e9;
        border-radius: 15px;
        padding: 18px;
        margin-bottom: 24px;
    }
    .gc-filter .form-group { margin-bottom: 0; }
    .gc-filter .gc-search { position: relative; }
    .gc-filter .gc-search i {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        font-size: 18px; opacity: .6; pointer-events: none;
    }
    .gc-filter .gc-search .form--control { padding-left: 44px; }
    .gc-filter .gc-actions { display: flex; gap: 10px; }
    .gc-filter .gc-actions .btn--base { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 45px; }
    .gc-filter .gc-reset {
        display: inline-flex; align-items: center; justify-content: center;
        height: 45px; padding: 0 16px; border-radius: 5px;
        border: 1px solid var(--primary-color); color: var(--primary-color); white-space: nowrap;
    }
    .gc-filter .gc-reset:hover { background: var(--primary-color); color: #fff; }
    /* select2 inside the filter: keep text centered, small clear (x) button */
    .gc-filter .select2-selection--single { position: relative; display: flex !important; align-items: center; border: 1px solid color-mix(in srgb, var(--primary-color) 25%, transparent) !important; }
    .gc-filter .select2-selection--single .select2-selection__rendered { padding-left: 8px; padding-right: 50px; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gc-filter .select2-selection--single .select2-selection__clear {
        width: auto !important; position: absolute; right: 32px; top: 50%; transform: translateY(-50%);
        font-size: 18px; color: #e5484d; cursor: pointer; z-index: 2;
    }
    .gc-filter .select2-selection--single .select2-selection__clear span { width: auto !important; }
    .gc-filter .select2-selection--single .select2-selection__placeholder { line-height: 1.4; }
    .gc-filter .select2-container--default .select2-selection--single .select2-selection__arrow { top: 50%; transform: translateY(-50%); }
    .gc-meta { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; font-size: 14px; }
    .gc-meta .gc-tag {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 12px; border-radius: 30px; font-size: 13px;
        background: color-mix(in srgb, var(--primary-color) 10%, transparent); color: var(--primary-color); margin-left: 6px;
    }
    .gift-card-item .gift-card-thumb { background: #f2f4f8; }
    .gc-empty {
        text-align: center; padding: 50px 20px; border-radius: 15px;
        background: #EBEDEF; border: 1px solid #ded7e9;
    }
    .gc-empty i { font-size: 46px; color: var(--primary-color); }
    .gc-empty h5 { margin: 12px 0 6px; }
    .gc-pagination { margin-top: 26px; display: flex; justify-content: center; }

    /* loader */
    .gc-loading { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; font-size: 14px; font-weight: 600; color: var(--primary-color); }
    .gc-spinner {
        width: 18px; height: 18px; border-radius: 50%;
        border: 2px solid color-mix(in srgb, var(--primary-color) 25%, transparent); border-top-color: var(--primary-color);
        animation: gc-spin .7s linear infinite;
    }
    .gc-skeleton__thumb, .gc-skeleton__line {
        border-radius: 6px;
        background: linear-gradient(90deg, rgba(0, 0, 0, .06) 25%, rgba(0, 0, 0, .12) 37%, rgba(0, 0, 0, .06) 63%);
        background-size: 400% 100%;
        animation: gc-shimmer 1.2s ease infinite;
    }
    .gc-skeleton__thumb { height: 130px; }
    .gc-skeleton__line { height: 14px; width: 70%; margin-top: 12px; }
    .gc-submit[disabled] { opacity: .75; cursor: wait; }
    @keyframes gc-spin { to { transform: rotate(360deg); } }
    @keyframes gc-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
    .gc-pagination .pagination { flex-wrap: wrap; gap: 4px; }
</style>
@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="dashboard-header-wrapper mt-20">
        <h4 class="title">{{ __($page_title) }}</h4>
        <div class="dashboard-btn-wrapper">
            <div class="dashboard-btn">
                <a href="{{ setRoute('user.gift.card.index') }}" class="btn--base"><i class="las la-gift me-1"></i>{{ __("My Gift Card") }}</a>
            </div>
        </div>
    </div>

    <form action="{{ setRoute('user.gift.card.list') }}" method="GET" class="gc-filter">
        <div class="row g-3 align-items-center">
            <div class="col-xl-5 col-lg-5 col-md-6">
                <div class="form-group gc-search">
                    <i class="las la-search"></i>
                    <input type="text" name="search" class="form--control" maxlength="60" value="{{ $search }}" placeholder="{{ __('Search gift cards (e.g. Amazon, Netflix)') }}">
                </div>
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="form-group">
                    <select name="country" class="form--control gc-country" data-placeholder="{{ __('All Countries') }}">
                        <option value="">{{ __('All Countries') }}</option>
                        @foreach ($countries ?? [] as $item)
                            <option value="{{ data_get($item, 'iso2') }}" {{ $country === data_get($item, 'iso2') ? 'selected' : '' }}>{{ data_get($item, 'name') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-xl-3 col-lg-3 col-md-12">
                <div class="gc-actions">
                    <button type="submit" class="btn--base gc-submit"><i class="las la-search"></i><span>{{ __("search") }}</span></button>
                    <a href="{{ setRoute('user.gift.card.list') }}" class="gc-reset" title="{{ __('Reset') }}" @if ($search === '' && !$country) style="display: none" @endif><i class="las la-undo-alt"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div id="gc-results" class="gc-results" aria-live="polite">
        @include('user.sections.gift-card.partials.list-results')
    </div>
</div>
@endsection

@push('script')
<script>
    $(document).ready(function () {
        var form     = $('.gc-filter');
        var results  = $('#gc-results');
        var country  = $('.gc-country');
        var listUrl  = "{{ setRoute('user.gift.card.list') }}";
        var request  = null;

        if ($.fn.select2) {
            country.select2({ width: '100%', allowClear: true, placeholder: country.data('placeholder') });
            // search as soon as a country is picked / cleared
            country.on('select2:select select2:clear', function () { form.trigger('submit'); });
        }

        function skeleton() {
            var cards = '';
            for (var i = 0; i < 12; i++) {
                cards += '<div class="col-xxl-2 col-xl-3 col-lg-3 col-md-4 col-sm-6 mb-20"><div class="gc-skeleton"><div class="gc-skeleton__thumb"></div><div class="gc-skeleton__line"></div></div></div>';
            }
            return '<div class="gc-loading"><span class="gc-spinner"></span>{{ __("Loading gift cards...") }}</div><div class="row mb-20-none">' + cards + '</div>';
        }

        function setLoading(state) {
            var btn = form.find('.gc-submit');
            btn.prop('disabled', state).find('i').attr('class', state ? 'las la-spinner la-spin' : 'las la-search');
            results.attr('aria-busy', state ? 'true' : 'false');
        }

        function syncForm(url) {
            var params = new URL(url, window.location.origin).searchParams;
            form.find('input[name=search]').val(params.get('search') || '');
            country.val(params.get('country') || '').trigger('change.select2');
            form.find('.gc-reset').toggle(!!(params.get('search') || params.get('country')));
        }

        function load(url, push) {
            if (request) request.abort();
            request = new AbortController();
            setLoading(true);
            results.html(skeleton());

            var top = results.offset().top - 120;
            if ($(window).scrollTop() > top) $('html, body').animate({ scrollTop: top }, 200);

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                cache: 'no-store',
                credentials: 'same-origin',
                signal: request.signal,
            })
            .then(function (response) {
                if (!response.ok) throw new Error(response.status);
                return response.text().then(function (html) { return { html: html, url: response.url || url }; });
            })
            .then(function (data) {
                results.html(data.html);
                if (push) window.history.pushState({ giftCards: true }, '', data.url);
                syncForm(data.url);
                setLoading(false);
            })
            .catch(function (error) {
                if (error.name === 'AbortError') return;
                window.location.href = url; // fall back to a normal page load
            });
        }

        function buildUrl() {
            var url = new URL(listUrl, window.location.origin);
            var search = $.trim(form.find('input[name=search]').val());
            var code = country.val();
            if (search) url.searchParams.set('search', search);
            if (code) url.searchParams.set('country', code);
            return url.toString();
        }

        form.on('submit', function (e) {
            e.preventDefault();
            load(buildUrl(), true);
        });

        form.on('click', '.gc-reset', function (e) {
            e.preventDefault();
            form.find('input[name=search]').val('');
            country.val('').trigger('change.select2');
            load(listUrl, true);
        });

        results.on('click', '.gc-pagination a, .gc-empty a', function (e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey) return; // allow open in new tab
            e.preventDefault();
            load(this.href, true);
        });

        window.addEventListener('popstate', function () {
            load(window.location.href, false);
        });
    });
</script>
@endpush
