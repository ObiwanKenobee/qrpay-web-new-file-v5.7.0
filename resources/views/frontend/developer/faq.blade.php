@extends('frontend.layouts.developer_master')

@php
    $lang = selectedLang();
    $faq_slug = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::DEVELOPER_FAQ_SECTION);
    $faq = App\Models\Admin\SiteSections::getData( $faq_slug)->first();
@endphp

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Resources") }}</div>
    <h1 class="dx-title">{{ __(@$faq->value->language->$lang->heading ?: 'FAQ') }}</h1>

    @if(!empty($faq->value->items))
        <div class="dx-faq">
            @foreach($faq->value->items ?? [] as $item)
                <details @if($loop->first) open @endif>
                    <summary>{{ __(@$item->language->$lang->question) }}</summary>
                    <p>{{ __(@$item->language->$lang->answer) }}</p>
                </details>
            @endforeach
        </div>
    @endif

    @if(@$faq->value->language->$lang->bottom_text)
        <div class="dx-callout dx-callout--info">
            <i class="las la-info-circle"></i>
            <p>{{ __(@$faq->value->language->$lang->bottom_text) }}</p>
        </div>
    @endif
</div>
@endsection
