# Testing Strategy — QRPay / Sanctum Scan

**Status:** QODOR-001 baseline  
**Date:** 2026-11-15  
**Revision examined:** `feature/qrpay-update` at `4ddb392`

This strategy starts from what the repository can prove today, not from an assumed mature test suite.

---

## 1. Execution evidence (verified)

| Command | Result |
|---|---|
| `./vendor/bin/phpunit --colors=never` | **62 tests, 1 assertion, 61 errors**, 1 PHPUnit deprecation, 58 risky |
| `./vendor/bin/phpunit tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php` | 35 tests, all error at bootstrap |
| `php artisan --version` | `ParseError: syntax error, unexpected token "<<"` |
| `php -l` over every tracked PHP file | 1 parse error (`BasicSettings.php:37`); 1 deprecation (`SendSms.php:10`) |
| `vendor/bin/pint --test app/Services/SanctumScan` | 8 files, 6 style issues |
| `composer audit --locked --no-dev` | 1 advisory: `phpseclib/phpseclib 4.0.1`; 1 abandoned package: `vonage/nexmo-bridge` |

**Root cause of the suite failure:** unresolved merge-conflict markers committed in [`app/Models/Admin/BasicSettings.php`](app/Models/Admin/BasicSettings.php), line 37. The application cannot boot, so no feature behavior is exercised.

**Mainline baseline:** not established. Running `main` in an isolated copy was blocked by sandbox policy in this session. Do not claim mainline tests pass until they are executed.

### Discovery vs execution

- Test methods found: 58 (`35 + 9 + 7 + 7` across four Sanctum Scan suites).
- PHPUnit discovered 62 tests; the difference is likely data-provider expansion or inherited examples. Treat the executed count as authoritative only when the suite runs.

---

## 2. Current test inventory

| File | Methods | What it covers | Structural limits |
|---|---:|---|---|
| [`ScanReferenceResolutionTest.php`](tests/Feature/SanctumScan/ScanReferenceResolutionTest.php) | 7 | hash-only storage, expiry, revocation, uniform 404, issuer validation | Services-only; no HTTP contract |
| [`MerchantScanReferenceLifecycleTest.php`](tests/Feature/SanctumScan/MerchantScanReferenceLifecycleTest.php) | 7 | owner scoping, actor attribution, anonymous rejection, live merchant name, inactive/soft-deleted merchant | No admin/ops paths |
| [`EconomicResourceRegistryTest.php`](tests/Feature/SanctumScan/EconomicResourceRegistryTest.php) | 9 | publish, variety requirement, QR rotation, archival revocation, unique codes, cross-merchant denial | No public page render; no QR image test |
| [`EconomicExchangeLifecycleTest.php`](tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php) | 35 | exchange lifecycle, payment evidence, disputes, evidence privacy/review, verifier governance, revisions, reassessment, appeals, identity gate | No settlement; no concurrency; no admin role/2FA; no exhaustive transition matrix |
| [`EnvironmentTemplateSecurityTest.php`](tests/Feature/EnvironmentTemplateSecurityTest.php) | 2 | no pre-generated `APP_KEY`; UTC default | Cannot boot in this revision |
| `ExampleTest` files | 2 | framework smoke | Not meaningful coverage |

### How the tests are built (verified)

- All Sanctum Scan suites force `database.default = sqlite` and `:memory:` ([`EconomicExchangeLifecycleTest.php:41`](tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php:41)).
- They hand-create `users`, `admins`, `basic_settings`, and `merchants` tables, then manually `require` and call `up()` on selected Sanctum Scan migrations ([`EconomicExchangeLifecycleTest.php:48`](tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php:48)).
- Some HTTP tests strip production middleware with `withoutMiddleware` ([`EconomicExchangeLifecycleTest.php:149`](tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php:149)).
- SQLite does not implement `lockForUpdate` with row-level blocking, so the tests cannot prove the concurrency-safety claims in the roadmap.
- Only 4 factories exist (`User`, `UserProfile`, `Admin`, `Currency`); there are no merchant, agent, wallet, exchange, resource, dispute, or verifier factories.

### CI coverage (verified)

[`.github/workflows/sanctum-scan-php-lint.yml`](.github/workflows/sanctum-scan-php-lint.yml):

- Triggers on pushes to `sanctum-scan/**` and pull requests targeting `main`.
- Lints only changed PHP files.
- Installs dependencies and runs **one** test file: `tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php`.
- Does not run Pint, PHPStan, `composer audit`, migrations, MySQL, or the frontend build.
- Does not trigger on `feature/*` pushes, including the current branch.

---

## 3. Test design principles

