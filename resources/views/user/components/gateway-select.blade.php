{{--
    Smart payment gateway select (user / agent / merchant panels).
    Turns every <select class="gateway-select"> into a searchable select2 that shows the gateway logo,
    name, currency and min-max limit. The original <select> keeps its value and fires "change",
    so the page JS that reads :selected data-* attributes keeps working.
    Option data used: data-image, data-type (MANUAL/AUTOMATIC), data-crypto, data-currency, data-min_amount, data-max_amount.
--}}
<style>
    .gs-container .select2-selection--single { height: 52px !important; display: flex !important; align-items: center; padding: 6px 10px !important; }
    .gs-container .select2-selection__rendered { padding-left: 0 !important; padding-right: 26px !important; width: 100%; }
    .gs-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); }
    .gs-item { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .gs-item__logo {
        width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0; object-fit: contain; background: #fff;
        border: 1px solid #ded7e9; padding: 3px;
    }
    .gs-item__logo--text {
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
        color: var(--primary-color); background: color-mix(in srgb, var(--primary-color) 10%, #fff);
    }
    .gs-item__body { min-width: 0; line-height: 1.25; }
    .gs-item__name { display: block; font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gs-item__meta { display: block; font-size: 12px; opacity: .65; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gs-badge { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 20px; font-size: 10px; font-weight: 700; vertical-align: middle;
        color: var(--primary-color); background: color-mix(in srgb, var(--primary-color) 10%, transparent); }
    .gs-dropdown .select2-results__option { padding: 8px 10px; }
    .gs-dropdown .select2-results__option--highlighted.select2-results__option--selectable { background: var(--primary-color); color: #fff; }
    .gs-dropdown .select2-results__option--selected { background: color-mix(in srgb, var(--primary-color) 8%, #fff); }
    .gs-dropdown .select2-results__option--highlighted .gs-item__meta { opacity: .9; }
    .gs-dropdown .select2-results > .select2-results__options { max-height: 320px; }
    .gs-dropdown .select2-search__field { border-radius: 6px; padding: 8px 10px !important; }
</style>
<script>
    (function ($) {
        if (!$ || !$.fn.select2) return;
        var T = { manual: @json(__("Manual")), crypto: @json(__("Crypto")), search: @json(__("Search")) };

        function fmt(n) { n = parseFloat(n); return isFinite(n) ? (+n.toFixed(2)).toString() : ''; }
        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

        function template(opt) {
            if (!opt.id || !opt.element) return opt.text;
            var $o = $(opt.element), name = $.trim(opt.text);
            var img = $o.data('image'), cur = $o.data('currency') || '';
            var min = fmt($o.data('min_amount')), max = fmt($o.data('max_amount'));
            var badge = String($o.data('type')).toUpperCase() === 'MANUAL' ? T.manual : ($o.data('crypto') == 1 ? T.crypto : '');
            var logo = img
                ? '<img class="gs-item__logo" src="' + esc(img) + '" alt="" onerror="this.outerHTML=\'<span class=&quot;gs-item__logo gs-item__logo--text&quot;>' + esc(name.charAt(0)) + '</span>\'">'
                : '<span class="gs-item__logo gs-item__logo--text">' + esc(name.charAt(0)) + '</span>';
            var meta = [cur, (min && max) ? (min + ' – ' + max + ' ' + cur) : ''].filter(Boolean).join(' · ');
            return $('<span class="gs-item">' + logo + '<span class="gs-item__body"><span class="gs-item__name">' + esc(name) +
                (badge ? '<span class="gs-badge">' + esc(badge) + '</span>' : '') + '</span>' +
                (meta ? '<span class="gs-item__meta">' + esc(meta) + '</span>' : '') + '</span></span>');
        }

        $(function () {
            $('select.gateway-select').each(function () {
                var $sel = $(this);
                if ($sel.hasClass('select2-hidden-accessible')) return;
                if ($.fn.niceSelect) { try { $sel.niceSelect('destroy'); } catch (e) {} }
                $sel.next('.nice-select').remove();
                $sel.select2({
                    width: '100%',
                    templateResult: template,
                    templateSelection: template,
                    minimumResultsForSearch: 6,
                    dropdownCssClass: 'gs-dropdown',
                    selectionCssClass: 'gs-container',
                    language: { searching: function () { return T.search + '...'; } },
                });
                $sel.next('.select2-container').addClass('gs-container');
            });
        });
    })(window.jQuery);
</script>
