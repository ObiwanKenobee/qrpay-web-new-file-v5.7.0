<?php

namespace App\Http;

use App\Http\Middleware\Admin\AdminDeleteGuard;
use App\Http\Middleware\Admin\AppModeGuard;
use App\Http\Middleware\Admin\AppModeGuardApi;
use App\Http\Middleware\Admin\GoogleTwoFactor;
use App\Http\Middleware\Admin\Localization;
use App\Http\Middleware\Admin\LoginGuard;
use App\Http\Middleware\Admin\MailGuard;
use App\Http\Middleware\Admin\ModuleSetting;
use App\Http\Middleware\Admin\PageSetup;
use App\Http\Middleware\Admin\ReferralSetting;
use App\Http\Middleware\Admin\RoleDeleteGuard;
use App\Http\Middleware\Admin\RoleGuard;
use App\Http\Middleware\Admin\SystemMaintenance;
use App\Http\Middleware\Admin\SystemMaintenanceApi;
use App\Http\Middleware\Admin\VirtualCardSystem;
use App\Http\Middleware\Api\HandleLocalization;
use App\Http\Middleware\ApiAuthenticator;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckSmsStatus;
use App\Http\Middleware\CheckStatusApiUser;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\KycApi;
use App\Http\Middleware\KycVerificationGuard;
use App\Http\Middleware\Merchant\CheckStatusApi;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RateLimiter;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\User\GoogleTwoFactorApi;
use App\Http\Middleware\User\PinSetupGuard;
use App\Http\Middleware\User\RegistrationPermission;
use App\Http\Middleware\User\SMSVerificationGuard;
use App\Http\Middleware\User\VerificationGuardApi;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerificationGuard;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,

    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            Localization::class,
            // \App\Http\Middleware\LanguageMiddleware::class,
            RateLimiter::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            SubstituteBindings::class,
            HandleLocalization::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'checkStatus' => CheckSmsStatus::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'app.mode' => AppModeGuard::class,
        'page_setup' => PageSetup::class,
        'app.mode.api' => AppModeGuardApi::class,
        'system.maintenance' => SystemMaintenance::class,
        'system.maintenance.api' => SystemMaintenanceApi::class,
        'module' => ModuleSetting::class,
        'referral_setting' => ReferralSetting::class,
        'virtual_card_method' => VirtualCardSystem::class,
        'admin.login.guard' => LoginGuard::class,
        'admin.role.guard' => RoleGuard::class,
        'mail' => MailGuard::class,
        'admin.delete.guard' => AdminDeleteGuard::class,
        'admin.role.delete.guard' => RoleDeleteGuard::class,
        'admin.google.two.factor' => GoogleTwoFactor::class,
        'verification.guard' => VerificationGuard::class,
        'verification.guard.merchant' => Middleware\Merchant\VerificationGuard::class,
        'verification.guard.api' => VerificationGuardApi::class,
        'user.google.two.factor' => Middleware\User\GoogleTwoFactor::class,
        'user.google.two.factor.api' => GoogleTwoFactorApi::class,
        'merchant.google.two.factor' => Middleware\Merchant\GoogleTwoFactor::class,
        'merchant.google.two.factor.api' => Middleware\Merchant\GoogleTwoFactorApi::class,
        'agent.google.two.factor' => Middleware\Agent\GoogleTwoFactor::class,
        'agent.google.two.factor.api' => Middleware\Agent\GoogleTwoFactorApi::class,
        'auth.api' => ApiAuthenticator::class,
        'merchant.api' => Middleware\Merchant\ApiAuthenticator::class,
        'agent.api' => Middleware\Agent\ApiAuthenticator::class,
        'CheckStatusApiUser' => CheckStatusApiUser::class,
        'CheckStatusApiMerchant' => CheckStatusApi::class,
        'kyc.verification.guard' => KycVerificationGuard::class,
        'api.kyc' => KycApi::class,
        'CheckStatusApiAgent' => Middleware\Agent\CheckStatusApi::class,
        'verification.guard.agent' => Middleware\Agent\VerificationGuard::class,
        'user.registration.permission' => RegistrationPermission::class,
        'agent.registration.permission' => Middleware\Agent\RegistrationPermission::class,
        'merchant.registration.permission' => Middleware\Merchant\RegistrationPermission::class,
        'sms.verification.guard' => SMSVerificationGuard::class,
        'agent.sms.verification.guard' => Middleware\Agent\SMSVerificationGuard::class,
        'merchant.sms.verification.guard' => Middleware\Merchant\SMSVerificationGuard::class,
        'user.pin.setup.guard' => PinSetupGuard::class,
        'merchant.pin.setup.guard' => Middleware\Merchant\PinSetupGuard::class,
        'agent.pin.setup.guard' => Middleware\Agent\PinSetupGuard::class,
    ];
}
