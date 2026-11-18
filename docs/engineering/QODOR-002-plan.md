# QODOR-002 — Settlement State Machine Vertical Slice

**Status:** Implemented (2026-11-16); CI/MySQL gates pending
**Date:** 2026-11-16
**Depends on:** ADR 0016 (gates), ADR 0017 (scope and status rules)
**Base branch:** `feature/qrpay-update` @ `4ddb392`
**Target:** one reviewable pull request after the P0 branch repair, or two
stacked pull requests as described in Phase 0.

> **Implementation evidence (2026-11-17):** `composer check` → 96 tests / 1,049
> assertions / 1 MySQL-only skip; Pint passes on 32 changed PHP files; `php -l`
> clean on 966 tracked non-Blade PHP files; Sanctum route names unique and
> settlement actions resolve. ADR 0016 gates are wired (M017) and the MySQL job
> sequence is green locally on MySQL 9.4 (migrations, route:list, backfill
> down/up, concurrency). The production operator middleware stack is tested
> (M018), ADR 0012 records the implemented 403 contract, the superseded identity
> controller is deleted, and evidence is versioned so rejection can be reopened
> (M019, ADR 0018). Remaining: first GitHub CI run and branch protection (R-02).

## Goal

Complete M016 end to end: every settlement status change in Sanctum Scan goes
through one explicit transition table, is authorised by actor, is recorded as an
immutable event in the same transaction, and is visible to the exchange
participants. No money moves.

## Definition of done

1. The application boots and the full test suite runs (P0 prerequisite).
2. The transition table is the only place a settlement status can change, and
   no actor bypasses it.
3. Buyer payment-evidence submission moves `awaiting_payment` →
   `payment_evidence_submitted` atomically with the evidence row.
4. An authenticated administrator can verify, reject, or settle; unauthorised
   actors and illegal transitions are rejected with typed, documented errors.
5. Exchange cancellation/decline/expiry paths keep settlement status
   consistent for exchanges with no verified payment.
6. Buyer and merchant read models expose settlement state; the public scan
   response does not.
7. Exhaustive unit tests, HTTP tests, a MySQL migration test, and a MySQL
   concurrency test pass in CI.
8. Documentation matches the code, including the rejection behaviour and the
   403/422 identity-gate contract. *(Amendment 2026-11-16: M019/ADR 0018 made
   `payment_rejected` a resting state with versioned resubmission, so it is no
   longer terminal.)*

## Phase 0 — P0 branch repair and gates (do this first, separately)

The settlement work cannot be tested on a branch whose application does not
boot. This phase is deliberately its own pull request so the settlement diff is
not mixed with a repository-wide repair.

### 0.1 Fix the boot blocker

File: `app/Models/Admin/BasicSettings.php` (lines 37–45).

The committed merge conflict must be resolved by keeping the `HEAD` side —
which is a formatting-only difference from the incoming side — and removing all
marker lines. Do not change the property list or casts. After the edit:

```bash
php -l app/Models/Admin/BasicSettings.php
php artisan --version
```

Both must succeed. This is the only functional-code change in Phase 0.

### 0.2 Establish the real baseline

```bash
./vendor/bin/phpunit
./vendor/bin/pint --test app/Services/SanctumScan
composer audit --locked --no-dev
```

Record, in the pull request body: test counts, failures, risky count, Pint
findings, and audit findings. Do not "fix" unrelated legacy tests in this pull
request; if the full suite cannot pass without unrelated work, record the exact
failing list and fix only failures caused by the conflict-marker repair.

### 0.3 Turn on the ADR 0016 gates

- `tools/check-conflict-markers.php` and its CI step (G1)
- PHP syntax check over all tracked PHP files (G2)
- full suite on every push and every PR target (G3)
- MySQL migration job (G4)
- route registry script: unique names, resolvable actions, declared public
  routes (G5)
- Pint `--test` on changed files (G8)
- `composer validate --strict`, `composer audit --locked`, lock-drift check (G7)

Defer G6 (PHPStan/Larastan) to a follow-up only if its baseline cannot be made
green in this phase; record the deferral in the PR and keep the task open.

### 0.4 Reconcile repository state

- Commit `package-lock.json` or remove the npm build claim from the runbook.
  Choose one; do not leave `package.json` without a lock file.
