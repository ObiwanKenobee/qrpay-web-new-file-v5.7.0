{{--
    Daily / monthly limit meter for every user page.
    It replaces the old "Daily Limit / Remaining Daily Limit / Monthly Limit / Remaining Monthly Limit"
    rows (.limit-daily, .daily-remaining, .limit-monthly, .monthly-remaining) with progress bars and is driven
    by the response of route('global.get.total.transactions'), so pages need no extra code.
    Colors: base color (--primary-color) = under 70% used, yellow = 70% or more used, red = fully used / exceeded.
--}}
<style>
    /* Limit Information card: same look as the smart preview */
    .preview-list-wrapper:has(.lm-wrap) { background: #fff; border: 1px solid #ded7e9; border-radius: 14px; overflow: hidden; }
    .preview-list-wrapper:has(.lm-wrap) > .preview-list-item { padding: 12px 16px; border-bottom: 1px dashed #ded7e9; }
    .preview-list-wrapper:has(.lm-wrap) .preview-list-user-icon { width: auto; height: auto; background: transparent; font-size: 17px; opacity: .9; }
    .preview-list-wrapper:has(.lm-wrap) .preview-list-user-content { padding-left: 8px; }
    .preview-list-wrapper:has(.lm-wrap) .preview-list-user-content span { font-size: 14px; opacity: .85; }
    .preview-list-wrapper:has(.lm-wrap) .preview-list-right span {
        display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 13px; font-weight: 600;
        background: color-mix(in srgb, var(--primary-color) 8%, transparent);
    }
    .lm-wrap { padding: 14px 16px; border-bottom: 1px solid #ded7e9; }
    .lm { padding: 12px; border-radius: 10px; background: rgba(0, 0, 0, .025); }
    .lm + .lm { margin-top: 10px; padding-top: 12px; border-top: 0; }
    .lm__head > span:first-child { font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
    .lm__head > span:first-child::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--primary-color); }
    .lm.is-low .lm__head > span:first-child::before { background: #f5a623; }
    .lm.is-over .lm__head > span:first-child::before { background: #e5484d; }
    .lm.is-unlimited .lm__head > span:first-child::before { background: #9ca3af; }
    .lm.is-over { background: rgba(229, 72, 77, .06); }
    .lm.is-low { background: rgba(245, 166, 35, .07); }
    .lm-wrap:last-child { border-bottom: 0; }
    .lm__head { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; font-size: 14px; }
    .lm__value strong { font-weight: 700; }
    .lm__of { opacity: .65; font-size: 13px; }
    .lm__track { display: flex; height: 9px; border-radius: 20px; overflow: hidden; background: rgba(0, 0, 0, .08); }
    .lm__left { --lm-color: var(--primary-color); background: var(--lm-color); transition: width .35s ease, background .35s ease; }
    .lm__pending {
        background: repeating-linear-gradient(45deg, var(--lm-color, var(--primary-color)), var(--lm-color, var(--primary-color)) 4px, transparent 4px, transparent 8px);
        opacity: .5; transition: width .35s ease;
    }
    .lm.is-low .lm__left, .lm.is-low .lm__pending { --lm-color: #f5a623; }
    .lm.is-over .lm__left, .lm.is-over .lm__pending { --lm-color: #e5484d; }
    .lm.is-over .lm__pending { background: #e5484d; opacity: .35; }
    .lm.is-unlimited .lm__left { width: 100% !important; opacity: .3; }
    .lm.is-unlimited .lm__of { display: none; }
    .lm__foot { display: flex; justify-content: space-between; gap: 10px; margin-top: 6px; font-size: 12px; opacity: .8; }
    .lm__note { text-align: right; }
    .lm.is-low .lm__note { color: #b7791f; opacity: 1; font-weight: 600; }
    .lm.is-over .lm__note { color: #e5484d; opacity: 1; font-weight: 600; }
    .lm:not(.is-low):not(.is-over):not(.is-unlimited) .lm__note { color: var(--primary-color); }
</style>
<script>
    (function ($) {
        if (!$) return;
        var LIMIT_URL = @json(route('global.get.total.transactions'));
        var T = {
            daily:      @json(__("Daily Limit")),
            monthly:    @json(__("Monthly Limit")),
            leftOf:     @json(__("left of")),
            unlimited:  @json(__("Unlimited")),
            exceeded:   @json(__("Limit exceeded")),
            noneLeft:   @json(__("No limit left")),
            thisTrx:    @json(__("This transaction")),
            resetDay:   @json(__("Resets at midnight")),
            resetMonth: @json(__("Resets on the 1st of every month")),
        };
        var OLD = '.limit-daily, .daily-remaining, .limit-monthly, .monthly-remaining';

        function meter(key) {
            return '<div class="lm" data-lm="' + key + '">' +
                '<div class="lm__head"><span>' + T[key] + '</span><span class="lm__value"><strong class="lm__remaining">--</strong> <span class="lm__of">' + T.leftOf + ' <span class="lm__total">--</span></span></span></div>' +
                '<div class="lm__track" role="progressbar" aria-label="' + T[key] + '" aria-valuemin="0" aria-valuemax="100"><div class="lm__left" style="width:100%"></div><div class="lm__pending" style="width:0%"></div></div>' +
                '<div class="lm__foot"><span>' + (key === 'daily' ? T.resetDay : T.resetMonth) + '</span><span class="lm__note"></span></div>' +
                '</div>';
        }

        // hide the old rows and put the meters in their place (once per page)
        function box() {
            var $wrap = $('.lm-wrap');
            if ($wrap.length) return $wrap;
            var $rows = $(OLD).not('.limit-meter *, .gc-limits *').closest('.preview-list-item');
            if (!$rows.length) return null;
            $wrap = $('<div class="lm-wrap"></div>').append(meter('daily')).append(meter('monthly'));
            $rows.last().after($wrap);
            $rows.hide();
            return $wrap;
        }

        function param(data, name) {
            if (!data) return 0;
            if (typeof data === 'object') return parseFloat(data[name]) || 0;
            var m = String(data).match(new RegExp('(?:^|&)' + name + '=([^&]*)'));
            return m ? (parseFloat(decodeURIComponent(m[1].replace(/\+/g, ' '))) || 0) : 0;
        }

        function render(data, amount) {
            var $wrap = box();
            if (!$wrap) return;
            var prec = parseInt(data.precision, 10);
            prec = isFinite(prec) ? Math.min(Math.max(prec, 0), 8) : 2;
            var cur = data.currency || '';
            var fmt = function (n) { n = parseFloat(n); return isFinite(n) ? n.toFixed(prec) + ' ' + cur : '--'; };
            amount = amount > 0 ? amount : 0;

            $.each({ daily: ['dailyLimitSelected', 'remainingDailyTxnSelected'], monthly: ['monthlyLimitSelected', 'remainingMonthlyTxnSelected'] }, function (key, f) {
                var $m = $wrap.find('[data-lm="' + key + '"]');
                var total = parseFloat(data[f[0]]);
                var remaining = parseFloat(data[f[1]]);

                if (!(total > 0)) {
                    $m.addClass('is-unlimited').removeClass('is-low is-over');
                    $m.find('.lm__remaining').text(T.unlimited);
                    $m.find('.lm__pending').css('width', '0%');
                    $m.find('.lm__note').text('');
                    return;
                }
                remaining = isFinite(remaining) ? Math.max(Math.min(remaining, total), 0) : total;
                var over    = amount > remaining || remaining <= 0;
                var left    = Math.max(remaining - amount, 0);
                var pending = Math.min(amount, remaining);
                var used    = 1 - left / total; // share of the limit used, this transaction included
                var full    = over || left <= 0;

                // base color below 70% used, yellow from 70%, red when fully used / exceeded
                $m.removeClass('is-unlimited').toggleClass('is-over', full).toggleClass('is-low', !full && used >= 0.7);
                // bold number = what is left AFTER the amount being typed, so it drops in real time
                $m.find('.lm__remaining').text(fmt(left));
                $m.find('.lm__total').text(fmt(total));
                $m.find('.lm__left').css('width', (left / total * 100) + '%');
                $m.find('.lm__pending').css('width', (pending / total * 100) + '%');
                $m.find('.lm__track').attr('aria-valuenow', Math.round(left / total * 100));
                $m.find('.lm__note').text(
                    remaining <= 0 ? T.noneLeft :
                    over ? T.exceeded + ' (+' + fmt(amount - remaining) + ')' :
                    amount > 0 ? T.thisTrx + ': ' + fmt(amount) : ''
                );
            });
        }

        $(function () { box(); });

        // real time: while an amount field is typed in, ask the page for fresh limits (debounced).
        // Every user page builds its own request in get_remaining_limits(), so the amount math stays the page's.
        var typingTimer = null;
        $(document).on('input keyup change', 'input', function () {
            if (!/amount/i.test(this.name || '') || !$('.lm-wrap').length) return;
            if (typeof window.get_remaining_limits !== 'function') return;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function () { window.get_remaining_limits(); }, 350);
        });
        $(document).ajaxSuccess(function (e, xhr, settings) { handle(xhr, settings); });
        $(document).ajaxError(function (e, xhr, settings) { handle(xhr, settings); }); // 4xx still carries the data
        function handle(xhr, settings) {
            if (!settings || settings.url !== LIMIT_URL) return;
            var data = ((xhr.responseJSON || {}).data) || null;
            if (!data || data.dailyLimitSelected === undefined) return;
            render(data, param(settings.data, 'sender_amount'));
        }
    })(window.jQuery);
</script>
