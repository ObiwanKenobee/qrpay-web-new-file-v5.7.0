@php
    [$dx_prev, $dx_next] = App\Http\Helpers\DeveloperDocs::pager(Route::currentRouteName());
@endphp
<div class="dx-pager">
    @if ($dx_prev)
        <a href="{{ setRoute($dx_prev['route']) }}" class="dx-pager-link">
            <small><i class="las la-arrow-left"></i> {{ __("Previous") }}</small>
            <span>{{ __($dx_prev['title']) }}</span>
        </a>
    @else
        <span></span>
    @endif
    @if ($dx_next)
        <a href="{{ setRoute($dx_next['route']) }}" class="dx-pager-link dx-pager-link--next">
            <small>{{ __("Next") }} <i class="las la-arrow-right"></i></small>
            <span>{{ __($dx_next['title']) }}</span>
        </a>
    @endif
</div>
