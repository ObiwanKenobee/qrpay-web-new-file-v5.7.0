{{-- @include('frontend.developer.partials.endpoint', ['method' => 'POST', 'path' => '/payment/create']) --}}
<div class="dx-endpoint">
    <span class="dx-method dx-method--{{ strtolower($method) }}">{{ strtoupper($method) }}</span>
    <code class="dx-endpoint-url"><span class="dx-base" data-base-url>__BASE_URL__</span>{{ $path }}</code>
    <button type="button" class="dx-copy" data-copy-endpoint="{{ $path }}" title="{{ __('Copy') }}"><i class="las la-copy"></i></button>
</div>
