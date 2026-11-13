{{-- $responses = [[status(int), 'label', data(array)], ...] --}}
<div class="dx-code dx-code--response" data-response-tabs>
    <div class="dx-code-head">
        <span class="dx-code-title">{{ $title ?? __("Response") }}</span>
        <div class="dx-status-tabs">
            @foreach ($responses as $i => $response)
                @php $tone = $response[0] < 300 ? 'ok' : ($response[0] < 500 ? 'warn' : 'err'); @endphp
                <button type="button" class="dx-status dx-status--{{ $tone }} {{ $i === 0 ? 'active' : '' }}" data-status="{{ $i }}" title="{{ $response[1] }}">
                    <i></i>{{ $response[0] }}
                </button>
            @endforeach
        </div>
        <button type="button" class="dx-copy" data-copy-code title="{{ __('Copy') }}"><i class="las la-copy"></i></button>
    </div>
    @foreach ($responses as $i => $response)
        <div class="dx-response {{ $i === 0 ? 'active' : '' }}" data-status="{{ $i }}">
            <div class="dx-response-label">{{ $response[1] }}</div>
            <pre class="dx-pre"><code class="language-json">{{ App\Http\Helpers\DeveloperDocs::json($response[2]) }}</code></pre>
        </div>
    @endforeach
</div>