- Resolve the duplicate admin identity-check routes (two controllers, same
  `sanctum.identity-checks.` name prefix). Either delete the superseded
  `SanctumScanUserIdentityController` or rename its route prefix; do not leave
  duplicate names once G5 is enabled.
- Decide and document the identity-gate status code (403 in code/API docs vs
  422 in ADR 0012). Amend ADR 0012 or change the code; one source of truth.

Phase 0 is complete when CI is green on the repaired branch and a clean clone
reproduces that result with the single documented command.

## Phase 1 — State machine core

### 1.1 `app/Services/SanctumScan/SettlementStateMachine.php` (new)

Pure PHP, no database access. Contents:

```php
final class SettlementStateMachine
{
    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const EVIDENCE_SUBMITTED = 'payment_evidence_submitted';
    public const VERIFIED = 'payment_verified';
    public const REJECTED = 'payment_rejected';
    public const SETTLED = 'settled';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [...];       // the six above
    public const TERMINAL = [REJECTED, SETTLED, CANCELLED];

    // from => to => allowed actor guards
    private const TRANSITIONS = [
        AWAITING_PAYMENT => [
            EVIDENCE_SUBMITTED => ['api', 'system'],
            CANCELLED          => ['api', 'merchant_api', 'system'],
        ],
        EVIDENCE_SUBMITTED => [
            VERIFIED  => ['admin'],
            REJECTED  => ['admin'],
            CANCELLED => ['api', 'merchant_api', 'system'],
        ],
        VERIFIED => [
            SETTLED => ['admin'],
        ],
    ];

    public static function canTransition(string $from, string $to, string $guard): bool;
    public static function isStatus(string $status): bool;
    public static function assertKnownStatus(string $status): void;
}
```

Rules:

- `canTransition` returns false for unknown statuses, unknown guards, terminal
  sources, and same-status "transitions".
- `admin` is the only guard allowed to verify, reject, or settle. `system` is
  not a superuser; it appears only where an automated code path legitimately
  records a transition (evidence submission, cancellation).
- `api` cancellation is used by buyer decline/withdraw/expiry paths;
  `merchant_api` cancellation by the merchant withdraw path.

### 1.2 `app/Services/SanctumScan/SettlementTransitionResult.php` (new)

Readonly result object replacing the current `SanctumScanExchange|string`
return type:

```php
final class SettlementTransitionResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?SanctumScanExchange $exchange,
        public readonly ?string $error,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {}

    public static function success(SanctumScanExchange $e, string $from, string $to): self;
    public static function failure(string $error, ?string $from = null, ?string $to = null): self;
}
```

Error codes (documented and stable): `invalid_settlement_transition`,
`unknown_settlement_status`, `invalid_actor`, `already_in_state`,
`stale_expected_status`.

### 1.3 Rewrite `app/Services/SanctumScan/ExchangeSettlementManager.php`

Keep the class name to minimise churn; replace internals.

- Remove `ADMIN_TRANSITIONS` and the `$actorGuard === 'system'` bypass.
- `transition(SanctumScanExchange $exchange, string $to, string $actorGuard,
  int $actorId, string $reason, ?string $expectedFrom = null): SettlementTransitionResult`
  - opens `DB::transaction`;
  - re-reads with `lockForUpdate()`;
  - optional `expectedFrom` mismatch returns `stale_expected_status`;
  - validates with `SettlementStateMachine::canTransition`;
  - on success writes status + `settlement_status_updated_at`, appends exactly
    one `settlement_status_changed` event with `from`, `to`, `reason`,
    actor guard/id;
  - returns success with the fresh model.
- `recordEvidenceSubmitted(SanctumScanExchange $lockedExchange, int $buyerId):
  SettlementTransitionResult` — for use **inside an existing locked
  transaction**; performs no nested transaction and no second lock.
- `cancelForClosedExchange(SanctumScanExchange $lockedExchange, string $guard,
  int $actorId, string $exchangeStatus): void` — maps a closed exchange
  (`declined`, `cancelled`, `expired`) to settlement `cancelled`, but only when
  settlement is `awaiting_payment` or `payment_evidence_submitted`. If it is
  `payment_verified` or `settled`, do nothing and let the caller keep the
  settlement state; this case must be covered by a test even though current
  exchange paths cannot reach it.
- All writes remain inside the caller's or method's transaction.

## Phase 2 — Persistence

