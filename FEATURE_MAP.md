# Feature Map — QRPay / Sanctum Scan

**Status:** QODOR-001 reconnaissance baseline  
**Date:** 2026-11-15  
**Branch:** `feature/qrpay-update` at `4ddb392`

## Status legend

| Label | Meaning |
|---|---|
| **Implemented (static)** | Routes, controllers, services, and models exist in the examined revision; not executed end-to-end. |
| **Implemented + tested** | Code exists and has an automated test in this repository. Tests were not executable at HEAD because of the boot blocker. |
| **Partial** | Some layers exist, but the capability is not wired end-to-end. |
| **Docs only** | Described in documentation but no implementation found. |
| **Not implemented** | Explicitly future/deferred. |

---

## 1. Legacy QRPay product surface

All entries are **Implemented (static)** unless noted. Legacy features have no automated tests in this repository.

### 1.1 Public site and content

| Feature | Actors | Entry points / evidence |
|---|---|---|
| Marketing home, about, service, FAQ, pricing, contact | Public | `SiteController` routes in [`routes/web.php:29`](routes/web.php:29) |
| Blog/journal, categories, details | Public | [`routes/web.php:37`](routes/web.php:37) |
| Useful pages, header sections, language switch | Public | [`routes/web.php:45`](routes/web.php:45) |
| Newsletter and contact submissions | Public | [`routes/web.php:46`](routes/web.php:46) |
| Sitemap | Public | `SitemapController`; `public/sitemap.xml` |
| Developer documentation pages | Public | `DeveloperController` routes in [`routes/web.php:56`](routes/web.php:56) |

### 1.2 Identity and onboarding

| Feature | Actors | Entry points / evidence |
|---|---|---|
| User registration/login/password reset | User | [`routes/auth.php`](routes/auth.php); `User\Auth\*` |
| Merchant registration/login/password reset | Merchant | [`routes/auth.php:81`](routes/auth.php:81); `Merchant\Auth\*` |
| Agent registration/login/password reset | Agent | [`routes/auth.php:146`](routes/auth.php:146); `Agent\Auth\*` |
| Email/SMS verification guards | User/merchant/agent | `VerificationGuard`, `SMSVerificationGuard` middleware aliases in [`app/Http/Kernel.php`](app/Http/Kernel.php) |
| Transaction PIN setup | User/merchant/agent | `*.pin.setup.guard` middleware |
| Google 2FA | All panels | `*.google.two.factor` middleware; `pragmarx/google2fa` |
| KYC submission and admin review | User/merchant/agent | `*KycData` models; `SetupKycController`; `KycVerificationGuard` |
| Login/activity logs | All panels | `*LoginLog` models |
| Social login | User | `laravel/socialite`; `config/services.php` |

### 1.3 Wallets, money movement, and payments

| Feature | Actors | Entry points / evidence | Notes |
|---|---|---|---|
| Wallet balances | User/merchant/agent | `UserWallet`, `MerchantWallet`, `AgentWallet` | Thin models; no domain invariants |
| Add money (many gateways, manual) | User/agent/merchant | [`routes/user.php:119`](routes/user.php:119); [`routes/agent.php:118`](routes/agent.php:118) | Provider traits + callbacks |
| Withdraw / money out | User/merchant/agent | [`routes/user.php:161`](routes/user.php:161); [`routes/merchant.php:66`](routes/merchant.php:66); [`routes/agent.php:100`](routes/agent.php:100) | Admin approval paths |
| Send / receive / request / make payment | User | [`routes/user.php:60`](routes/user.php:60) | Wallet-to-wallet |
| Agent money-in / money-out | User/agent | [`routes/user.php:81`](routes/user.php:81); [`routes/agent.php:64`](routes/agent.php:64) | Agent network |
| Remittance (bank deposit, cash pickup) | User/agent | [`routes/user.php:303`](routes/user.php:303); [`routes/agent.php:164`](routes/agent.php:164) | Country/recipient data |
| Bill pay | User/agent | [`routes/user.php:279`](routes/user.php:279) | Categories, providers |
| Mobile top-up | User/agent | [`routes/user.php:286`](routes/user.php:286) | Reloadly-backed; unsigned webhook |
| Gift cards | User | [`routes/user.php:335`](routes/user.php:335) | Provider abstraction |
| Virtual cards | User | [`routes/user.php:176`](routes/user.php:176) | Stripe, Sudo, Strowallet, CardyFie |
| Payment links (wallet/gateway/card) | User/merchant/public payer | [`routes/web.php:77`](routes/web.php:77); [`routes/user.php:102`](routes/user.php:102) | Share token, checkout callbacks |
| Transaction history and exports | All panels | `app/Exports/*`; transaction routes | Excel exports |
| Referral levels and bonuses | User | `ReferralLevelPackage`, `UserEarnReferralLevel` | |

