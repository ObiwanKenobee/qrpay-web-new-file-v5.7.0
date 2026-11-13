@if ($error)
    <div class="alert alert-danger gc-error"><i class="las la-exclamation-triangle me-1"></i>{{ $error }}</div>
@endif

@if ($products->total() > 0)
    <div class="gc-meta">
        <div>
            {{ __("Showing") }} <strong>{{ $products->firstItem() }}–{{ $products->lastItem() }}</strong> {{ __("of") }} <strong>{{ number_format($products->total()) }}</strong> {{ __("Gift Cards") }}
            @if ($search !== '')
                <span class="gc-tag"><i class="las la-search"></i>{{ $search }}</span>
            @endif
            @if ($country)
                <span class="gc-tag"><i class="las la-flag"></i>{{ data_get(collect($countries ?? [])->firstWhere('iso2', $country), 'name', $country) }}</span>
            @endif
        </div>
        <div>{{ __("Page") }} {{ $products->currentPage() }} / {{ $products->lastPage() }}</div>
    </div>
@endif

<div class="row mb-20-none">
    @forelse ($products as $card)
        @php
            $image = $card['logoUrls'][0] ?? '';
            $url   = setRoute('user.gift.card.details', $card['productId']);
        @endphp
        <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-4 col-sm-6 mb-20">
            <div class="gift-card-item">
                <div class="gift-card-thumb">
                    <a href="{{ $url }}"><img src="{{ $image }}" alt="{{ $card['productName'] }}" loading="lazy"></a>
                </div>
                <div class="gift-card-content">
                    <h5 class="title"><a href="{{ $url }}">{{ $card['productName'] }}</a></h5>
                </div>
            </div>
        </div>
    @empty
        @unless ($error)
            <div class="col-12 mb-20">
                <div class="gc-empty">
                    <i class="las la-gift"></i>
                    <h5>{{ __("No gift cards found") }}</h5>
                    <p class="mb-3">{{ __("Try another name or country.") }}</p>
                    @if ($search !== '' || $country)
                        <a href="{{ setRoute('user.gift.card.list') }}" class="btn--base">{{ __("Show all gift cards") }}</a>
                    @endif
                </div>
            </div>
        @endunless
    @endforelse
</div>

@if ($products->hasPages())
    <div class="gc-pagination">
        {{ $products->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
@endif
