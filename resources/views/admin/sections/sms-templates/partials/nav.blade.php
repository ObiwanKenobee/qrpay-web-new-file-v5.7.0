{{-- current page button is filled, others are outlined (.outline) --}}
<div class="custom-card mb-15">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            @foreach (\App\Constants\SmsTemplateConst::ACCOUNT_GROUPS as $item_group => $title)
                <a href="{{ setRoute('admin.sms.template.index',$item_group) }}" class="btn--base @if($current != $item_group) outline @endif"><i class="las la-sms me-1"></i>{{ __($title) }}</a>
            @endforeach
        </div>
    </div>
</div>