### 1.4 Merchant developer API and QR gateway

| Feature | Actors | Entry points / evidence |
|---|---|---|
| API credential generation | Merchant | `Merchant\DeveloperApiController` |
| Gateway settings | Merchant | `GatewaySettings`; [`routes/merchant.php:109`](routes/merchant.php:109) |
| Auth token + payment order creation | Merchant server | `PaymentGateway\QrPay\v1\AuthenticationController`; `PaymentController@create` |
| Hosted payer authentication | Payer | `UserAuthenticationController`; QR demo checkout routes |
| Payment confirmation and wallet/card charge | Payer | `PaymentController@paymentConfirm` |
| Sandbox wallet | Merchant | `SandboxWallet`; sandbox routes |
| Return/cancel redirects | Merchant/payer | Query parameters; no server-to-server settlement webhook found in QR routes |

### 1.5 Admin and operations

| Feature | Entry points / evidence |
|---|---|
| Dashboard and statistics | `Admin\DashboardController`; `RebuildDashboardStats` command |
| User/merchant/agent care and KYC review | `Admin\UserCareController`, `MerchantCareController`, `AgentCareController` |
| Roles and permissions | `AdminRole`, `AdminRolePermission`, `RoleGuard` |
| Payment gateways and currencies | `PaymentGatewaysController`, `PaymentGatewayCurrencyController`, `CurrencyController` |
| Exchange rates | `ExchangeRateController`, `LiveExchangeRateApiController`, `currency:update` |
| Languages, email/SMS templates, pages, sections, SEO | Corresponding admin controllers |
| Notifications and push | `PushNotificationController`, notification helpers, Pusher |
| Support tickets and chat | `SupportTicketController`, support models |
| Reports and exports | `app/Exports/*`, profit logs, transaction views |
| System maintenance, file permissions, server info | `SystemMaintenanceController`, `FilePermissionController`, `ServerInfoController` |
| Sanctum Scan evidence/verifier/identity review | Admin routes in [`routes/admin.php:98`](routes/admin.php:98) |
| Extensions/modules | `ExtensionsController`, `ModuleSetting` |

### 1.6 Installer and deployment

| Feature | Evidence | Status |
|---|---|---|
| Web installer (requirements, DB config, migration, admin setup) | [`resources/installer/src/routes/web.php`](resources/installer/src/routes/web.php) | Implemented (static); reachable when installer files exist |
| Installer reset | [`resources/installer/src/routes/web.php:38`](resources/installer/src/routes/web.php:38) | Implemented (static); unauthenticated environment write |
| Root rewrite fallback | [`index.php`](index.php), [`.htaccess`](.htaccess) | Implemented (static) |

---

## 2. Sanctum Scan protocol slices

| Milestone | Capability | Code state | Test state | Docs state |
|---|---|---|---|---|
| M002 | Opaque scan references + public resolution | Implemented (static): issuer/resolver, hashed storage, expiry/revocation | `ScanReferenceResolutionTest` (7 tests) | ADR 0002; README |
| M003 | Merchant identity and QR lifecycle | Implemented (static): merchant issuance/list/revoke, audit actor | `MerchantScanReferenceLifecycleTest` (7 tests) | ADR 0003 |
| M004 | Public scan page + economic resource registry | Implemented (static): product/batch registry, provenance events, QR rotation, archival | `EconomicResourceRegistryTest` (9 tests) | ADR 0004; API guide |
| M005 | Economic exchange + transaction evidence | Implemented (static): offer/accept/decline/dispatch/receipt, one payment reference, immutable unverified evidence | `EconomicExchangeLifecycleTest` (multiple scenarios) | ADR 0005; API guide |
| M006 | Bilateral dispute and resolution | Implemented (static): one dispute, messages, propose/accept/reject/withdraw | `EconomicExchangeLifecycleTest` dispute scenarios | ADR 0006; API guide |
| M007 | Private dispute evidence + platform review | Implemented (static): private storage, MIME/size limits, SHA-256, admin review | `EconomicExchangeLifecycleTest` evidence scenarios | ADR 0007; API guide |
| M008 | Verifier registry, mandates, attestations | Implemented (static): institutions, memberships, mandates, attestations | `EconomicExchangeLifecycleTest` verifier scenarios | ADR 0008; API guide |
| M009 | Credentials, conflicts, appeals | Implemented (static): credential reviews, conflict declarations, appeals/decisions | `EconomicExchangeLifecycleTest` governance scenarios | ADR 0009; API guide |
| M010 | Versioned attestation corrections + withdrawal | Implemented (static): immutable versions, terminal withdrawal | `EconomicExchangeLifecycleTest` revision scenarios | ADR 0010; API guide |
| M011 | Independent reassessment | Implemented (static): new mandate, different member, append-only link | `EconomicExchangeLifecycleTest` reassessment scenarios | ADR 0011; API guide |
| M012 | Participant-scoped provenance export | **Docs only on this branch** | None | Roadmap/README claim implemented on a dependent branch |
| M013 | Offline provenance manifest verifier | **Docs only on this branch** | None | Roadmap/README claim implemented on a dependent branch |
| M014 | Provenance verifier hardening | **Docs only on this branch** | None | Roadmap claim; ADR 0014 missing |
| M015 | Manual buyer identity review + optional gate | Implemented (static) but with duplicate admin implementations | `EconomicExchangeLifecycleTest` identity scenarios | ADR 0012, ADR 0015, API guide; inconsistent status code |
| M016 | Payment settlement state machine | **Partial**: migration + orphan service; no caller/route/test | None | Roadmap says not started |

