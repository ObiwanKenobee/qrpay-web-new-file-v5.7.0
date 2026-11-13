{{-- One row of the theme's preview list. Params: icon, label, value, class?, last?, valueClass? (extra JS hook) --}}
<div class="preview-list-item">
    <div class="preview-list-left">
        <div class="preview-list-user-wrapper">
            <div class="preview-list-user-icon">
                <i class="{{ $icon ?? 'las la-receipt' }}"></i>
            </div>
            <div class="preview-list-user-content">
                <span @class(['last' => $last ?? false])>{{ $label }}</span>
            </div>
        </div>
    </div>
    <div class="preview-list-right">
        <span @class([$class ?? '', 'last' => $last ?? false, $valueClass ?? ''])>{{ $value ?? '--' }}</span>
    </div>
</div>
