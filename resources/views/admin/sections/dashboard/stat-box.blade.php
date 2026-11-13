{{--
    Dashboard stat box
    $title, $value, $badges [[text, class]], $percent (0-100), $caption (what the circle means), $color, $route (optional link), $permission,
    $off (account types this service is turned off for in Module Settings), $col (column classes, optional)
--}}
@php
    $percent = max(0, min(100, round($percent ?? 0)));
@endphp
<div class="{{ $col ?? 'col-xxxl-3 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12' }} mb-15">
    <div class="dashbord-item">
        <div class="dashboard-content">
            <div class="left">
                <h6 class="title">
                    @if (isset($route) && admin_permission_by_name($permission ?? $route))
                        <a href="{{ setRoute($route) }}" class="dashboard-box-link">{{ $title }}</a>
                    @else
                        {{ $title }}
                    @endif
                </h6>
                <div class="user-info">
                    <h2 class="user-count">{{ $value }}</h2>
                </div>
                <div class="user-badge">
                    @foreach ($badges ?? [] as [$text, $class])
                        <span class="badge {{ $class }}">{{ $text }}</span>
                    @endforeach
                </div>
                @if (!empty($off))
                    <small class="text--danger d-block mt-1"><i class="las la-power-off"></i> {{ __("Off for") }}: {{ implode(', ', $off) }}</small>
                @endif
            </div>
            <div class="right text-center">
                <div class="chart dashboard-pie" data-percent="{{ $percent }}" data-color="{{ $color ?? '#8b7fd6' }}" style="--pie-color: {{ $color ?? '#8b7fd6' }}"><span>{{ $percent }}%</span></div>
                @isset($caption)
                    <small class="dashboard-pie-caption d-block mt-1">{{ $caption }}</small>
                @endisset
            </div>
        </div>
    </div>
</div>