**Coupling fact:** M002–M015 services do not move funds and do not read/write legacy wallet or transaction tables. Preserve this boundary.

---

## 3. User journeys

### 3.1 Legacy user onboarding and wallet funding

1. Register with email/phone; verify email and SMS.
2. Complete KYC if required by deployment; set a transaction PIN; optionally enable 2FA.
3. Open a wallet; fund via a gateway or admin-approved manual method.
4. Receive a transaction record and notification.
5. Spend via send money, payment link, bill pay, top-up, remittance, virtual card, or gift card.

Failure points: gateway callbacks vary in signature verification; wallet updates are not uniformly locked; nested transactions can produce partial settlement.

### 3.2 Merchant QR payment (developer API)

1. Merchant creates API credentials and configures gateway settings.
2. Merchant server obtains a short-lived order token.
3. Payer opens hosted checkout, authenticates, and confirms.
4. The platform debits the payer wallet and credits the merchant wallet (or charges a card), then marks the order `SUCCESS`.
5. Payer is redirected; merchant reconciles by querying its own system or the platform UI.

There is no documented server-to-server merchant settlement webhook in the QR gateway route file.

### 3.3 Payment link

1. User/merchant creates a payment link with amount/currency.
2. Payer opens the share URL.
3. Payer pays by wallet, card, or external gateway.
4. Platform records the transaction and redirects to success/cancel.
5. Link owner receives a notification.

### 3.4 Public scan

1. Anyone scans a QR or opens `/scan/{reference}`.
2. Resolver validates format, hash, status, expiry, revocation, merchant/resource state.
3. Viewer sees merchant-declared resource details, provenance events, and a disclaimer.
4. No payment, ownership proof, quality verification, or delivery claim is made.

### 3.5 Sanctum Scan exchange

1. Merchant publishes a product or agricultural batch and may rotate a scan reference.
2. Merchant issues an offer to a named eligible buyer (verified email + SMS; optional identity gate applies only at acceptance).
3. Buyer lists and accepts or declines. Acceptance snapshots the agreement; the offer cannot be accepted after expiry or resource archival.
4. Merchant dispatches after acceptance.
5. Buyer confirms receipt after dispatch.
6. Buyer submits one payment reference (rail, reference, amount, currency). It must match the agreed amount/currency and remains unverified.
7. Settlement is **not** part of M005; M016 is incomplete.

### 3.6 Dispute and verification

1. Either participant opens one dispute after acceptance.
2. Participants exchange append-only messages and may propose a resolution.
3. Only the counterparty can accept/reject; rejection returns to open; initiator may withdraw before a response.
4. Participants may attach private evidence while the dispute is open.
5. Admin reviews evidence; independent verifiers may be mandated, declare conflicts, and attest.
6. Attestations can be corrected/withdrawn via versioned revisions, reassessed under a new mandate, and appealed.
7. None of these steps moves funds or changes settlement state.

### 3.7 Admin identity review and gate

1. An authorised admin records an identity review with method, internal reference, verdict, and summary.
2. A `verified` verdict sets the user’s projection; a later non-verified verdict clears it.
3. If the gate is enabled, buyer acceptance returns `403 buyer_identity_unverified` until a current verified projection exists.
4. Merchant responses never reveal the state.

