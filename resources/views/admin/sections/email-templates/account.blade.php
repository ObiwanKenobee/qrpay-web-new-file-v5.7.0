@extends('admin.layouts.master')

@push('css')

@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => array_merge([
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ],
    ], admin_permission_by_name("admin.email.template.index") ? [
        [
            'name'  => __("Email Templates"),
            'url'   => setRoute("admin.email.template.index"),
        ]
    ] : []), 'active' => __($page_title)])
@endsection

@section('content')
    @include('admin.sections.email-templates.partials.nav',['current' => $group])

    @include('admin.sections.email-templates.partials.template-table',[
        'title' => __("Notification Templates"),
        'items' => $email_templates->where('auth', false),
    ])
    @include('admin.sections.email-templates.partials.template-table',[
        'title' => __("Auth Email Templates"),
        'items' => $email_templates->where('auth', true),
    ])
@endsection

@push('script')
    <script>
        switcherAjax("{{ setRoute('admin.email.template.status.update') }}");
    </script>
@endpush
