{{--
    Strowallet card holder form (create + edit)
    $d             default values (create: user profile, edit: saved card holder)
    $customer_kyc  saved card holder or null
    $locked        true once Strowallet accepted the card holder (customer_id exists), the form is read only
--}}
@php
    $val = fn($key) => old($key, $d[$key] ?? '');
    $sections = [
        'personal'  => ['icon' => 'la-user',          'tone' => 'blue',   'title' => __("Personal Information"), 'text' => __("Your name, birth date and birth place, as on your ID.")],
        'identity'  => ['icon' => 'la-id-card',       'tone' => 'purple', 'title' => __("Identity Document"),    'text' => __("The ID document you will verify with.")],
        'contact'   => ['icon' => 'la-map-marker',    'tone' => 'teal',   'title' => __("Contact & Address"),    'text' => __("Where we can reach you and your home address.")],
        'financial' => ['icon' => 'la-briefcase',     'tone' => 'orange', 'title' => __("Financial Profile"),    'text' => __("How you will use the card.")],
    ];
@endphp

<div class="ch">
    {{-- header --}}
    <div class="ch-hero">
        <div class="ch-hero__icon"><i class="las la-user-shield"></i></div>
        <div class="ch-hero__text">
            <h4 class="title">{{ __(@$page_title) }}</h4>
            <p>{{ $locked ? __("Your card holder has been submitted. These details can no longer be changed.") : __("A cardholder is required before creating a card. Please create a cardholder first.") }}</p>
        </div>
        @if (!$customer_kyc)
            <div class="ch-hero__status">
                <span class="ch-pill is-info"><i class="las la-plus-circle"></i> {{ __("New Card Holder") }}</span>
            </div>
        @elseif (!empty($customer_kyc->completion_link))
            {{-- Strowallet asks the card holder to finish verification on its site --}}
            <div class="ch-hero__status">
                <a href="{{ $customer_kyc->completion_link }}" target="_blank" rel="noopener" class="btn--base small">{{ __("Complete Verification") }} <i class="las la-external-link-alt"></i></a>
            </div>
        @endif
    </div>

    <div class="row g-4">
        {{-- steps / progress --}}
        <div class="col-xl-3">
            <div class="ch-steps">
                <div class="ch-progress">
                    <div class="ch-progress__head"><span>{{ __("Completed") }}</span><strong class="ch-progress__value">0%</strong></div>
                    <div class="ch-progress__track"><span class="ch-progress__bar"></span></div>
                </div>
                <ul>
                    @foreach ($sections as $key => $section)
                        <li>
                            <a href="#ch-{{ $key }}" class="ch-step" data-step="{{ $key }}">
                                <span class="ch-step__num tone-{{ $section['tone'] }}">{{ $loop->iteration }}</span>
                                <span class="ch-step__title">{{ $section['title'] }}</span>
                                <i class="las la-check-circle ch-step__done"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="col-xl-9">
            <form class="card-form ch-form" action="{{ route('user.strowallet.virtual.card.store.customer') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if ($locked)
                    <div class="ch-locked"><i class="las la-lock"></i> {{ __("Card holder information can not be changed after it is verified.") }}</div>
                @endif
                <fieldset class="m-0 p-0 border-0" @if($locked) disabled @endif>

                {{-- 1. personal --}}
                <div class="ch-section" id="ch-personal" data-section="personal">
                    @include('user.sections.virtual-card-strowallet.customer.partials.section-head', ['section' => $sections['personal'], 'number' => 1])
                    <div class="row g-3">
                        <div class="col-lg-12 form-group">
                            <label>{{ __("name") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("Enter Name") }}" name="name" value="{{ $val('name') }}" required>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("first Name") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("enter First Name") }}" name="first_name" value="{{ $val('first_name') }}" required>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("last Name") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("enter Last Name") }}" name="last_name" value="{{ $val('last_name') }}" required>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("date Of Birth") }}<span>*</span></label>
                            <input type="date" class="form--control" name="dob" value="{{ $val('dob') }}" max="{{ now()->subDay()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("Place Of Birth") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("Enter Place Of Birth") }}" name="place_of_birth" value="{{ $val('place_of_birth') }}" maxlength="100" required>
                        </div>
                    </div>
                </div>

                {{-- 2. identity --}}
                <div class="ch-section" id="ch-identity" data-section="identity">
                    @include('user.sections.virtual-card-strowallet.customer.partials.section-head', ['section' => $sections['identity'], 'number' => 2])
                    <div class="row g-3">
                        <div class="col-lg-6 form-group">
                            <label>{{ __("ID Type") }}<span>*</span></label>
                            <select class="form--control nice-select" name="id_type" required>
                                <option value="">{{ __("Select ID Type") }}</option>
                                @foreach (['national_id' => __("National ID"), 'passport' => __("passport"), 'drivers_license' => __("Driver's License")] as $value => $text)
                                    <option value="{{ $value }}" @selected($val('id_type') == $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("id Number") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("enter Id Number") }}" name="id_number" value="{{ $val('id_number') }}" required>
                        </div>
                        <div class="col-lg-12 form-group">
                            @if (!empty($customer_kyc?->id_image))
                                @include('admin.components.form.input-file',[
                                    'label'             => __("ID Image"),
                                    'name'              => "id_image",
                                    'class'             => "file-holder",
                                    'old_files_path'    => files_asset_path('card-kyc-images'),
                                    'old_files'         => $customer_kyc->id_image,
                                ])
                            @else
                                <label>{{ __("ID Image") }}<span>*</span></label>
                                <input type="file" class="file-holder" name="id_image" data-height="130" accept="image/*" data-max_size="20" data-file_limit="15">
                            @endif
                            <small class="ch-hint"><i class="las la-info-circle"></i> {{ __("Upload a clear photo of the front side. JPG, PNG or WEBP, max 10MB.") }}</small>
                        </div>
                    </div>
                </div>

                {{-- 3. contact & address --}}
                <div class="ch-section" id="ch-contact" data-section="contact">
                    @include('user.sections.virtual-card-strowallet.customer.partials.section-head', ['section' => $sections['contact'], 'number' => 3])
                    <div class="row g-3">
                        <div class="col-lg-6 form-group">
                            <label>{{ __("Email") }}<span>*</span></label>
                            <input type="email" class="form--control" placeholder="{{ __("enter Email Address") }}" name="email" value="{{ $val('email') }}" required>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("Phone") }}<span>*</span></label>
                            <div class="input-group">
                                <div class="input-group-text phone-code">+{{ old('phone_code', $d['phone_code'] ?? '') }}</div>
                                <input class="phone-code" type="hidden" name="phone_code" value="{{ old('phone_code', $d['phone_code'] ?? '') }}" />
                                <input type="text" class="form--control" placeholder="{{ __('enter Phone Number') }}" name="phone" value="{{ $val('phone') }}" required>
                            </div>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("country") }}<span>*</span></label>
                            <select name="country" class="form-control select2-auto-tokenize country-select select2-basic trx-type-select" required>
                                <option value="" disabled @selected($val('country') == '')>{{ __("select Country") }}</option>
                                @foreach (get_all_countries() ?? [] as $country)
                                    <option value="{{ $country->iso3 }}" @selected($val('country') == $country->iso3) data-mobile-code="{{ $country->mobile_code }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("Address Line 1") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("enter Address") }}" name="line1" value="{{ $val('line1') }}" required>
                        </div>
                        <div class="col-lg-4 form-group">
                            <label>{{ __("city") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("enter City") }}" name="city" value="{{ $val('city') }}" required>
                        </div>
                        <div class="col-lg-4 form-group">
                            <label>{{ __("state") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("enter State") }}" name="state" value="{{ $val('state') }}" required>
                        </div>
                        <div class="col-lg-4 form-group">
                            <label>{{ __("Zip/Postal") }}<span>*</span></label>
                            <input type="text" class="form--control" placeholder="{{ __("enter Zip Code") }}" name="postal_code" value="{{ $val('postal_code') }}" required>
                        </div>
                    </div>
                </div>

                {{-- 4. financial profile --}}
                <div class="ch-section" id="ch-financial" data-section="financial">
                    @include('user.sections.virtual-card-strowallet.customer.partials.section-head', ['section' => $sections['financial'], 'number' => 4])
                    <div class="row g-3">
                        <div class="col-lg-4 form-group">
                            <label>{{ __("Account Purpose") }}<span>*</span></label>
                            <select class="form--control nice-select" name="account_purpose" required>
                                <option value="">{{ __("Select Account Purpose") }}</option>
                                @foreach (\App\Models\StrowalletCustomerKyc::ACCOUNT_PURPOSES as $value => $text)
                                    <option value="{{ $value }}" @selected($val('account_purpose') == $value)>{{ __($text) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 form-group">
                            <label>{{ __("Employment Status") }}<span>*</span></label>
                            <select class="form--control nice-select" name="employment_status" required>
                                <option value="">{{ __("Select Employment Status") }}</option>
                                @foreach (\App\Models\StrowalletCustomerKyc::EMPLOYMENT_STATUSES as $value => $text)
                                    <option value="{{ $value }}" @selected($val('employment_status') == $value)>{{ __($text) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 form-group">
                            <label>{{ __("Occupation") }}<span>*</span></label>
                            <select class="form--control nice-select" name="occupation" required>
                                <option value="">{{ __("Select Occupation") }}</option>
                                @foreach (\App\Models\StrowalletCustomerKyc::OCCUPATIONS as $value => $text)
                                    <option value="{{ $value }}" @selected($val('occupation') == $value)>{{ __($text) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("Annual Salary") }}<span>*</span></label>
                            <div class="input-group">
                                <input type="number" class="form--control" placeholder="{{ __("Enter Annual Salary") }}" name="annual_salary" value="{{ $val('annual_salary') }}" min="1" step="any" required>
                                <span class="input-group-text">USD</span>
                            </div>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label>{{ __("Expected Monthly Volume") }}<span>*</span></label>
                            <div class="input-group">
                                <input type="number" class="form--control" placeholder="{{ __("Enter Expected Monthly Volume") }}" name="expected_monthly_volume" value="{{ $val('expected_monthly_volume') }}" min="1" step="any" required>
                                <span class="input-group-text">USD</span>
                            </div>
                        </div>
                    </div>
                </div>

                </fieldset>

                @if (!$locked)
                    <div class="ch-submit">
                        <p><i class="las la-shield-alt"></i> {{ __("Make sure the details match your ID document. They can't be changed after verification.") }}</p>
                        <button type="submit" class="btn--base btn-loading buyBtn">{{ __("confirm") }} <i class="las la-arrow-right ms-1"></i></button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>

@push('css')
<style>
    .ch-hero { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 22px 24px; margin-bottom: 22px; border-radius: 18px; background: linear-gradient(120deg, #eef2ff 0%, #f5f3ff 55%, #fdf2f8 100%); border: 1px solid #e0e7ff; }
    .ch-hero__icon { width: 58px; height: 58px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 16px; background: #fff; color: #4f46e5; font-size: 30px; box-shadow: 0 8px 20px -10px rgba(79,70,229,.45); }
    .ch-hero__text { flex: 1 1 280px; }
    .ch-hero__text .title { margin: 0 0 4px; font-size: 20px; }
    .ch-hero__text p { margin: 0; font-size: 14px; color: #4b5563; }
    .ch-hero__status { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; text-align: right; }
    .ch-hero__status small { font-size: 12px; color: #6b7280; }
    .ch-pill { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 30px; font-size: 13px; font-weight: 700; }
    .ch-pill.is-success { background: #dcfce7; color: #15803d; }
    .ch-pill.is-warning { background: #fef3c7; color: #b45309; }
    .ch-pill.is-info    { background: #dbeafe; color: #1d4ed8; }

    .ch-steps { position: sticky; top: 90px; padding: 18px; border-radius: 16px; background: #fff; border: 1px solid #ececf3; }
    .ch-steps ul { list-style: none; margin: 14px 0 0; padding: 0; }
    .ch-step { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 12px; color: #374151; font-size: 14px; font-weight: 600; transition: background .2s; }
    .ch-step:hover { background: #f8fafc; color: #111827; }
    .ch-step.is-active { background: color-mix(in srgb, var(--primary-color) 8%, #fff); }
    .ch-step__num { width: 30px; height: 30px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 9px; font-size: 13px; font-weight: 700; }
    .ch-step__title { flex: 1 1 auto; }
    .ch-step__done { font-size: 20px; color: #d1d5db; transition: color .2s; }
    .ch-step.is-done .ch-step__done { color: #16a34a; }
    .ch-progress__head { display: flex; justify-content: space-between; font-size: 13px; color: #6b7280; margin-bottom: 6px; }
    .ch-progress__head strong { color: #111827; }
    .ch-progress__track { height: 8px; border-radius: 10px; background: #eef0f4; overflow: hidden; }
    .ch-progress__bar { display: block; height: 100%; width: 0; border-radius: 10px; background: linear-gradient(90deg, #22c55e, #16a34a); transition: width .3s ease; }

    .ch-section { padding: 20px 22px; margin-bottom: 18px; border-radius: 16px; background: #fff; border: 1px solid #ececf3; scroll-margin-top: 90px; }
    .ch-section .form-group { margin-bottom: 0; }
    .ch-section label { font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .ch-section-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px dashed #e5e7eb; }
    .ch-section-head__icon { width: 42px; height: 42px; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 21px; }
    .ch-section-head h6 { margin: 0; font-size: 16px; }
    .ch-section-head p { margin: 2px 0 0; font-size: 13px; color: #6b7280; }
    .ch-section-head__num { margin-left: auto; font-size: 12px; font-weight: 700; color: #9ca3af; }
    .ch .input-group-text { background: #f1f5f9; color: #374151; border: 1px solid #e5e7eb; font-weight: 600; }
    .ch-hint { display: block; margin-top: 6px; font-size: 12px; color: #6b7280; }
    .tone-blue   { background: #dbeafe; color: #1d4ed8; }
    .tone-purple { background: #ede9fe; color: #6d28d9; }
    .tone-teal   { background: #ccfbf1; color: #0f766e; }
    .tone-orange { background: #ffedd5; color: #c2410c; }

    .ch-locked { display: flex; align-items: center; gap: 8px; padding: 12px 16px; margin-bottom: 18px; border-radius: 12px; background: #fef3c7; color: #92400e; font-size: 14px; font-weight: 600; }
    .ch-form fieldset[disabled] .ch-section { background: #fafafa; }
    .ch-form fieldset[disabled] .form--control, .ch-form fieldset[disabled] .nice-select, .ch-form fieldset[disabled] .select2-container { pointer-events: none; opacity: .85; }
    .ch-submit { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding: 16px 20px; border-radius: 16px; background: #fff; border: 1px solid #ececf3; }
    .ch-submit p { margin: 0; font-size: 13px; color: #6b7280; flex: 1 1 260px; }
    .ch-submit .btn--base { min-width: 200px; justify-content: center; }

    @media (max-width: 1199px) {
        .ch-steps { position: static; }
        .ch-steps ul { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
    }
    @media (max-width: 575px) {
        .ch-hero__status { align-items: flex-start; text-align: left; }
        .ch-steps ul { grid-template-columns: 1fr; }
        .ch-section { padding: 16px; }
        .ch-submit .btn--base { width: 100%; }
    }
</style>
@endpush

@push('script')
<script>
    $(function () {
        // phone code follows the selected country
        $("select[name=country]").on('change', function () {
            placePhoneCode($("select[name=country] :selected").attr("data-mobile-code"));
        });

        // progress: required fields filled per section
        var hasOldImage = @json(!empty($customer_kyc?->id_image));
        function filled($field) {
            if ($field.attr('type') === 'file') return hasOldImage || ($field[0].files && $field[0].files.length > 0) || $field.closest('.ch-section').find('.fileholder-files-view-wrp img, .file-holder-preview img').length > 0;
            return $.trim($field.val() || '') !== '';
        }
        function updateProgress() {
            var total = 0, done = 0;
            $('.ch-section').each(function () {
                var $fields = $(this).find('input[name]:not([type=hidden]):not([name=_token]), select[name]');
                var sectionDone = true;
                $fields.each(function () {
                    total++;
                    if (filled($(this))) done++; else sectionDone = false;
                });
                $('.ch-step[data-step="' + $(this).data('section') + '"]').toggleClass('is-done', sectionDone);
            });
            var percent = total ? Math.round(done / total * 100) : 0;
            $('.ch-progress__value').text(percent + '%');
            $('.ch-progress__bar').css('width', percent + '%');
        }
        $(document).on('input change', '.ch-form input, .ch-form select', updateProgress);
        updateProgress();

        // highlight the step of the section in view
        $('.ch-step').on('click', function () { $('.ch-step').removeClass('is-active'); $(this).addClass('is-active'); });
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        $('.ch-step').removeClass('is-active');
                        $('.ch-step[data-step="' + $(entry.target).data('section') + '"]').addClass('is-active');
                    }
                });
            }, { rootMargin: '-40% 0px -55% 0px' });
            $('.ch-section').each(function () { observer.observe(this); });
        }
    });
</script>
@endpush
