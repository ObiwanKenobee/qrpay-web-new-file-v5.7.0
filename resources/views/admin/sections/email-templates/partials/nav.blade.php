{{-- current page button is filled, others are outlined (.outline) --}}
@php
    $show_admin_btn     = $current != \App\Constants\EmailTemplateConst::GROUP_ADMIN && admin_permission_by_name("admin.email.template.index");
    $show_account_btns  = admin_permission_by_name("admin.email.template.account");
@endphp
@if ($show_admin_btn || $show_account_btns)
<div class="custom-card mb-15">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            @if ($show_admin_btn)
                <a href="{{ setRoute('admin.email.template.index') }}" class="btn--base outline"><i class="las la-user-shield me-1"></i>{{ __("Admin Email Templates") }}</a>
            @endif
            @if ($show_account_btns)
                @foreach (\App\Constants\EmailTemplateConst::ACCOUNT_GROUPS as $group => $title)
                    <a href="{{ setRoute('admin.email.template.account',$group) }}" class="btn--base @if($current != \App\Constants\EmailTemplateConst::GROUP_ADMIN && $current != $group) outline @endif"><i class="las la-envelope me-1"></i>{{ __($title) }}</a>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endif
