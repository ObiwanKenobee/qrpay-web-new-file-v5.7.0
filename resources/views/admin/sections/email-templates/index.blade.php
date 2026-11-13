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
        ]
    ], 'active' => __("Email Templates")])
@endsection

@section('content')
    @include('admin.sections.email-templates.partials.nav',['current' => \App\Constants\EmailTemplateConst::GROUP_ADMIN])

    @if (admin_permission_by_name("admin.email.template.status.update"))
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Admin Email Notification") }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table two">
                    <thead>
                        <tr>
                            <th>{{ __("name") }}</th>
                            <th>{{ __("Email") }}</th>
                            <th>{{ __("Email Notification") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admins as $admin)
                            <tr>
                                <td>{{ $admin->fullname }}</td>
                                <td>{{ $admin->email }}</td>
                                <td>
                                    @include('admin.components.form.switcher',[
                                        'label'         => false,
                                        'name'          => 'email_notification',
                                        'options'       => [__('Enable') => 1 , __('Disable') => 0],
                                        'onload'        => true,
                                        'value'         => $admin->email_notification ? 1 : 0,
                                        'data_target'   => $admin->id,
                                        'permission'    => "admin.email.template.status.update",
                                    ])
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 3])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @include('admin.sections.email-templates.partials.template-table',[
        'title' => __("Admin Notification Templates"),
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
