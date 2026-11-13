@extends('admin.layouts.master')

@push('css')
    <style>
        .sms-preview { max-width: 380px; margin: 0 auto; padding: 24px 18px; background: #f3f4f6; border-radius: 24px; }
        .sms-preview .sms-sender { text-align: center; font-size: 13px; color: #6b7280; margin-bottom: 14px; }
        .sms-preview .sms-bubble { display: inline-block; max-width: 90%; padding: 12px 16px; background: #ffffff; border-radius: 18px 18px 18px 4px; color: #111827; font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        .sms-preview .sms-meta { margin-top: 10px; font-size: 12px; color: #6b7280; }
    </style>
@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ],
        [
            'name'  => __(\App\Constants\SmsTemplateConst::ACCOUNT_GROUPS[$sms_template->group] ?? "SMS Templates"),
            'url'   => setRoute("admin.sms.template.index",$sms_template->group),
        ]
    ], 'active' => __("SMS Template Preview")])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __($sms_template->type) }}</h6>
            @include('admin.components.link.edit-default',[
                'href'          => setRoute('admin.sms.template.edit',$sms_template->id),
                'permission'    => "admin.sms.template.edit",
            ])
        </div>
        <div class="card-body">
            <div class="sms-preview">
                <div class="sms-sender">{{ $basic_settings->site_name ?? "" }}</div>
                <div class="sms-bubble">{{ $sms }}</div>
                <div class="sms-meta">{{ __("Characters") }}: {{ mb_strlen($sms) }} | {{ __("SMS Parts") }}: {{ $segments }}</div>
            </div>
        </div>
    </div>
@endsection