### 2.1 Model casts

`app/Models/SanctumScanExchange.php`: add

```php
'settlement_status' => 'string',
'settlement_status_updated_at' => 'immutable_datetime',
```

No `$guarded` change is needed (`$guarded = ['id']` already permits both).

### 2.2 Backfill migration (new)

`database/migrations/2026_11_17_090000_backfill_settlement_status_for_closed_exchanges.php`

- `up()`: set `settlement_status = 'cancelled'` and
  `settlement_status_updated_at = now()` where exchange `status` is
  `declined`, `cancelled`, or `expired` **and** settlement status is still
  `awaiting_payment`.
- `down()`: set those rows back to `awaiting_payment` and
  `settlement_status_updated_at = null`.
- Use the query builder, chunk by id, and do not touch rows in any other
  settlement state.
- The column, default, and index already exist from
  `2026_11_14_110000_add_settlement_status_to_sanctum_scan_exchanges_table.php`;
  do not add them again.

## Phase 3 — Wiring

### 3.1 Buyer evidence submission

`app/Http/Controllers/Api/User/SanctumScanExchangeController.php`,
`submitPaymentReference()` (transaction begins at line 263):

After the evidence row and `payment_reference_submitted` event are written, call
`$settlement->recordEvidenceSubmitted($exchange, (int) $buyer->getKey())`
using the already-locked `$exchange`. If the result is not `ok`, return a 409
with the result's error code and roll back (throw inside the transaction or
return the error and ensure nothing was committed). The event and the status
change must share one transaction.

Do not move the existing amount/currency validation, evidence uniqueness check,
or state check.

### 3.2 Closed-exchange consistency

In the same transaction where each path sets the exchange to a closed status,
call `cancelForClosedExchange()` with the correct guard:

- `Api/User/SanctumScanExchangeController::accept` — merchant/resource
  withdrawal branch (`status = cancelled`).
- `Api/User/SanctumScanExchangeController::decline` — `status = declined`.
- `Api/User/SanctumScanExchangeController::expireIfNeeded` — `status = expired`.
- `Api/Merchant/SanctumScanExchangeMerchantController::cancel` — `cancelled`
  and expired branches.

The call uses the locked exchange; no extra query is required.

### 3.3 Administrator endpoints

New controller: `app/Http/Controllers/Admin/SanctumScanExchangeSettlementController.php`

- constructor injection of `ExchangeSettlementManager`
- `show(int $exchangeId)`: returns exchange summary, settlement status,
  updated timestamp, payment evidence summary, and settlement-related events;
  404 when missing.
- `transition(Request $request, int $exchangeId, ExchangeSettlementManager $m)`:
  - validate `to_status` (`in:` all six statuses),
    `reason` (`required|string|min:10|max:500`), optional
    `expected_status` (`in:` all six statuses);
  - resolve the authenticated admin from the `admin` guard; reject with 403 if
    absent or inactive;
  - call `transition(..., guard: 'admin', actorId: admin id, reason,
    expectedFrom)`;
  - map errors: illegal/unknown/already/stale → 409 with
    `error.code = <code>`; validation → 422; missing exchange → 404;
  - success → 200 with the exchange settlement projection.

Routes in `routes/admin.php`, inside the existing group at line 98 (the group
already has `web`, `auth:admin`, `app.mode`, `admin.role.guard`,
`admin.google.two.factor`):

```php
Route::controller(SanctumScanExchangeSettlementController::class)
    ->prefix('sanctum/exchanges/{exchangeId}/settlement')
    ->whereNumber('exchangeId')
    ->name('sanctum.exchanges.settlement.')
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'transition')->name('transition')->middleware('throttle:10,1');
    });
```

Verify with G5 that the names are unique and the actions resolve.

### 3.4 Participant projections

- Buyer `present()` in `Api/User/SanctumScanExchangeController`: add
  `settlement_status` and `settlement_status_updated_at`.
- Merchant presenter in `Api/Merchant/SanctumScanExchangeMerchantController`
  (`index`/`store`/`dispatch`/`cancel` responses): add the same two fields.
- Public scan (`ScanReferenceResolver` and the public scan controller): must
  **not** expose settlement state. Add an assertion test for absence.
- Admin endpoint may expose all settlement fields and the evidence summary.

## Phase 4 — Tests

