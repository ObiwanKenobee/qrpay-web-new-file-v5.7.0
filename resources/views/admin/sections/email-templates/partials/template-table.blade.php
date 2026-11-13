@php
    $can_status_update  = admin_permission_by_name("admin.email.template.status.update");
    $can_preview        = admin_permission_by_name("admin.email.template.preview");
    $can_edit           = admin_permission_by_name("admin.email.template.edit");
    $colspan            = 2 + ($can_status_update ? 1 : 0) + (($can_preview || $can_edit) ? 1 : 0);
@endphp
<div class="table-area mt-15">
    <div class="table-wrapper">
        <div class="table-header">
            <h5 class="title">{{ $title }}</h5>
        </div>
        <div class="table-responsive">
            <table class="custom-table two">
                <thead>
                    <tr>
                        <th>{{ __("type") }}</th>
                        @if ($can_status_update)
                            <th>{{ __("Status") }}</th>
                        @endif
                        <th>{{ __("Last Edit By") }}</th>
                        @if ($can_preview || $can_edit)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ __($item->type) }}</td>
                            @if ($can_status_update)
                                <td>
                                    @if (\App\Constants\EmailTemplateConst::canDisable($item->slug))
                                        @include('admin.components.form.switcher',[
                                            'label'         => false,
                                            'name'          => 'status',
                                            'options'       => [__('Enable') => 1 , __('Disable') => 0],
                                            'onload'        => true,
                                            'value'         => $item->status,
                                            'data_target'   => $item->id,
                                            'permission'    => "admin.email.template.status.update",
                                        ])
                                    @else
                                        <span class="badge badge--success">{{ __("Always On") }}</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $item->admin->fullname ?? "-" }}</td>
                            @if ($can_preview || $can_edit)
                                <td>
                                    @include('admin.components.link.info-default',[
                                        'href'          => setRoute('admin.email.template.preview',$item->slug),
                                        'permission'    => "admin.email.template.preview",
                                    ])
                                    @include('admin.components.link.edit-default',[
                                        'href'          => setRoute('admin.email.template.edit',$item->slug),
                                        'permission'    => "admin.email.template.edit",
                                    ])
                                </td>
                            @endif
                        </tr>
                    @empty
                        @include('admin.components.alerts.empty',['colspan' => $colspan])
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
