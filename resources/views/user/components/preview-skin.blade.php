{{--
    Smart look for every feature "Preview" list (user / agent / merchant panels), CSS only.
    Applies to .preview-list-wrapper blocks that contain a total row (an element with class "last"),
    so Limit Information and other plain lists are untouched. The total row becomes a filled box.
    Same look as user/components/payment-summary.blade.php. Gift card summary is excluded on purpose.
--}}
<style>
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) {
        background: #fff; border: 1px solid #ded7e9; border-radius: 14px; overflow: hidden; padding: 6px 0 0;
    }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item {
        padding: 10px 16px; border-bottom: 1px solid rgba(0, 0, 0, .05);
    }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-user-icon {
        width: auto; height: auto; background: transparent; font-size: 17px; opacity: .9;
    }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-user-content { padding-left: 8px; }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-user-content span { opacity: .85; font-size: 14px; }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-right span { font-weight: 600; font-size: 14px; word-break: break-word; }

    /* total row -> filled box */
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item:has(.last) {
        margin: 8px 12px 12px; padding: 14px 16px; border: 0; border-radius: 12px;
        background: var(--primary-color); flex-wrap: wrap; gap: 6px;
    }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item:has(.last) .preview-list-user-icon { display: none; }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item:has(.last) .preview-list-user-content { padding-left: 0; }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item:has(.last) span {
        color: #fff !important; opacity: 1;
    }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item:has(.last) .preview-list-user-content span { font-size: 14px; font-weight: 500; opacity: .9; }
    .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item:has(.last) .preview-list-right span { font-size: 22px; font-weight: 700; line-height: 1.3; }
    @media (max-width: 575px) {
        .preview-list-wrapper:has(.last):not(.gc-summary .preview-list-wrapper) .preview-list-item:has(.last) .preview-list-right span { font-size: 18px; }
    }
</style>
