{{-- $params = [['name', 'type', required(bool), 'description', 'example'?], ...] --}}
<div class="dx-params">
    @if (!empty($title))
        <div class="dx-params-head">{{ $title }}</div>
    @endif
    @foreach ($params as $param)
        <div class="dx-param">
            <div class="dx-param-top">
                <code class="dx-param-name">{{ $param[0] }}</code>
                <span class="dx-param-type">{{ $param[1] }}</span>
                @if ($param[2])
                    <span class="dx-param-req">{{ __("Required") }}</span>
                @else
                    <span class="dx-param-opt">{{ __("Optional") }}</span>
                @endif
            </div>
            <p class="dx-param-desc">{{ $param[3] }}</p>
            @if (!empty($param[4]))
                <div class="dx-param-example">{{ __("Example") }}: <code>{{ $param[4] }}</code></div>
            @endif
        </div>
    @endforeach
</div>