### 4.1 Unit: exhaustive transition matrix

`tests/Unit/SanctumScan/SettlementStateMachineTest.php`

For every `from` in the six statuses, every `to` in the six statuses, and every
guard in `admin`, `api`, `merchant_api`, `system`, assert the boolean against
an independent expected table written in the test (not derived from the
production constant). Include:

- every terminal source rejects all targets;
- `admin` cannot cancel;
- `system` cannot verify/reject/settle;
- unknown statuses reject;
- same-status pairs reject;
- `assertKnownStatus` throws for unknown values.

### 4.2 Feature: settlement lifecycle

`tests/Feature/SanctumScan/SettlementLifecycleTest.php` (use migrations, not a
handcrafted schema; follow the existing `EconomicExchangeLifecycleTest`
fixtures for users, merchants, resources, scans, and offers)

Cover:

1. accepted exchange starts at `awaiting_payment`;
2. buyer submits payment reference → 200, status
   `payment_evidence_submitted`, exactly one new
   `payment_reference_submitted` event and one `settlement_status_changed`
   event;
3. admin verifies → `payment_verified`; event recorded;
4. admin settles → `settled`; event recorded; terminal;
5. admin rejects → `payment_rejected`; *(amended 2026-11-16: M019/ADR 0018 lets
   the buyer append version 2 and reopen verification; a resubmission while
   settlement is still `payment_evidence_submitted` remains rejected)*;
6. illegal transitions (`awaiting_payment` → `settled`, `settled` →
   anything, `payment_verified` → `cancelled`) return 409 and write no event;
7. repeating the same successful transition returns 409 `already_in_state`
   and writes no duplicate event;
8. `expected_status` mismatch returns 409 `stale_expected_status`;
9. amount/currency mismatch still fails before any settlement change;
10. exchange decline/cancel/expiry sets settlement `cancelled` and writes the
    corresponding event;
11. buyer and merchant responses include settlement fields; public scan
    response does not.

### 4.3 Feature: authorisation

- unauthenticated admin endpoint → redirect/401 per existing admin behaviour;
- authenticated non-admin (buyer API token) → 403/404, never 200;
- admin without the required role or 2FA (per existing middleware) → denied;
- admin transition is scoped to the exchange by id; no participant scoping
  bypass is possible because the route is admin-only, but assert that a
  non-existent id returns 404;
- buyer cannot transition settlement directly (no buyer endpoint accepts a
  target status);
- merchant cannot transition settlement directly.

### 4.4 Migration test

`tests/Feature/SanctumScan/SettlementBackfillMigrationTest.php`
(group `mysql` or an equivalent DB-backed suite):

- seed exchanges in `declined`, `cancelled`, `expired`, `accepted` with
  settlement `awaiting_payment`, plus one `payment_verified` closed exchange;
- run the backfill migration `up()`;
- assert closed rows become `cancelled` except the verified one, which is
  untouched;
- run `down()`; assert only backfilled rows return to `awaiting_payment`.

### 4.5 MySQL concurrency test

`tests/Feature/SanctumScan/SettlementConcurrencyTest.php` (group `mysql`):

- one exchange in `payment_evidence_submitted`;
- two competing admin transitions (`payment_verified` vs `payment_rejected`)
  issued against two separate connections inside coordinated transactions;
- assert exactly one succeeds, one returns a conflict, the final status matches
  the winner, and exactly one `settlement_status_changed` event exists.
- Mark the test skipped when the connection is not MySQL; it is required in the
  G4 job.

### 4.6 Regression

The full existing suite (58 Sanctum Scan test methods plus the legacy suite)
must pass. Record the count in the pull request. Any legacy test that fails for
reasons unrelated to this slice is fixed in Phase 0 or recorded as a known
failure with a tracking task; it may not be silently skipped.

## Phase 5 — Documentation

- `FEATURE_MAP.md`: M016 state machine matches Phase 1; move M016 to the
  correct status per ADR 0017; correct any M012–M014 claims that are false on
  this branch.
- `ARCHITECTURE.md`: add the settlement service and admin controller to the
  Sanctum Scan module map and principal flows; note that settlement records
  truth but does not move money.
- `TESTING_STRATEGY.md`: add the new suites to the L2/L3/L4 inventory and the
  concurrency-test requirement.
- ADR 0017: status → Accepted when the plan is approved; add an
  implementation-evidence note when merged.
