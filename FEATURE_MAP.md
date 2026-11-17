# Feature Map — QRPay / Sanctum Scan

**Status:** Continuously reconciled feature map  
**Date:** 2026-11-17  
**Branch:** `main` at `e9cdf766`; M020 in progress on `sanctum-scan/m020-evidence-decision-binding`

## Status legend

Milestone status follows ADR 0017:

| Label | Meaning |
|---|---|
| **Planned** | Agreed, not started. |
| **In progress** | Code or documentation is being developed on a branch; not merged. |
| **Implemented** | Merged code path exists; this does not claim verification. |
| **Verified** | Named automated checks pass in CI and required manual evidence is recorded. |
| **Retired** | Intentionally removed; the superseding record is linked. |

Legacy QRPay feature entries marked “Implemented (static)” are inventory findings,
not a declaration that the inherited feature set is production-ready or tested.


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
| M012 | Participant-scoped provenance export | Implemented: buyer/merchant scoped JSON manifest, exchange/dispute timeline, evidence and governance history, SHA-256 digest | `EconomicExchangeLifecycleTest::test_exchange_participants_can_export_a_hashed_provenance_manifest_without_private_storage_or_actor_identifiers` | Provenance export API guide; legacy design note requires ADR-ID reconciliation |
| M013 | Offline provenance manifest verifier | Implemented: bounded offline Artisan verifier for schema 1.0.0 manifests | `ProvenanceManifestVerifierTest`, `ProvenanceManifestVerifierCommandTest` | Offline provenance verifier guide; command `sanctum-scan:verify-provenance` |
| M014 | Provenance verifier hardening | Implemented: structural, digest, revision and reassessment consistency checks | `ProvenanceManifestVerifierTest` | Legacy hardening note exists; its ADR identifier conflicts with the canonical index and is tracked for M021 reconciliation |
| M015 | Manual buyer identity review + optional gate | Implemented: append-only admin review and optional acceptance prerequisite | `EconomicExchangeLifecycleTest` identity scenarios; `AdminOperatorMiddlewareTest` production-stack coverage | ADR 0012/0015 and API guide; implemented denial contract is 403 |
| M016 | Payment settlement state machine | Implemented: typed transition table, actor-bound manager, atomic evidence submission, cancellation consistency and admin API; no fund movement | `SettlementStateMachineTest`, `SettlementLifecycleTest`, `SettlementBackfillMigrationTest`, MySQL-only `SettlementConcurrencyTest` | ADR 0016/0017, QODOR-002; GitHub CI execution still pending |
| M017 | Deterministic verification gates | Implemented: G1/G2/G3/G4/G5/G7/G8 wired; G6 deferred | `composer check`, `RouteRegistryTest`, MySQL gate sequence | ADR 0016; GitHub Actions execution and branch protection pending |
| M018 | Operator-surface hardening | Implemented: real admin middleware stack exercised; duplicate identity controller removed | `AdminOperatorMiddlewareTest` | M018 record; ADR 0012 contract aligned |
| M019 | Settlement evidence versioning | Implemented: append-only version chain and buyer-only resubmission after rejection | `SettlementLifecycleTest`, `PaymentEvidenceVersioningMigrationTest` | ADR 0018; unsafe rollback after multiple versions remains a limitation |
| M020 | Evidence-version-bound settlement decisions | In progress on M020 branch: immutable decisions bind exact evidence ID/version/fingerprint | `SettlementLifecycleTest` assertions added; execution pending | ADR 0019; no schema migration required |

**Coupling fact:** M002–M020 Sanctum Scan services do not move funds and do not read/write legacy wallet or transaction tables. Preserve this boundary.

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
6. Buyer submits payment evidence (rail, reference, amount, currency). It must match the agreed amount/currency and remains unverified. After an administrator rejects a version, the buyer may append a replacement version; prior evidence is not overwritten.
7. Submitting evidence advances settlement `awaiting_payment -> payment_evidence_submitted` (or `payment_rejected -> payment_evidence_submitted` for a buyer resubmission) in the same transaction. Administrator verification, rejection and settlement events identify the exact evidence version and its SHA-256 fingerprint; no step moves funds.
8. Declining, cancelling, or expiring an exchange before verification cancels settlement. Buyer and merchant responses expose the settlement status; public scan payloads do not.

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
| Settlement | `awaiting_payment -> payment_evidence_submitted` (api/system); `-> cancelled` (api/merchant_api/system); `payment_evidence_submitted -> payment_verified` or `payment_rejected` (admin only); `-> cancelled` (api/merchant_api/system); `payment_verified -> settled` (admin only); `payment_rejected -> payment_evidence_submitted` (api only, new evidence version); settled/cancelled terminal | `SettlementStateMachine` transition table enforced by `ExchangeSettlementManager`; every transition appends exactly one `settlement_status_changed` event; evidence rows are append-only versions (`version`, `supersedes_id`); no money movement |

