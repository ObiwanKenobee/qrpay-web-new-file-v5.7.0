<?php

declare(strict_types=1);

/**
 * Reviewed allowlist for ADR 0016 G5 (route registry gate).
 *
 * Every entry is a tracked debt, not an endorsement. Entries may only be
 * removed in the same change that fixes the underlying route; the test fails
 * when an entry no longer matches a real violation, so the list cannot rot.
 */

return [
    // R-26: legacy route names registered twice. Renaming them can break
    // generated URLs and external clients, so it needs its own change.
    'duplicate_names' => [
        'api.user.add.money.payment.global.success',
        'api.user.add.money.payment.global.cancel',
        'request.money.',
        'api.agent.add.money.payment.global.success',
        'api.agent.add.money.payment.global.cancel',
        'payment-link.gateway.payment.global.success',
        'payment-link.gateway.payment.global.cancel',
        'user.qr.scan',
        'user.add.money.payment.global.success',
        'user.add.money.payment.global.cancel',
        'user.strowallet.virtual.card.create',
        'agent.add.money.payment.global.success',
        'agent.add.money.payment.global.cancel',
        'qrpay.pay.',
    ],

    // Routes whose declared action does not exist. These four are legacy dead
    // endpoints with no view or client reference; removing them changes a 500
    // into a 404 and is deferred to a dedicated cleanup (R-29). New
    // unresolvable actions fail the gate.
    'unresolvable_actions' => [
        'DELETE user/payment/link/delete -> App\Http\Controllers\User\PaymentLinkController@delete',
        'DELETE merchant/payment-link/delete -> App\Http\Controllers\Merchant\PaymentLinkController@delete',
        'POST agent/sender-recipient/find/user -> App\Http\Controllers\Agent\SenderRecipientController@checkUser',
        'POST agent/receiver-recipient/find/user -> App\Http\Controllers\Agent\ReceiverRecipientController@checkUser',
    ],

    // State-changing routes with no middleware outside the global web/api
    // stack. Each is intentional public surface: installer steps, public
    // location lookups, provider callbacks authenticated inside the handler,
    // or the standalone QRPay payment pages. Security review of these routes
    // is tracked separately (R-03/R-04/R-05).
    'public_write_routes' => [
        'POST global/get-states',
        'POST global/get-cities',
        'POST global/get-countries',
        'POST global/get-countries-user',
        'POST global/get-countries-agent',
        'POST global/get-countries-merchant',
        'POST global/get-timezones',
        'POST global/set-cookie',
        'POST global/get/total/transactions',
        'POST global/mobile-topup/webhook',
        'POST flutterwave/withdraw_webhooks',
        'POST pay/sandbox/api/v1/authentication/token',
        'POST pay/sandbox/api/v1/payment/create',
        'POST pay/api/v1/authentication/token',
        'POST pay/api/v1/payment/create',
        'POST pay/sandbox/v1/user/authentication/form/submit/{token}',
        'POST pay/sandbox/v1/user/authentication/mail/verify/submit/{token}',
        'POST pay/sandbox/v1/user/payment/preview/submit/{token}',
        'POST pay/v1/user/authentication/form/submit/{token}',
        'POST pay/v1/user/authentication/mail/verify/submit/{token}',
        'POST pay/v1/user/payment/preview/submit/{token}',
        'POST project/install/validation/form/submit',
        'POST project/install/database/config/submit',
        'POST project/install/migration/submit',
        'POST project/install/admin/account/setting/submit',
    ],
];
