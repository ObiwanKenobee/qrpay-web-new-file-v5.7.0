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
    ], 'active' => __($page_title)])
@endsection

@section('content')
    @include('admin.sections.sms-templates.partials.nav',['current' => $group])

    @foreach ([
        ['title' => __("Notification Templates"), 'items' => $sms_templates->where('auth', false)],
        ['title' => __("Verification SMS Templates"), 'items' => $sms_templates->where('auth', true)],
    ] as $section)
    <div class="table-area mt-15">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ $section['title'] }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table two">
                    <thead>
                        <tr>
                            <th>{{ __("type") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Last Edit By") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($section['items'] as $item)
                            <tr>
                                <td>{{ __($item->type) }}</td>
                                <td>
                                    @if ($item->auth)
                                        <span class="badge badge--success">{{ __("Always On") }}</span>
                                    @else
                                        @include('admin.components.form.switcher',[
                                            'label'         => false,
                                            'name'          => 'status',
                                            'options'       => [__('Enable') => 1 , __('Disable') => 0],
                                            'onload'        => true,
                                            'value'         => (int) $item->sms_status,
                                            'data_target'   => $item->id,
                                            'permission'    => "admin.sms.template.status.update",
                                        ])
                                    @endif
                                </td>
                                <td>{{ $item->admin->fullname ?? "-" }}</td>
                                <td>
                                    @include('admin.components.link.info-default',[
                                        'href'          => setRoute('admin.sms.template.preview',$item->id),
                                        'permission'    => "admin.sms.template.preview",
                                    ])
                                    @include('admin.components.link.edit-default',[
                                        'href'          => setRoute('admin.sms.template.edit',$item->id),
                                        'permission'    => "admin.sms.template.edit",
                                    ])
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 4])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach
@endsection

@push('script')
    <script>
        switcherAjax("{{ setRoute('admin.sms.template.status.update') }}");
    </script>
@endpush