---

## 5. Feature-to-test matrix

| Area | Test evidence | Gaps |
|---|---|---|
| Scan reference resolution | [`ScanReferenceResolutionTest.php`](tests/Feature/SanctumScan/ScanReferenceResolutionTest.php) | No HTTP contract test; no malformed/hash timing tests |
| Merchant reference lifecycle | [`MerchantScanReferenceLifecycleTest.php`](tests/Feature/SanctumScan/MerchantScanReferenceLifecycleTest.php) | No admin/ops lifecycle tests |
| Economic resource registry | [`EconomicResourceRegistryTest.php`](tests/Feature/SanctumScan/EconomicResourceRegistryTest.php) | No QR image generation test; no public page rendering test |
| Exchange/dispute/evidence/verification/identity | [`EconomicExchangeLifecycleTest.php`](tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php) | Domain lifecycle and governance coverage; manager-level concurrency coverage remains a separate M022 task |
| Settlement | [`SettlementStateMachineTest.php`](tests/Unit/SanctumScan/SettlementStateMachineTest.php), [`SettlementLifecycleTest.php`](tests/Feature/SanctumScan/SettlementLifecycleTest.php), [`SettlementBackfillMigrationTest.php`](tests/Feature/SanctumScan/SettlementBackfillMigrationTest.php), [`SettlementConcurrencyTest.php`](tests/Feature/SanctumScan/SettlementConcurrencyTest.php) | MySQL test currently proves lock contention and stale expected-status handling sequentially; simultaneous competing manager transitions are still M022. It is skipped outside MySQL. |
| Environment template | [`EnvironmentTemplateSecurityTest.php`](tests/Feature/EnvironmentTemplateSecurityTest.php) | Does not boot the full app in the current revision |
| Legacy commerce | None | Entire legacy surface untested |
| Webhooks | None | Signature/authorization tests absent |
| Installer | None | Post-install exposure untested |
| Routes | [`RouteRegistryTest.php`](tests/Feature/RouteRegistryTest.php) | Sanctum names are unique and settlement actions resolve; 14 pre-existing legacy duplicate names remain outside this namespace |

---

## 6. Documentation drift and inconsistencies

| Previously reported claim | Current repository evidence / remaining action |
|---|---|
| M012–M014 were docs-only or absent | Superseded by merged code: participant-scoped provenance export route/controller and lifecycle test; offline verifier Artisan command and unit/command tests; structural consistency hardening. See `SanctumScanDisputeEvidenceController::provenanceExport`, `ProvenanceManifestVerifier`, and the M012–M014 test/doc records. |
| M016 settlement was planned or incomplete | Superseded by merged implementation: typed state machine, locked manager, buyer evidence transition, administrator endpoint, participant projections, backfill and tests. It records settlement assertions and moves no funds. |
| M015 remained in progress with duplicate identity controller | Superseded by M018: the duplicate controller was removed; the real admin middleware stack is exercised by `AdminOperatorMiddlewareTest`; the implemented identity-gate contract is 403. |
| ADR 0014 was missing | A legacy proposed provenance-hardening note exists at `docs/architecture/decisions/0014-provenance-verifier-consistency-hardening.md`; it conflicts with the canonical ADR index, which reserves 0014. M021 must reconcile the legacy 0012/0013/0014 provenance notes without rewriting accepted identity/quantity ADRs or breaking links. |
| Provenance ADR links pointed to identity/quantity ADRs | The legacy proposed participant-export and offline-verifier notes use duplicate IDs 0012 and 0013. Canonical new decisions live in `docs/decisions/README.md`; duplicate legacy identifiers are tracked for M021. |
| API docs did not reflect current settlement behavior | Buyer/merchant payment-evidence projections expose the latest version; admin history includes all versions; M020 adds evidence ID/version/fingerprint to settlement decision events. |
| Settlement evidence versioning has no rollback caveat | ADR 0018 documents that `down()` cannot restore a one-row-per-exchange constraint once multiple versions exist without destroying history. Explicit fail-safe rollback enforcement and tests remain M023. |

---

## 7. Explicit limits and non-goals

- Sanctum Scan does **not** move money, reserve stock, verify delivery, adjudicate disputes, or reconcile provider settlement.
- Platform review and independent attestations are accountable statements, not cryptographic proof of identity, title, quality, or payment.
- Manual identity review is not legal identity proofing, AML compliance, or cross-guard uniqueness.
- M017 wallet settlement, external mobile-money rails, biometric KYC, tokenised assets, AI-authorised transactions, and impact verification are deferred.
- Legacy features are inherited and not covered by a root license; redistribution rights remain unresolved.
- No claim is made that legacy features are production-ready or secure; several concrete risks are documented in [`docs/engineering/RISK_REGISTER.md`](docs/engineering/RISK_REGISTER.md).
