{{-- $snippets = DeveloperDocs::request(...) or DeveloperDocs::wrap([...]); $title optional --}}
<div class="dx-code" data-code-tabs>
    <div class="dx-code-head">
        <span class="dx-code-title">{{ $title ?? __("Request") }}</span>
        <select class="dx-code-select" aria-label="{{ __('Language') }}">
            @foreach ($snippets as $key => $snippet)
                <option value="{{ $key }}">{{ $snippet['label'] }}</option>
            @endforeach
        </select>
        <button type="button" class="dx-copy" data-copy-code title="{{ __('Copy') }}"><i class="las la-copy"></i></button>
    </div>
    <div class="dx-code-tabs">
        @foreach ($snippets as $key => $snippet)
            <button type="button" data-lang="{{ $key }}">{{ $snippet['label'] }}</button>
        @endforeach
    </div>
    @foreach ($snippets as $key => $snippet)
        <pre class="dx-pre" data-lang="{{ $key }}"><code class="language-{{ $snippet['hl'] }}" data-template="{{ $snippet['code'] }}">{{ $snippet['code'] }}</code></pre>
    @endforeach
</div>
