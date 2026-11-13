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
    ], 'active' => __("Edit Email Template")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Dynamic Variable Details") }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table two">
                    <thead>
                        <tr>
                            <th>{{ __("name") }}</th>
                            <th>{{ __("Description") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($variables as $key => $description)
                            @php
                                $placeholder = '{' . '{' . $key . '}' . '}';
                            @endphp
                            <tr>
                                <td>
                                    {{ $placeholder }}
                                    @if ($key == $required)
                                        <span class="badge badge--danger">{{ __("Required") }}</span>
                                    @endif
                                </td>
                                <td>{{ __($description) }}</td>
                                <td>
                                    <button type="button" class="btn btn--base copy-btn" data-text="{{ $placeholder }}"><i class="las la-copy"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <form action="{{ setRoute('admin.email.template.update',$email_template->slug) }}" method="POST" class="mt-15">
        @csrf
        @method("PUT")
        <div class="custom-card">
            <div class="card-header">
                <h6 class="title">{{ __($email_template->type) }}</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.form.input',[
                            'label'         => __("Subject"),
                            'name'          => "subject",
                            'required'      => true,
                            'value'         => old('subject',$email_template->subject),
                        ])
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.form.input-text-rich',[
                            'label'         => __("Body"),
                            'name'          => "body",
                            'value'         => old('body',$email_template->body),
                        ])
                        @error('body')
                            <span class="text--danger">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.button.form-btn',[
                            'class'         => "w-100 btn-loading",
                            'text'          => __("update"),
                            'permission'    => "admin.email.template.update",
                        ])
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('script')
    <script>
        $(".copy-btn").on("click",function(){
            var text = $(this).attr("data-text");
            var tempInput = document.createElement("textarea");
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            throwMessage('success',["{{ __('Copied') }}: " + text]);
        });
    </script>
@endpush