- `docs/engineering/RISK_REGISTER.md`: update R-01 (boot blocker) to resolved
  with the commit hash; add the settlement-slice risks if not already covered.
- Operator-facing text and API docs for the admin endpoint must state that
  `settled` is an administrative record, not proof of funds received.

## Error contract (stable, to be documented and tested)

| Condition | HTTP | `error.code` |
| --- | --- | --- |
| Validation failure | 422 | `validation_failed` |
| Exchange not found | 404 | `not_found` |
| Unauthenticated | 401/redirect | existing admin behaviour |
| Authenticated but not an admin | 403 | `forbidden` |
| Unknown target status | 422 | `validation_failed` |
| Illegal state transition | 409 | `invalid_settlement_transition` |
| Same status repeated | 409 | `already_in_state` |
| `expected_status` mismatch | 409 | `stale_expected_status` |
| Wrong actor for a legal transition | 403 | `invalid_actor` |

## Acceptance checklist

- [x] Phase 0 boot repair: application boots; full suite runs. ADR 0016 gates
      are wired in M017 (`composer check` and the CI workflow); GitHub execution
      and branch protection remain under R-02.
- [x] Route registry gate enabled: `RouteRegistryTest` asserts global name
      uniqueness, action resolution, and write-route middleware with
      shrink-only allowlists; `route:list --json` runs in the MySQL CI job. The
      14 legacy duplicates were renamed (R-26 resolved 2026-11-18) and only the
      4 dead routes (R-29) remain allowlisted.
- [x] Transition matrix test is exhaustive and passes (144 combinations).
- [x] Buyer evidence submission moves settlement state atomically.
- [x] Admin verify/reject/settle works for active admins via the controller and
      writes one event per transition; the production operator middleware stack
      is verified separately by `AdminOperatorMiddlewareTest` (R-27, M018).
- [x] Closed-exchange paths keep settlement consistent.
- [x] Buyer/merchant projections include settlement; public scan does not.
- [x] MySQL backfill migration `up()`/`down()` tested — rollback and re-migrate
      green on MySQL 9.4 (2026-11-17).
- [x] MySQL concurrency test passes with one winner and one event — 1 test,
      6 assertions green on MySQL 9.4; skipped on SQLite.
- [x] Full suite green in CI at the merged commit — green locally (96 tests /
      1,049 assertions / 1 skip); MySQL job sequence green locally; first GitHub
      run pending (R-02).
- [x] Rejected evidence can be superseded by an append-only version and
      verification reopened (M019, ADR 0018).
- [x] Documentation and ADR statuses updated; no feature claimed without
      evidence.

## Out of scope

- Moving money, wallet debits/credits, ledger entries, gateway calls, escrow.
- Automatic payment verification or reconciliation against bank/mobile-money
  statements.
- ~~Evidence resubmission after rejection~~ — delivered in M019 (ADR 0018).
- Refunds, chargebacks, and dispute-driven settlement reversal.
- Exposing settlement state on the public scan endpoint.
- Legacy payment, wallet, and transaction controllers.
- Full-application PHPStan coverage.

## Rollback

- Phase 0: revert the single conflict-marker repair commit; gates remain useful
  and can stay.
- Phase 1–4: revert the pull request. The only new schema change is the
  backfill migration; run its `down()` before reverting if the deployment
  already executed it. The settlement column and index predate this slice and
  must not be dropped.
- No money, wallet, or transaction data is touched, so rollback has no
  financial reconciliation requirement.

## Risks

| Risk | Mitigation |
| --- | --- |
| Wiring settlement changes buyer evidence behaviour for the first time | HTTP tests cover the full path; response shape only gains fields |
| Nested transaction/lock mistakes cause deadlocks | `recordEvidenceSubmitted` runs inside the existing lock; admin path is the only one that opens its own transaction |
| Backfill marks a genuinely pending exchange as cancelled | Backfill only touches closed exchange statuses and only `awaiting_payment` rows |
| 403/422 contract choice breaks clients | Decide in Phase 0.4, document in the API docs, test the chosen code |
| CI stays red for unrelated legacy failures | Phase 0 records the exact baseline; unrelated fixes are separate tasks, not silent skips |
| Scope creep into money movement | Out-of-scope list is enforced in review; ADR 0017 states the boundary |
