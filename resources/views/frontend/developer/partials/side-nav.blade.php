@php
    $dx_current = Route::currentRouteName();
@endphp
<aside class="dx-sidebar">
    <div class="dx-search">
        <i class="las la-search"></i>
        <input type="text" class="dx-search-input" placeholder="{{ __('Search') }}..." autocomplete="off">
        <kbd>/</kbd>
    </div>
    <nav class="dx-nav">
        @foreach (App\Http\Helpers\DeveloperDocs::nav() as $group)
            <div class="dx-nav-group">
                <div class="dx-nav-title">{{ __($group['title']) }}</div>
                <ul>
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ setRoute($item['route']) }}" class="dx-nav-link {{ $dx_current === $item['route'] ? 'active' : '' }}">
                                @isset($item['method'])
                                    <span class="dx-method dx-method--{{ strtolower($item['method']) }}">{{ $item['method'] }}</span>
                                @else
                                    <i class="{{ $item['icon'] }}"></i>
                                @endisset
                                <span class="dx-nav-text">{{ __($item['title']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <div class="dx-nav-empty">{{ __("No results found") }}</div>
    </nav>
</aside>