---

## 4. State machines

| Entity | States / transitions | Enforcement |
|---|---|---|
| Legacy payment order | `CREATED -> SUCCESS` or `EXPIRED` | Controller checks; no DB constraint |
| Legacy transaction | Success/failed/pending/rejected constants | Application writes; no DB constraint |
| Exchange | `offered -> accepted -> dispatched -> received`; `offered -> declined/cancelled/expired` | Service/controller checks inside transactions; model `$guarded=['id']` |
| Dispute | `open -> resolution_proposed -> resolved`; `resolution_proposed -> open`; `open -> withdrawn` | Service transaction + unique exchange index |
| Verifier membership | `invited -> active/declined`; `active <-> suspended`; any non-revoked -> `revoked` | Admin/service methods |
| Mandate | `active -> revoked/completed` | Service + admin actions |
| Attestation revision | `version 1 -> correction/withdrawal versions`; revoked terminal | Unique chain indexes + service checks |
| Identity review | Append-only verdicts; current projection on `users` | Service transaction + user lock |
| Settlement | `awaiting_payment -> payment_evidence_submitted -> payment_verified/payment_rejected`; `payment_verified -> settled`; `payment_rejected -> payment_evidence_submitted`; `-> cancelled` | Orphan service; not wired; cancel path broken |

---

## 5. Feature-to-test matrix

| Area | Test evidence | Gaps |
|---|---|---|
| Scan reference resolution | [`ScanReferenceResolutionTest.php`](tests/Feature/SanctumScan/ScanReferenceResolutionTest.php) | No HTTP contract test; no malformed/hash timing tests |
| Merchant reference lifecycle | [`MerchantScanReferenceLifecycleTest.php`](tests/Feature/SanctumScan/MerchantScanReferenceLifecycleTest.php) | No admin/ops lifecycle tests |
| Economic resource registry | [`EconomicResourceRegistryTest.php`](tests/Feature/SanctumScan/EconomicResourceRegistryTest.php) | No QR image generation test; no public page rendering test |
| Exchange/dispute/evidence/verification/identity | [`EconomicExchangeLifecycleTest.php`](tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php) (35 methods) | No settlement; no concurrency; no admin role/2FA tests; no state-machine exhaustive matrix |
| Environment template | [`EnvironmentTemplateSecurityTest.php`](tests/Feature/EnvironmentTemplateSecurityTest.php) | Does not boot the full app in the current revision |
| Legacy commerce | None | Entire legacy surface untested |
| Webhooks | None | Signature/authorization tests absent |
| Installer | None | Post-install exposure untested |
| Routes | None | Duplicate names, public-route allowlist, middleware assertions absent |

---

## 6. Documentation drift and inconsistencies

| Claim | Reality in this revision |
|---|---|
| Roadmap/README: M012–M014 implemented | No provenance export route/service/command exists on this branch (`grep` found none). |
| Roadmap: M016 “Planned / not started” | Migration + orphan service exist; no integration or tests. |
| Roadmap: M015 “In progress, not merged” | M015 code and tests are present on this branch. |
| ADR 0014 referenced by roadmap | File is absent. |
| ADR 0012: gate returns 422 | Implementation and API doc return 403 (`buyer_identity_unverified`). |
| ADR 0015: gate optional | Matches code, but conflicts with ADR 0012 framing/status code. |
| Roadmap maps M012→ADR 0012 and M013→ADR 0013 | Those ADRs cover identity prerequisites and quantity visibility, not provenance. |
| README: “Implemented protocol slices” | True for M002–M011; M012–M014 are branch-only/absent; M015 is present but duplicated; M016 is partial. |
| API docs | Generally align with code for M002–M011 and M015; settlement is undocumented. |

---

## 7. Explicit limits and non-goals

- Sanctum Scan does **not** move money, reserve stock, verify delivery, adjudicate disputes, or reconcile provider settlement.
- Platform review and independent attestations are accountable statements, not cryptographic proof of identity, title, quality, or payment.
- Manual identity review is not legal identity proofing, AML compliance, or cross-guard uniqueness.
- M017 wallet settlement, external mobile-money rails, biometric KYC, tokenised assets, AI-authorised transactions, and impact verification are deferred.
- Legacy features are inherited and not covered by a root license; redistribution rights remain unresolved.
- No claim is made that legacy features are production-ready or secure; several concrete risks are documented in [`docs/engineering/RISK_REGISTER.md`](docs/engineering/RISK_REGISTER.md).
