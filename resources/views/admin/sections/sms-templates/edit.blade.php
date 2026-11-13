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
            'name'  => __(\App\Constants\SmsTemplateConst::ACCOUNT_GROUPS[$sms_template->group] ?? "SMS Templates"),
            'url'   => setRoute("admin.sms.template.index",$sms_template->group),
        ]
    ], 'active' => __("Edit SMS Template")])
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

    <form action="{{ setRoute('admin.sms.template.update',$sms_template->id) }}" method="POST" class="mt-15">
        @csrf
        @method("PUT")
        <div class="custom-card">
            <div class="card-header">
                <h6 class="title">{{ __($sms_template->type) }}</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.form.textarea',[
                            'label'         => __("SMS Body"),
                            'name'          => "sms_body",
                            'value'         => old('sms_body',$sms_template->sms_body),
                        ])
                        <small class="text--base sms-counter d-block mt-1"></small>
                        @error('sms_body')
                            <span class="text--danger">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.button.form-btn',[
                            'class'         => "w-100 btn-loading",
                            'text'          => __("update"),
                            'permission'    => "admin.sms.template.update",
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

        // characters + SMS parts (GSM 160/153, unicode 70/67), placeholders are counted as typed
        function smsCounter() {
            var text = $("textarea[name=sms_body]").val() || "";
            var unicode = /[^\x00-\x7F£¥èéùìòÇØøÅå€]/.test(text);
            var single = unicode ? 70 : 160, multi = unicode ? 67 : 153;
            var parts = text.length == 0 ? 0 : (text.length <= single ? 1 : Math.ceil(text.length / multi));
            $(".sms-counter").text("{{ __('Characters') }}: " + text.length + " | {{ __('SMS Parts') }}: " + parts + " ({{ __('values replace placeholders when sent') }})");
        }
        $(document).on("input", "textarea[name=sms_body]", smsCounter);
        smsCounter();
    </script>
@endpush
