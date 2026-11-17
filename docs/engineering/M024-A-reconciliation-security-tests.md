# M024-A — Reconciliation Security and Projection Tests

**Status:** Test code authored; runtime validation blocked  
**Date:** 2026-11-17  
**Depends on:** M024 PR #29  
**Pull request:** [#30 — M024-A: Test reconciliation security and projections](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/pull/30)

## Goal

Establish feature-level regression coverage for the read-only reconciliation workspace without weakening or bypassing the production admin middleware stack.

## Coverage authored

The M024-A changes to `tests/Feature/SanctumScan/AdminOperatorMiddlewareTest.php` add coverage for:

- Queue inclusion of payment-attention cases and open disputes, and exclusion of settled exchanges with resolved disputes.
- Settlement/dispute filters, pagination navigation, and per-page clamping to the supported range of 1–100.
- Case detail projection of payment-evidence versions, settlement decision events, dispute messages, evidence metadata and linked review outcome.
- Omission of private evidence storage disk/path and raw file-byte fields from the JSON projection.
- Denial of queue and detail access when a non-super-admin has no explicit permission for the corresponding route.

The existing middleware test fixture now creates dispute, message, evidence and review tables and uses unique merchant/buyer identities where multiple exchanges are created in one test.

## Authorization contract

The current `admin.role.guard` implementation evaluates the named route against role permissions. A non-super-admin without the matching permission is denied with HTTP 404 (not 403); the tests assert the actual middleware contract. The controller's active-admin check remains a separate 403 guard.

The tests do not disable `auth:admin`, `admin.role.guard`, or `admin.google.two.factor`.

## Validation status

No PHP lint, PHPUnit, Pint, route:list or runtime database test has executed for this PR.

PR workflow run [44081555371](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/actions/runs/44081555371) reached completed/failure on both jobs. Both job records have `runner_id: 0` and empty step arrays. A rerun (attempt 2) reproduced the same pre-runner failure. This is not evidence of a PHP syntax error or a failing test assertion.

The repository-wide owner-side incident is tracked in [issue #21](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/issues/21). The incident record already documents cross-repository failures, diagnostic runner-label attempts, and the required owner checks:
1. Inspect repository Settings → Actions → General for disabled-state banners or restrictions.
2. Inspect account-level Actions restrictions and billing/usage.
3. If settings are normal or a GitHub-controlled disabled state is shown, raise a GitHub Support ticket with the affected run/job IDs and workflow YAML.

Do not add another diagnostic workflow, change runner labels again, or repeatedly rerun CI before completing those owner-side checks.

## Acceptance gates

- [ ] PHP lint passes for the changed test file.
- [ ] Focused `AdminOperatorMiddlewareTest` passes.
- [ ] Full Sanctum Scan feature suite passes.
- [ ] G4 MySQL migration, route-registry and concurrency gates pass on MySQL.
- [ ] GitHub Actions executes the required jobs on allocated runners.
- [ ] No permission bypass or change to payment/funds behaviour is introduced.

## Non-goals

No production controller, route, database schema, payment provider, wallet, ledger or funds-movement code is changed by M024-A. No claim of verified workspace security is made until the tests execute successfully.
