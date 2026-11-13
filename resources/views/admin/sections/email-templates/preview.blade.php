@extends('admin.layouts.master')

@push('css')

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
            'name'  => __(\App\Constants\EmailTemplateConst::ACCOUNT_GROUPS[$email_template->group] ?? "Email Templates"),
            'url'   => \App\Http\Controllers\Admin\EmailTemplateController::listUrl($email_template),
        ]
    ], 'active' => __("Email Template Preview")])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __("Subject") }}: {{ $email['subject'] }}</h6>
            @include('admin.components.link.edit-default',[
                'href'          => setRoute('admin.email.template.edit',$email_template->slug),
                'permission'    => "admin.email.template.edit",
            ])
        </div>
        <div class="card-body">
            {{-- sandboxed (no scripts), template html can't run inside admin panel --}}
            <iframe sandbox="" srcdoc="{{ view('mail-templates.email-template', ['email_subject' => $email['subject'], 'email_body' => $email['body']])->render() }}" style="width: 100%; height: 800px; border: 0; border-radius: 5px; background-color: #f4f4f4;"></iframe>
        </div>
    </div>
@endsection