1. **Test the deployed artifact, not a parallel schema.** Feature tests must run the real migrations for the tables they use. Handcrafted schemas are allowed only for pure unit tests and must not be used to claim migration or production compatibility.
2. **Test through the real middleware boundary at least once per route.** A service-level test alone cannot prove authentication, verification, PIN, 2FA, or rate limiting.
3. **Use MySQL for anything involving locks, unique-race behaviour, decimal precision, or migrations.** SQLite is a fast smoke environment, not a concurrency oracle.
4. **One capability, one acceptance test.** Every milestone needs an end-to-end test that exercises the HTTP surface and asserts the audit history.
5. **Assert negative outcomes.** Invalid state, unauthorized actor, duplicate submission, expired offer, tampered evidence, and merchant leakage are first-class test cases.
6. **No network, no real personal data, no production dumps.** Fake storage, queue, notifications, and HTTP clients. Use synthetic evidence fixtures.
7. **Freeze time** for expiry, review timestamps, and revision ordering (`Carbon::setTestNow`).
8. **Record the executed command and summary** in the PR or milestone note.

---

## 4. Test layers

### L0 — Static and deterministic checks

- Conflict-marker scan.
- `php -l` over all tracked PHP files.
- `composer validate --strict`.
- Pint `--test`.
- PHPStan/Larastan (recommended; not currently installed) at least on `app/Services/SanctumScan`, Sanctum Scan controllers, and routes.
- Route name uniqueness and public-route allowlist.
- Migration `up()`/`down()` smoke on MySQL.

These are cheap and catch the current boot failure class before any test runs.

### L1 — Domain/unit tests

Target: services and state machines, with a real database connection but no HTTP.

Required suites:

- `ScanReferenceIssuer` / `ScanReferenceResolver` lifecycle and hash-only storage.
- `ExchangeSettlementManager` exhaustive transition matrix.
- `ExchangeDisputeManager` transition matrix and actor rules.
- `UserIdentityCheckManager` projection and gate semantics.
- `IndependentVerificationManager` eligibility, conflict blocks, revision chain, reassessment links, terminal withdrawal.
- `DisputeEvidenceManager` MIME allowlist, size, digest binding, duplicate digest, tamper detection.

### L2 — Feature/HTTP tests

Target: routes, middleware, validation, participant scoping, response shape.

At least one test per state-changing route, including:

- Unauthenticated request is rejected.
- Wrong participant is rejected (IDOR).
- Wrong state returns the documented error code.
- Duplicate submission is idempotent or rejected.
- Merchant response does not contain buyer identity state.
- Admin route requires role and 2FA (or the test explicitly documents the test-only bypass).

### L3 — Contract/integration tests

- Payment provider callbacks: signature verification, replay, amount/currency binding.
- QR gateway order lifecycle and merchant return flow.
- File download authorization and digest verification.
- Queue jobs and notifications.
- External providers are faked; a small number of sandbox integration tests can be manual/release-gated.

### L4 — Concurrency and property-based tests

Run on MySQL 8 with separate processes or connections.

- Two concurrent buyer acceptances of the same offer: exactly one succeeds.
- Two concurrent payment-evidence submissions: exactly one row.
- Two concurrent attestation corrections: version sequence has no duplicates and exactly one succeeds or both receive a deterministic conflict error.
- Concurrent identity review and acceptance: serialized by settings→user lock order.
- Concurrent mandate grants: no duplicate active mandates (requires a uniqueness constraint or serialized grant).
- Legacy wallet transfer: balance conservation and no negative balance under concurrent requests (currently untested and likely unsafe).

### L5 — Security regression suite

- Admin RBAC for named and unnamed routes.
- Broadcast/push token issuance cannot use a foreign user ID.
- Public cache-clear endpoints are absent or protected.
- Installer reset is unavailable after installation.
- Unauthenticated webhooks either verify signatures or cannot mutate balances/state.
- Public file download cannot access private evidence or another tenant’s file.
- CORS and session cookie settings match the deployment model.

---

## 5. State transitions that warrant exhaustive/property-based testing

| Transition domain | Invariants to test |
|---|---|
| Exchange | Only the documented edges are allowed; terminal states never transition; every accept/dispatch/receipt writes exactly one event; expiry is monotonic; a cancelled/expired offer cannot be accepted; status is never written outside the service. |
| Settlement | No skipped states; `settled` requires both payment verification and receipt conditions; no transition moves funds; system transitions are limited to `awaiting_payment -> payment_evidence_submitted`; cancellation is valid from every non-terminal state. |
| Dispute | One dispute per exchange; proposer ≠ acceptor; rejection returns to open; withdrawal only by initiator before response; terminal states cannot reopen. |
| Identity review | Latest row determines the projection; non-verified clears it; verified sets method/timestamp; history is immutable; merchant never sees it. |
| Attestation revisions | Version strictly increases; one parent per revision; revoked is terminal; evidence digest stays bound; reassessment references a prior version and a different member. |
| Membership/mandate | Invitation must be accepted by an active verified user; suspension/revocation invalidates authority; no duplicate active mandate for the same evidence/institution/scope. |
| Scan reference | Raw token is never stored; hash lookup only; expiry/revocation/archival are enforced; unknown/malformed references are indistinguishable publicly. |
| Legacy wallet (future) | Sum of debits/credits is conserved; no negative balance; exactly-once effect per idempotency key; failure rolls back all rows. |

### Tooling

