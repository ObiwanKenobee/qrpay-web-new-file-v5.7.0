# M017 — Deterministic Verification Gates

**Status:** Implemented (CI wired, first GitHub run pending)
**Date:** 2026-11-16
**Depends on:** ADR 0016 (gates), ADR 0017 (evidence rules)
**Base branch:** `feature/qrpay-update`

## Goal

Turn ADR 0016 from a decision into executable enforcement: every push and pull
request runs the mechanical checks that would have caught the committed merge
conflict, the non-booting branch, and the stale route definitions found during
reconnaissance.

## Delivered

### Single entry point

| Command | Purpose |
|---|---|
| `composer check` | Runs G1, G2, G3, G7, G8 exactly as CI does |
| `SKIP_TESTS=1 composer check` | Fast gates while iterating |

`tools/verify.sh` computes the changed-file set from `origin/main` (or
`BASE_REF`/`BASE_SHA` in CI, including uncommitted files locally) and enforces
Pint on that set.

### Gate scripts

| Gate | Implementation |
|---|---|
| G1 conflict markers | `tools/check-conflict-markers.php` scans every tracked text file |
| G2 PHP syntax | `tools/check-php-syntax.php` runs `php -l` on every tracked non-Blade PHP file |
| G3 full suite | `vendor/bin/phpunit` with no file allowlist |
| G4 MySQL | `mysql` job: `migrate:fresh`, backfill `down`/`up`, concurrency test |
| G5 route registry | `tests/Feature/RouteRegistryTest.php` global name uniqueness, action resolution, write-route middleware; `route:list --json` smoke in the MySQL job |
| G6 PHPStan | Deferred and recorded below |
| G7 composer | `composer validate --strict`, `tools/check-dependency-audit.php` with a reviewed advisory allowlist |
| G8 Pint | `pint --test` on changed PHP files |

### Route allowlists

`tools/route-registry-allowlist.php` records three kinds of tracked debt:

- 14 legacy duplicate route names (R-26);
- 25 middleware-free state-changing routes that are intentional public surface
  (global lookups, provider callbacks, installer steps, standalone payment
  pages);
- 4 dead routes whose controller method does not exist (R-29).

Both tests fail when an allowlisted entry no longer matches a violation, so the
lists can only shrink.

## Executed evidence (local, 2026-11-16)

| Check | Result |
|---|---|
| `php tools/check-conflict-markers.php` | 1,928 text files clean |
| `php tools/check-php-syntax.php` | 951 PHP files clean, 715 Blade templates skipped |
| `composer validate --strict --no-check-publish` | valid |
| `composer audit --locked --no-dev` | one advisory, allowlisted with rationale; abandoned package warned |
| `composer check` | **87 tests, 1,001 assertions, 1 skipped (MySQL-only), green** |

These are the M017 run. After M019 the same command reports 96 tests /
1,049 assertions and 1,950/966 tracked files at G1/G2 (2026-11-17).

Fail-closed demonstrations, each reverted afterwards:

1. conflict markers appended to `README.md` → G1 exited 1 and named lines
   1344/1346/1348;
2. syntax error appended to `tests/Feature/ExampleTest.php` → G2 exited 1 and
   named the parse error;
3. `POST global/get-states` removed from the allowlist → middleware test failed
   naming that route;
4. `user.qr.scan` removed from the duplicate allowlist → uniqueness test failed
   naming that route.

## Changes required to make the gates pass

- Removing the four dead route definitions was probed and reverted: touching
  the legacy route files would force unrelated Pint churn, so the routes remain
  registered and are allowlisted until a dedicated cleanup (R-29).
- Pint reformatted eight changed files, including `routes/api/merchant_api.php`
  and `tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php`. This is
  style-only churn attributable to G8.
- `authorizenet/authorizenet` changed from an exact `2.0.3` pin to `~2.0.3` so
  `composer validate --strict` passes; the lock hash was refreshed without
  changing installed versions.

## Limitations and unverified claims

- The workflow has not executed on GitHub in this session. "Wired" is a
  repository fact; "green in CI" is not yet verified.
- The G4/G5 step sequence was later executed locally on 2026-11-17 against a
  disposable MySQL 9.4 instance: all migrations, the backfill down/up, the
  route-introspection fixture, `route:list --json` (1,347 routes), and the
  concurrency test (6 assertions) are green. Two MySQL-only migration defects
  were found and fixed in the process; see `M019-evidence-versioning.md`. The
  GitHub runner run remains the outstanding evidence (R-02).
- G6 (PHPStan/Larastan) is not installed. Adding it requires a dependency and
  lock change; the ratchet is deferred (R-10 extension). The `composer check`
  entry point is the place to add it.
- `package-lock.json` is still absent. No CI gate claims a reproducible
  frontend build; the claim is removed from the documentation rather than
  half-supported (R-15).
- The advisory allowlist is a recorded acceptance, not a fix. It must be
  revisited when `laravel/passport` permits `phpseclib >= 4.0.3` (R-28).

## Next

M018 — operator-surface hardening: production-middleware tests for admin
settlement/identity/evidence routes, removal of the superseded identity
controller, and reconciliation of ADR 0012's status-code contract.
