# Engineering Risk Register — QRPay / Sanctum Scan

**Status:** QODOR-001 baseline; updated 2026-11-16 after the QODOR-002 settlement slice  
**Date:** 2026-11-15 (baseline), 2026-11-16 (status update)  
**Revision:** `feature/qrpay-update` at `4ddb392` (baseline); working tree after QODOR-002  
**Scale:** P0 = release/boot blocker; P1 = material security, correctness, or delivery risk; P2 = maintainability or operational risk.

Likelihood/impact are engineering judgements unless marked **verified**. “Verified” means the condition was directly observed in code or by an executed command.

---

## P0 — Must be resolved before any further feature work

### R-01 — Application cannot boot at HEAD (committed merge-conflict markers)

- **Likelihood:** Certain (verified)
- **Impact:** Critical — every HTTP request and CLI command fails
- **Evidence:** [`app/Models/Admin/BasicSettings.php:37`](../../app/Models/Admin/BasicSettings.php#L37) contains `<<<<<<<`, `=======`, `>>>>>>>`. `php artisan --version` returns `ParseError`; PHPUnit reports 62 tests, 1 assertion, 61 errors.
- **Root cause:** Commit `4ddb392` committed an unresolved merge conflict.
- **Mitigation:** Resolve the conflict in a dedicated commit; run `php -l`, `php artisan --version`, and the full suite. Add a pre-commit and CI conflict-marker check (see R-02).
- **Owner:** Current branch owner
- **Status:** Resolved 2026-11-16 — conflict resolved on the HEAD side; `php artisan --version` reports 12.72.0; `composer check` is green (94 tests, 1,017 assertions, 1 MySQL-only skip) and enforces the conflict-marker gate.

### R-02 — CI does not validate the active branch and runs almost nothing

- **Likelihood:** Certain (verified)
- **Impact:** Critical — defects reach the branch/main undetected
- **Evidence:** At the examined revision, [`.github/workflows/sanctum-scan-php-lint.yml`](../../.github/workflows/sanctum-scan-php-lint.yml) triggered only on `sanctum-scan/**` pushes and `main` PRs, linted changed files, and executed one test file. M017 replaced that workflow.
- **Mitigation:** Trigger on all branches/PRs; run all tests, Pint, static analysis, `composer audit`, route checks, and a MySQL migration job. Enable branch protection.
- **Status:** Mitigated in code 2026-11-16 (M017) — the workflow triggers on every push/PR and runs G1, G2, G3, G5 assertions, G7, G8 plus a MySQL job. Remaining: first GitHub execution and branch protection are unverified; G6 (PHPStan) is deferred.

### R-03 — Unauthenticated webhooks can mutate financial state

- **Likelihood:** High (verified routes; exploitability depends on deployment)
- **Impact:** Critical — balance manipulation, false transaction status
- **Evidence:**
  - Mobile top-up webhook: route registered without auth at [`routes/global.php:25`](../../routes/global.php#L25); handler trusts request `data.status` and updates/refunds wallet balances at [`app/Http/Controllers/GlobalController.php:203`](../../app/Http/Controllers/GlobalController.php#L203).
  - Flutterwave withdrawal webhook: route strips `web` at [`routes/global.php:46`](../../routes/global.php#L46); handler trusts request payload and adjusts wallet balance at [`app/Http/Controllers/GlobalController.php:99`](../../app/Http/Controllers/GlobalController.php#L99).
  - No signature verification was found in either handler.
- **Mitigation:** Verify provider signatures/timestamps and bind to the stored transaction; reject unsigned payloads; add replay tests. If the provider cannot sign, require server-side reconciliation instead of trusting the webhook.
- **Status:** Open

### R-04 — Public cache/config/route clearing endpoints

- **Likelihood:** Certain (verified)
- **Impact:** High — trivial DoS/cache stampede; configuration/route cache churn
- **Evidence:** `GET /api/clear-cache` ([`routes/api/api.php:59`](../../routes/api/api.php#L59)), `/merchant-api/clear-cache` ([`routes/api/merchant_api.php:37`](../../routes/api/merchant_api.php#L37)), `/agent-api/clear-cache` ([`routes/api/agent_api.php:37`](../../routes/api/agent_api.php#L37)) have no auth. The admin variant calls a non-existent `Artisan::all()` and is broken ([`routes/admin.php:791`](../../routes/admin.php#L791)).
- **Mitigation:** Remove these routes; use an authenticated, rate-limited operational command. Add a public-route allowlist test.
- **Status:** Open

### R-05 — Broadcast/push token issuance trusts caller-supplied user ID

- **Likelihood:** High (verified route/middleware removal)
- **Impact:** High — privacy breach; attacker can obtain a push token for another account
- **Evidence:** `pusher/beams-auth` removes `api`, `auth.api`, and related middleware ([`routes/api/api.php:144`](../../routes/api/api.php#L144)); controller uses `request()->user_id` ([`app/Http/Controllers/Api/User/AuthorizationController.php:632`](../../app/Http/Controllers/Api/User/AuthorizationController.php#L632)).
- **Mitigation:** Require authentication and derive the user ID from the authenticated principal; add an authorization test.
- **Status:** Open

### R-06 — Admin RBAC fails open for unnamed or unlisted routes

- **Likelihood:** High (verified helper logic)
- **Impact:** High — any authenticated admin can reach routes outside the permission map
- **Evidence:** [`app/Http/Helpers/helpers.php:1298`](../../app/Http/Helpers/helpers.php#L1298) returns `true` when the current route name is not in `permission_protected()`; `RoleGuard` denies only on `false` ([`app/Http/Middleware/Admin/RoleGuard.php:29`](../../app/Http/Middleware/Admin/RoleGuard.php#L29)). Eight routes are unconditionally skipped ([`helpers.php:1197`](../../app/Http/Helpers/helpers.php#L1197)).
- **Mitigation:** Default-deny for admin routes with unknown names; maintain an explicit allowlist; add a test that enumerates admin routes and asserts permission coverage.
- **Status:** Open

### R-07 — Installer reset route remains reachable after installation

- **Likelihood:** High (verified)
- **Impact:** High — rewrites environment configuration; can force reinstall flow
- **Evidence:** [`resources/installer/src/routes/web.php:38`](../../resources/installer/src/routes/web.php#L38) is unauthenticated and writes `PRODUCT_KEY`; installer routes are registered whenever files exist ([`app/Providers/RouteServiceProvider.php:109`](../../app/Providers/RouteServiceProvider.php#L109)).
- **Mitigation:** Remove installer files from production deployments or gate them on an explicit, server-side lock and authentication. Add a post-install exposure test.
- **Status:** Open

### R-08 — Legacy wallet paths use non-locking read-modify-write and nested transactions

- **Likelihood:** Medium-High (verified pattern; double-spend is a hypothesis until concurrency-tested)
- **Impact:** Critical — double debit/credit, partial settlement, lost funds
- **Evidence:**
  - QR confirm reads and updates wallet models without `lockForUpdate` ([`app/Http/Controllers/PaymentGateway/QrPay/v1/PaymentController.php:708`](../../app/Http/Controllers/PaymentGateway/QrPay/v1/PaymentController.php#L708)).
  - Make-payment opens separate transactions in sender and receiver helpers ([`app/Http/Controllers/User/MakePaymentController.php:210`](../../app/Http/Controllers/User/MakePaymentController.php#L210), `:242`), so a sender debit can commit before a receiver credit failure.
  - `lockForUpdate` appears almost exclusively in Sanctum Scan services, not legacy wallet flows.
- **Mitigation:** Before refactoring, add characterization and concurrency tests on MySQL. Then consolidate each business action into one transaction with deterministic lock order and idempotency keys.
- **Status:** Open — separate financial review required

### R-09 — QR gateway charges a card before validating the order/status

- **Likelihood:** Medium (verified code order)
- **Impact:** High — charge without a valid order, replay/double-charge risk
- **Evidence:** `paymentConfirm` tokenises and charges Stripe at [`PaymentController.php:260`](../../app/Http/Controllers/PaymentGateway/QrPay/v1/PaymentController.php#L260) before the null check at line 293 and status check at line 387. No row lock is used.
- **Mitigation:** Validate order existence, expiry, status, amount, and currency inside a lock before any provider charge; make confirmation idempotent; add a replay test.
- **Status:** Open

---

## P1 — Material security, correctness, or delivery risk

### R-10 — Test suite cannot validate migrations, middleware, or concurrency

- **Likelihood:** Certain (verified)
- **Impact:** High — false confidence in financial and protocol behavior
- **Evidence:** Tests hand-build SQLite schemas, manually call migration `up()` on selected files, and strip middleware; SQLite `lockForUpdate` does not block. The MySQL job that would cover this is wired in M017 but has not executed.
- **Mitigation:** Adopt `RefreshDatabase` for feature tests, add a MySQL CI job, remove test-only middleware bypasses except where explicitly justified, and add concurrency tests for locked paths.
- **Status:** Partially mitigated 2026-11-16 — settlement now has a pure transition matrix, a migration/backfill test, and a MySQL-gated concurrency test that the CI MySQL job runs. The broader suite still uses handcrafted schemas and middleware bypasses. PHPStan/Larastan is still not installed.

### R-11 — Settlement state machine is orphaned and internally inconsistent

- **Likelihood:** Certain (verified)
- **Impact:** High — misleading capability, broken cancellation, future money risk
- **Evidence (baseline):** [`app/Services/SanctumScan/ExchangeSettlementManager.php`](../../app/Services/SanctumScan/ExchangeSettlementManager.php) had no caller; `onPaymentEvidenceSubmitted()` was never invoked; `cancel()` passed `cancelled`, which was not an allowed transition from `awaiting_payment`; `'system'` bypassed transition validation. The settlement migration was not applied in tests.
- **Mitigation:** Implemented the QODOR-002 slice: typed `SettlementStateMachine`, rewritten manager with no system bypass, buyer evidence transition, closed-exchange consistency, admin show/transition API, reversible backfill, and four test suites.
- **Status:** Resolved 2026-11-16 — verified by `SettlementStateMachineTest` (144-combination matrix), `SettlementLifecycleTest` (HTTP lifecycle, conflicts, authorisation), `SettlementBackfillMigrationTest`, and the MySQL-gated `SettlementConcurrencyTest`. MySQL concurrency remains unexecuted until the G4 CI job exists.

### R-12 — Duplicate admin identity implementations and route-name collision

- **Likelihood:** Certain (verified)
- **Impact:** Medium-High — ambiguous authorization, inconsistent validation, maintenance hazard
- **Evidence:** `SanctumScanUserIdentityController` and `SanctumScanUserIdentityReviewController` both exist; two admin route groups use the `sanctum.identity-checks.` name prefix ([`routes/admin.php:100`](../../routes/admin.php#L100), `:167`), producing duplicate `index`/`store` names. Validation differs (`status` vs `review_status`; summary minimum length).
- **Mitigation:** Choose one controller/contract, remove the duplicate, and add a route-name uniqueness test.
- **Status:** Resolved 2026-11-16 for the Sanctum surface — the duplicate admin identity route block and the superseded `SanctumScanUserIdentityController` were removed; `RouteRegistryTest` asserts Sanctum route-name uniqueness. Fourteen legacy duplicate names remain outside the Sanctum namespace (R-26).

### R-13 — Documentation and milestone status are not trustworthy

- **Likelihood:** Certain (verified)
- **Impact:** Medium-High — agents and engineers build on false premises
- **Evidence:** M012–M014 are claimed implemented but absent on this branch; M016 is “not started” despite schema/service; M015 is “in progress” but present; ADR 0014 missing; ADR 0012 says 422 while code/API use 403; roadmap ADR mappings are wrong.
- **Mitigation:** Make status evidence-based; link each milestone to code/tests; add a capability manifest and verification command; reconcile ADRs.
- **Status:** Partially mitigated 2026-11-16 — M016/M015 statuses corrected; [`ROADMAP-STATUS.md`](ROADMAP-STATUS.md) maps roadmap phases to verified evidence; ADR 0016/0017 accepted with implementation notes; ADR 0012 reconciled to the implemented 403 contract. M012–M014 drift and ADR 0014 remain.

### R-14 — Route boot queries the database and can silently change the admin prefix

- **Likelihood:** Medium (verified code; trigger depends on DB/settings availability)
- **Impact:** Medium-High — availability, confusing admin routing, security-by-obscurity failure
- **Evidence:** [`app/Providers/RouteServiceProvider.php:35`](../../app/Providers/RouteServiceProvider.php#L35) queries `basic_settings` on every request and swallows all throwables; the prefix falls back to `admin` at `:62`.
- **Mitigation:** Cache configuration deterministically; fail closed or use a static configured prefix; avoid DB access in route registration.
- **Status:** Open

### R-15 — Dependency and supply-chain risk

- **Likelihood:** Medium (verified advisory and repository configuration)
- **Impact:** Medium-High — known DoS advisory, non-reproducible or unverifiable packages
- **Evidence:** `phpseclib/phpseclib 4.0.1` has advisory GHSA-w3p9-g4p2-h3rr (unbounded PBKDF iterations); `vonage/nexmo-bridge` is abandoned; `composer.json` uses `minimum-stability: dev` and a custom Composer repository for `reachead/laravel-sdk`; the lock has no dist reference for that package; Symfony 6.4 components coexist with Laravel 12; Stripe SDK v10 is EOL; no `package-lock.json`.
- **Mitigation:** Update phpseclib; replace abandoned packages; pin and verify custom packages; run `composer audit` in CI; commit a frontend lockfile; document the Symfony compatibility decision.
- **Status:** Partially mitigated 2026-11-16 — `composer validate --strict` and an advisory gate now run in CI. The phpseclib advisory is allowlisted under R-28 because Passport pins the affected version. `package-lock.json` is still absent; no build gate claims reproducibility.

### R-16 — Public/legacy file handling lacks consistent authorization

- **Likelihood:** Medium (verified route/controller behavior; impact depends on stored data)
- **Impact:** Medium-High — unauthorized download/deletion of files
- **Evidence:** `GET file/download/{path_source}/{name}` is unauthenticated ([`routes/global.php:34`](../../routes/global.php#L34)). `FileController@removeFile` deletes a caller-supplied path/filename on the default disk with no ownership check ([`app/Http/Controllers/FileController.php:86`](../../app/Http/Controllers/FileController.php#L86)). The route is available to admin/user/agent/merchant. `.env.example` sets the default disk to `public`.
- **Mitigation:** Require authorization and tenant scoping; store sensitive files on the private disk; serve through a scoped controller; add traversal/ownership tests.
- **Status:** Open

### R-17 — Identity-review implementation does not consistently check admin status

- **Likelihood:** Medium (verified)
- **Impact:** Medium — a disabled admin session may still record reviews
- **Evidence:** `SanctumScanUserIdentityController::store()` lacked the `abort_unless($admin && $admin->status)` check used by the review controller; `adminId()` only returned the ID. The file was unreferenced after the duplicate route block was removed.
- **Mitigation:** Consolidated on `SanctumScanUserIdentityReviewController` and deleted the superseded controller.
- **Status:** Resolved 2026-11-16 — only the review controller is routed ([`routes/admin.php:101`](../../routes/admin.php#L101)); a repository-wide search finds no remaining reference to the deleted class.

### R-18 — Lock-order and uniqueness gaps in governance flows are untested

- **Likelihood:** Medium (inference from code paths)
- **Impact:** Medium — deadlocks or duplicate active mandates/versions under concurrency
- **Evidence:** No MySQL concurrency tests; mandates have indexes but no unique active constraint; revision version is computed from the latest row; SQLite tests cannot detect lock-order inversion.
- **Mitigation:** Define and document lock order per flow; add DB constraints where possible; add MySQL concurrency tests.
- **Status:** Open

### R-19 — No production-like migration verification

- **Likelihood:** Certain (verified)
- **Impact:** Medium-High — schema drift or migration failure during deployment
- **Evidence:** 205 migrations; tests run selected migrations manually on SQLite; no CI job runs `migrate:fresh` on MySQL.
- **Mitigation:** Add a MySQL migration job and a deployment preflight check; test `down()` where safe.
- **Status:** Partially mitigated 2026-11-16 — the settlement column and backfill have exercised `up()`/`down()` on SQLite, and the CI MySQL job runs `migrate:fresh`, the backfill down/up, and the concurrency test. The full migration set is still unverified until that job executes.

---

## P2 — Maintainability and operational risk

### R-20 — No structured observability or audit export

- **Evidence:** Logging is mostly `Log::notice`/`logger()`; no correlation IDs, metrics, or trace export. Sanctum Scan audit history is in database tables but has no operational dashboard.
- **Mitigation:** Add structured logs for state transitions and webhook verification; expose read-only operational metrics; never log secrets or personal payloads.

### R-21 — Code-style and static-analysis drift

- **Evidence:** Pint reports 6 style issues across 8 Sanctum Scan service files; no PHPStan/Larastan.
- **Mitigation:** Add Pint `--test` and Larastan to CI; fix incrementally, starting with the bounded module.
- **Status:** Partially mitigated 2026-11-16 — Pint `--test` is enforced on changed files in CI and passes on all 30 changed PHP files. Unchanged legacy files still report style issues; PHPStan is not installed, so static analysis remains unmitigated.

### R-22 — PHP/runtime version drift

- **Evidence:** `composer.json` requires `^8.2`; CI uses 8.3; local session used 8.4.13. PHP 8.2 is in security-only support.
- **Mitigation:** Choose a supported target, pin it in CI and deployment, and test the minimum supported version.

### R-23 — Unresolved source provenance and licensing

- **Evidence:** No root `LICENSE`; `composer.json` says MIT but the repository has a commercial lineage; custom SDK; README says redistribution rights are unresolved ([`docs/security/source-provenance.md`](../security/source-provenance.md)).
- **Mitigation:** Complete provenance review before relicensing or redistributing; do not claim the inherited application is open source.

### R-24 — Deployment/rollback documentation absent

- **Evidence:** No Dockerfile, deploy script, release checklist, or rollback procedure; root rewrite fallback and public front controller both exist.
- **Mitigation:** Document the authoritative topology, migration/rollback steps, queue/scheduler supervision, backup/restore, and health checks.

### R-25 — Broad middleware bypass surface

- **Evidence:** 51 `withoutMiddleware` usages across route files; some remove the entire `web` group for callbacks.
- **Mitigation:** Require a justification and compensating control for each bypass; add an allowlist and tests.

### R-26 — Fourteen legacy route names are duplicated

- **Likelihood:** Certain (verified 2026-11-16)
- **Impact:** Medium — a global route-name uniqueness gate (ADR 0016 G5) cannot pass until they are renamed; ambiguous URL generation
- **Evidence:** `RouteRegistryTest` reports duplicate names in the legacy surface, including `api.user.add.money.payment.global.*`, `api.agent.add.money.payment.global.*`, `user.add.money.payment.global.*`, `agent.add.money.payment.global.*`, `payment-link.gateway.payment.global.*`, `user.qr.scan`, `request.money.`, and `qrpay.pay.`. Sanctum Scan names are unique.
- **Mitigation:** Rename the legacy duplicates in a dedicated change after confirming no external client depends on the names; then promote the uniqueness test from Sanctum-scoped to global.
- **Status:** Open

### R-27 — Settlement HTTP tests disable admin operator middleware

- **Likelihood:** Certain (verified)
- **Impact:** Medium — the tests prove controller behaviour but not that `app.mode`, RBAC, or 2FA protect the route in production
- **Evidence:** `SettlementLifecycleTest` disables `Admin\AppModeGuard` and `Admin\RoleGuard`; `Admin\GoogleTwoFactor` passed because the synthetic admin had 2FA disabled. Route registration kept the production middleware stack.
- **Mitigation:** Added `AdminOperatorMiddlewareTest`, which runs the unmodified production stack with seeded role/permission tables and covers: super-admin read + transition, role-less logout, non-super-admin without permission (404), demo-mode read allowed / write rejected, and pending 2FA redirect.
- **Status:** Resolved 2026-11-16 — 5 tests / 14 assertions green; current full suite 94 tests / 1,017 assertions.

### R-28 — phpseclib advisory cannot be fixed without replacing Passport

- **Likelihood:** Certain (verified 2026-11-16)
- **Impact:** Medium — `PKSA-h7cz-q7zq-mdkt` is a CPU-exhaustion DoS when parsing attacker-controlled encrypted keys, PFX, or CMS payloads
- **Evidence:** `phpseclib/phpseclib 4.0.1` is affected; `laravel/passport v13.11.0` (the latest release) requires exactly `4.0.1`, so Composer cannot resolve a fixed `>= 4.0.3` version while Passport is installed. `composer update phpseclib/phpseclib` fails on that pin.
- **Mitigation:** QRPay does not expose encrypted-key/PFX/CMS parsing to untrusted input. The advisory is allowlisted in `tools/check-dependency-audit.php` with a shrink-only assertion. Revisit when Passport publishes a compatible constraint, then remove the allowlist entry.
- **Status:** Accepted 2026-11-16

### R-29 — Four legacy routes reference missing controller methods

- **Likelihood:** Certain (verified 2026-11-16)
- **Impact:** Low-Medium — requests to these routes fail with a server error; no view or client references them
- **Evidence:** `RouteRegistryTest` found `PaymentLinkController@delete` (user and merchant) and `SenderRecipientController@checkUser` / `ReceiverRecipientController@checkUser` (agent) do not exist.
- **Mitigation:** The four signatures are allowlisted in `tools/route-registry-allowlist.php` with a shrink-only assertion so new dead routes fail the gate. A dedicated cleanup should remove the route definitions (turning the 500 into a 404) or implement the intended methods.
- **Status:** Accepted debt 2026-11-16

---

## Prioritised remediation sequence

1. ~~**R-01** — restore bootability.~~ Resolved 2026-11-16.
2. **R-02** — CI gates are wired (M017); confirm the first GitHub run, enable branch protection, and keep the MySQL job green.
3. **R-03 / R-04 / R-05 / R-06 / R-07** — close unauthenticated/unauthorized state-changing surfaces.
4. **R-08 / R-09** — financial-path review and concurrency characterization before any refactor.
5. **R-10 / R-19** — migration-backed MySQL tests and PHPStan ratchet. ~~R-27 production-middleware coverage~~ resolved 2026-11-16 (M018).
6. ~~**R-11** — complete the settlement slice (QODOR-002).~~ Resolved 2026-11-16; MySQL job wired in M017.
7. **R-13 / R-26 / R-29** — finish legacy duplicate/dead-route cleanup and remaining documentation reconciliation. ~~R-12 / R-17~~ resolved 2026-11-16 (M018).
8. **R-14 / R-15 / R-16 / R-18 / R-28** — hardening and dependency follow-up.
9. **R-20–R-25** — operational and maintainability work.