- Use PHPUnit data providers for exhaustive transition matrices now.
- Add a property-based library (e.g. Eris) only if input-space generation adds value beyond data providers. Do not add a new test framework merely for style.
- For concurrency, prefer a dedicated MySQL integration job with real parallel processes over trying to simulate locks in SQLite.

---

## 6. Required test infrastructure

1. **Migration-backed test database**
   - `RefreshDatabase` for feature tests.
   - A MySQL 8 service in CI with the same charset/collation as production.
   - A migration smoke test that runs all migrations from scratch and, where practical, rolls back.

2. **Factories**
   - Add `MerchantFactory`, `AgentFactory`, `UserWalletFactory`, `MerchantWalletFactory`, `AgentWalletFactory`, `SanctumScanResourceFactory`, `SanctumScanExchangeFactory`, `SanctumScanDisputeFactory`, and evidence/verifier factories.
   - Keep factories minimal; do not depend on the large production seeders.

3. **Fakes and fixtures**
   - `Storage::fake('local')` and `Storage::fake('public')`.
   - `Queue::fake`, `Notification::fake`, `Http::fake`, `Mail::fake`.
   - Small synthetic PDF/JPEG/PNG fixtures with known digests.

4. **Time control**
   - Freeze time per test; advance it explicitly for expiry and revision sequencing.

5. **Route inventory snapshot**
   - Assert no duplicate route names.
   - Assert the set of unauthenticated routes against an explicit allowlist.
   - Assert required middleware on Sanctum Scan routes.

6. **Boot smoke test**
   - A test that resolves the HTTP kernel and performs a trivial request. This would have failed immediately on the current parse error.

---

## 7. CI design

Recommended workflow shape:

```yaml
on:
  push:
    branches: ['**']
  pull_request:
    branches: ['**']

jobs:
  static:
    # conflict markers, php -l all files, composer validate, pint --test
  unit:
    # phpunit tests/Unit
  feature-sqlite:
    # phpunit tests/Feature with RefreshDatabase
  feature-mysql:
    services:
      mysql: { image: 'mysql:8', env: { MYSQL_DATABASE: testing, ... } }
    # migrate:fresh + phpunit
  security:
    # route allowlist, authorization matrix, webhook signature tests, composer audit
  frontend:
    # npm ci && npm run build (requires a committed lockfile)
```

Rules:

- No `continue-on-error` for required jobs.
- Cache Composer/npm dependencies, not `vendor/`.
- Pin the PHP version used in production; test the minimum supported PHP (8.2) and the CI version (8.3) until the support policy is updated.
- Upload PHPUnit output and coverage artifacts.

---

## 8. Coverage targets

Do not optimize for a single global percentage. Use capability-level gates:

| Area | Required evidence |
|---|---|
| Sanctum Scan state-changing services | Exhaustive transition matrix + failure cases |
| Sanctum Scan HTTP routes | At least one authorized success and one unauthorized/IDOR failure per route |
| Evidence handling | MIME/size/digest/tamper/duplicate tests |
| Verifier governance | Eligibility, conflict, credential expiry, revision/reassessment chain |
| Settlement | Full transition matrix, authorization, idempotency, no-funds assertion |
| Legacy money paths | Characterization tests before refactoring; concurrency tests before claiming safety |
| Admin security | Role/2FA matrix, including unnamed-route behaviour |
| Webhooks | Signature/replay/amount-binding tests |

---

## 9. QODOR-002 test plan (settlement slice)

Add:

1. `tests/Unit/SanctumScan/ExchangeSettlementStateMachineTest.php`
   - Data-provider matrix for every `(current, target)` pair.
   - Assert system-only transitions.
   - Assert cancellation from every non-terminal state.
   - Assert invalid targets return a typed error, not a string.

2. `tests/Feature/SanctumScan/SettlementLifecycleTest.php`
   - Payment evidence submission advances `awaiting_payment -> payment_evidence_submitted` exactly once.
   - Admin verify/reject/settle endpoints enforce admin auth, role, and 2FA.
   - Buyer/merchant cannot drive verification or settlement.
   - Settlement status is visible to participants only as a status, never as proof of provider settlement.
   - No wallet/transaction row is created or modified (assert table counts/balances before and after).

3. `tests/Feature/SanctumScan/SettlementMigrationTest.php`
   - Migration applies on MySQL; columns/defaults/index exist.
   - Existing exchanges backfill to `awaiting_payment`.

4. `tests/Feature/SanctumScan/SettlementAuthorizationTest.php`
   - IDOR across exchanges.
   - Merchant cannot settle.
   - Unauthenticated request rejected.
   - Duplicate settle request is idempotent or rejected deterministically.

5. Extend the boot smoke test and route inventory test to include the new admin route names.

---

## 10. Exit criteria for the testing milestone

- The suite boots and runs at HEAD.
- CI runs on every branch/PR and executes all tests.
- Feature tests use real migrations; MySQL job passes.
- Settlement has an exhaustive matrix and an HTTP lifecycle test.
- Route names are unique and public routes are allowlisted.
- Static analysis and Pint pass.
- `composer audit` is clean or the remaining advisory is documented and accepted.
- No test claims concurrency safety on SQLite.
