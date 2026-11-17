# M018 — Operator-Surface Hardening

**Status:** Implemented
**Date:** 2026-11-16
**Depends on:** M016 (settlement API), M017 (gates), ADR 0012 (identity gate)
**Base branch:** `feature/qrpay-update`

## Goal

Prove that the admin settlement routes are protected by the same middleware
stack that runs in production, rather than by tests that disable it, and remove
the last duplicate identity implementation.

## Delivered

### Production-middleware test

`tests/Feature/SanctumScan/AdminOperatorMiddlewareTest.php` registers the real
admin route group and exercises it without `withoutMiddleware`:

| Test | Asserts |
|---|---|
| Super-admin read + transition | `GET` 200; `POST` 200; DB status `payment_verified` |
| Admin without a role | redirect; session guest afterwards (`RoleGuard`) |
| Non-super-admin without permission | 404 (`RoleGuard`) |
| Demo mode | read 200; write 422 `errors.unknown.0`; status unchanged (`AppModeGuard`) |
| Pending 2FA | redirect to `admin.authorize.google.2fa` |

The fixture builds only the tables the stack touches: `users`, `admins`,
`basic_settings`, `merchants`, the four role tables, and the Sanctum Scan
resource/exchange/event/evidence migrations. The role-less case deliberately
creates no `admin_has_roles` row; an earlier version assigned a `Reviewer` role
and therefore exercised the permission path (404) instead of the logout path.

### Duplicate identity controller removed

`app/Http/Controllers/Admin/SanctumScanUserIdentityController.php` was deleted.
Only `SanctumScanUserIdentityReviewController` is routed
([`routes/admin.php:101`](../../routes/admin.php#L101)), and a repository search
finds no remaining reference. This closes R-12 (Sanctum scope) and R-17.

### ADR 0012 reconciled

ADR 0012 specified 422 for a buyer whose identity prerequisite is unmet. The
implemented and documented contract is 403
([`app/Http/Controllers/Api/User/SanctumScanExchangeController.php:123`](../../app/Http/Controllers/Api/User/SanctumScanExchangeController.php#L123)).
The ADR now records the amendment with the rationale: the request is well
formed; the authenticated caller lacks a platform prerequisite. 422 remains
reserved for malformed data.

## Executed evidence (local, 2026-11-16)

| Check | Result |
|---|---|
| `vendor/bin/phpunit tests/Feature/SanctumScan/AdminOperatorMiddlewareTest.php` | 5 tests, 14 assertions, green |
| `vendor/bin/pint --test` on the new test | pass |
| `composer check` | **92 tests, 1,015 assertions, 1 skipped (MySQL-only), green** |
| G8 Pint | 27 changed PHP files pass |

These are the M018 run. M019 and the MySQL verification later raised the suite
to 96 tests / 1,049 assertions / 32 changed files (2026-11-17).

The PHPUnit "deprecated schema" notice comes from the pre-existing
`phpunit.xml`, not from this test; it was present before M018.

## Changes

- Deleted `app/Http/Controllers/Admin/SanctumScanUserIdentityController.php`.
- Added `tests/Feature/SanctumScan/AdminOperatorMiddlewareTest.php`.
- Amended [`docs/architecture/decisions/0012-buyer-identity-verification-prerequisite.md`](../architecture/decisions/0012-buyer-identity-verification-prerequisite.md).
- Pint reformatted `routes/api/merchant_api.php` (import ordering and function
  argument spacing) after the dead-route revert restored the unformatted body.
  This is style-only; route definitions are unchanged.

## Limitations and unverified claims

- The tests exercise the middleware code paths, not a browser session. The
  Google 2FA redirect is asserted from the middleware, not from a completed
  challenge.
- Identity, evidence, and dispute admin routes share the same middleware stack
  but are not individually enumerated; the settlement show/transition routes
  are the representative case.
- The MySQL job's step sequence runs green locally on MySQL 9.4 as of
  2026-11-17; the GitHub run is still pending (M017 limitation, R-02).

## Next

M019 — settlement evidence versioning: let a rejected evidence record be
superseded by an append-only new version and reopen verification, without
rewriting history.
