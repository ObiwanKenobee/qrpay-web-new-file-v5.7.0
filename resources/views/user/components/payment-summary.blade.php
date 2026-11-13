{{--
    Smart payment summary (used by Add Money preview + Manual Payment, user and agent panels).
    Params:
      $gateway        string|null   gateway name shown in the header (static)
      $gatewaySelect  string|null   CSS selector of a <select>; its selected option text is shown live in the header
      $gatewayLabel   string|null   small label above the gateway name (default "Payment Gateway")
      $rows           array         [['icon' => 'las la-..', 'label' => '..', 'value' => '..', 'class' => 'js-hook extra-class'], ...]
      $total          array         ['label' => '..', 'value' => '..', 'class' => 'js-hook']
      $note           string|null   small note under the total
    The value elements keep the given classes, so existing page JS (e.g. $('.fees').text(..)) still updates them.
--}}
@once
<style>
    .ps-card { border: 1px solid #ded7e9; border-radius: 14px; background: #fff; overflow: hidden; }
    .ps-head { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px dashed #ded7e9; }
    .ps-head__icon {
        width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 20px;
        color: var(--primary-color); background: color-mix(in srgb, var(--primary-color) 10%, transparent);
    }
    .ps-head__label { display: block; font-size: 12px; opacity: .65; }
    .ps-head__name { display: block; font-weight: 700; font-size: 15px; word-break: break-word; }
    .ps-rows { padding: 6px 16px; }
    .ps-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 0; font-size: 14px; }
    .ps-row + .ps-row { border-top: 1px solid rgba(0, 0, 0, .05); }
    .ps-row__label { display: flex; align-items: center; gap: 8px; opacity: .8; }
    .ps-row__label i { font-size: 17px; color: var(--primary-color); opacity: .9; }
    .ps-row__value { font-weight: 600; text-align: right; word-break: break-word; }
    .ps-total {
        margin: 6px 12px 12px; padding: 14px 16px; border-radius: 12px;
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;
        background: var(--primary-color); color: #fff;
    }
    .ps-total__label { font-size: 14px; opacity: .9; }
    .ps-total__value { font-size: 22px; font-weight: 700; line-height: 1.3; word-break: break-word; color: #fff !important; }
    .ps-note { display: block; padding: 0 16px 14px; font-size: 12px; opacity: .7; text-align: center; }
    @media (max-width: 575px) { .ps-total__value { font-size: 19px; } }
</style>
@endonce
<div class="ps-card">
    <div class="ps-head">
        <span class="ps-head__icon"><i class="las la-university"></i></span>
        <div>
            <span class="ps-head__label">{{ $gatewayLabel ?? __("Payment Gateway") }}</span>
            <span class="ps-head__name ps-gateway-name">{{ $gateway ?? '--' }}</span>
        </div>
    </div>
    <div class="ps-rows">
        @foreach ($rows as $row)
            <div class="ps-row">
                <span class="ps-row__label"><i class="{{ $row['icon'] ?? 'las la-receipt' }}"></i>{{ $row['label'] }}</span>
                <span class="ps-row__value {{ $row['class'] ?? '' }}">{{ $row['value'] ?? '--' }}</span>
            </div>
        @endforeach
    </div>
    <div class="ps-total">
        <span class="ps-total__label">{{ $total['label'] }}</span>
        <span class="ps-total__value {{ $total['class'] ?? '' }}">{{ $total['value'] ?? '--' }}</span>
    </div>
    @if (!empty($note))
        <span class="ps-note"><i class="las la-info-circle"></i> {{ $note }}</span>
    @endif
</div>
@if (!empty($gatewaySelect))
    @push('script')
    <script>
        $(function () {
            var $sel = $(@json($gatewaySelect));
            var icon = $('.ps-head__icon').first().html();
            var show = function () {
                var $o = $sel.find(':selected'), img = $o.data('image');
                $('.ps-gateway-name').text($.trim($o.text()) || '--');
                $('.ps-head__icon').html(img ? $('<img>', { src: img, alt: '' }).css({ width: '100%', height: '100%', objectFit: 'contain', padding: '4px', borderRadius: '10px', background: '#fff' }) : icon);
            };
            $sel.on('change', show); show();
        });
    </script>
    @endpush
@endif
