# ADR 0016 — Deterministic Verification Gates

**Status:** Accepted (2026-11-16)
**Date:** 2026-11-16

## Context

The repository currently depends on human review to catch failures that a
machine can detect without judgement. Measured evidence from the QODOR-001
baseline investigation:

- A committed merge conflict in
  `app/Models/Admin/BasicSettings.php` (lines 37–45) makes every Artisan
  command and every test fail with `ParseError`. The application cannot boot.
- `phpunit.xml` defines Unit and Feature suites, but the GitHub Actions
  workflow runs a single test file (`EconomicExchangeLifecycleTest`) only.
- The CI workflow is triggered by pushes that touch `sanctum-scan/**` and by
  pull requests to `main` only. Work on the current feature branch is not
  covered.
- There is no static analysis in CI. PHP syntax errors are therefore only
  caught when a test or Artisan command happens to load the file.
- Two admin controllers (`SanctumScanUserIdentityController` and
  `SanctumScanUserIdentityReviewController`) are both routed under the
  `sanctum.identity-checks.` route-name prefix
  (`routes/admin.php:100-107` and `routes/admin.php:167-174`). Duplicate route
  names break URL generation and are not detected by any current check.
- `package.json` is tracked but `package-lock.json` is not. Composer lock and
  vendor state have drifted (`composer install --dry-run` reports differences),
  so a local green run is not reproducible from the committed lock file.
- `composer audit --locked --no-dev` reports an advisory for
  `phpseclib/phpseclib` and an abandoned `vonage/nexmo-bridge`; nothing fails
  the build on this.

These are mechanical facts. They do not need review judgement, and leaving them
to review has already allowed a non-booting branch to be committed.

## Decision

Adopt a set of required, automated gates. Each gate is fail-closed: non-zero
exit blocks merge. All gates run on every push to every branch and on every
pull request regardless of target branch.

### G1 — Conflict-marker gate

A repository script (`tools/check-conflict-markers.php`) scans every tracked
text file and fails when it finds `<<<<<<<` or `>>>>>>>` markers, or a
`=======` line that is bounded by those markers. It must not flag legitimate
Markdown setext underlines (`=======` outside a conflict block).

Rationale: a conflict marker is never intentional content. This single gate
would have prevented the current boot failure.

### G2 — PHP syntax gate

Every tracked `*.php` file — including `resources/installer/**`, `config/**`,
`routes/**`, `database/**`, and the helper files listed in
`composer.json`'s `autoload.files` — is checked with `php -l` using the
project's supported PHP version (8.2). A parse error fails the build.

This is deliberately broader than "lint the app directory": the boot blocker
was in a model loaded indirectly, and helper files are autoloaded on every
request.

Implementation note 2026-11-16: `*.blade.php` templates are excluded from
`php -l` because they are not standalone PHP; Laravel compiles them. The gate
currently covers 951 non-Blade files.

### G3 — Full test suite gate

`php artisan config:clear && ./vendor/bin/phpunit` (or `php artisan test`) runs
the **entire** Unit and Feature suite on every push. No test-file allowlist.

- The SQLite in-memory configuration in `phpunit.xml` remains the default for
  speed.
- A separate MySQL job (G4) covers database-specific behaviour.
- The gate fails on any error or failure. "Risky" and deprecation output is
  reported but does not fail until the baseline is clean; the counts are
  recorded in the job summary so they cannot silently grow.

### G4 — Migration and schema gate

Against a MySQL 8 service container:

1. `php artisan migrate:fresh --force`
2. run the Feature suite with the MySQL connection
3. `php artisan migrate:rollback --step=1 --force` for the newest migration,
   then `php artisan migrate --force`

This catches schema drift, missing indexes, invalid `down()` methods, reserved
words, and MySQL-only constraint failures that SQLite does not reproduce. It
also removes the current practice of tests handcrafting SQLite schemas instead
of exercising migrations; new tests must migrate, not fabricate, the schema.

### G5 — Route registry gate

`php artisan route:list --json` must succeed, and a script asserts:

- no two routes share the same **name**;
- every route action resolves to an existing class and method;
- every `POST`/`PUT`/`PATCH` route has at least one middleware in its chain
  other than the global stack, unless it appears in a reviewed public-route
  allowlist.

The last rule does not decide whether a route is secure; it forces the
author to declare that a route is intentionally public. The allowlist is
reviewed like code.

Adopted allowlists (amendment 2026-11-16), all in
`tools/route-registry-allowlist.php` and all shrink-only — the test fails when
an entry no longer matches a violation:

- **duplicate names** — the 14 legacy names tracked as R-26;
- **public write routes** — 25 existing middleware-free POST routes (global
  lookups, provider callbacks, installer steps, standalone payment pages);
- **unresolvable actions** — 4 dead routes whose controller method is missing
  (R-29).

`php artisan route:list --json` queries `virtual_card_apis` during boot, so the
smoke command runs in the migrated MySQL job; the global assertions run inside
the normal test suite, which boots without a seeded database.

### G6 — Static analysis gate (ratcheting)

Add Larastan/PHPStan at a low level (5) with a committed baseline. Initially
scope it to the newer bounded code (`app/Services/SanctumScan`,
`app/Http/Controllers/Admin`, `app/Http/Controllers/Api/User`,
`app/Models/SanctumScan*`) to keep the first pull request reviewable. The
baseline may only shrink: a CI step fails if the ignored-error count increases.
Legacy directories enter scope only after their baseline is zero, as described
in ADR 0001's incremental-adoption principle.

**Deferred 2026-11-16:** PHPStan/Larastan is not in `composer.json` or the lock
file. Adding it is a dependency and lock change that is not part of M017, so no
gate is claimed here. The ratchet is tracked as an extension of R-10 and must
be added to `composer check` rather than as a separate bypassable step.

### G7 — Dependency and reproducibility gate

- `composer validate --strict --no-check-publish`
- `composer audit --locked` fails on any security advisory affecting production
  dependencies, unless the advisory is listed in
  `tools/check-dependency-audit.php` with a reviewed rationale. The allowlist
  is shrink-only: an entry that is no longer reported fails the gate.
- The lock file must be committed and `composer install --dry-run` must report
  nothing to install, update, or remove. This detects the current vendor/lock
  drift.
- Abandoned-package findings (currently `vonage/nexmo-bridge`) are reported as
  warnings with a tracked removal task; they do not fail the build because the
  package is not currently exploitable by that fact alone.

**Adopted exception 2026-11-16:** `PKSA-h7cz-q7zq-mdkt` (phpseclib 4.0.1
PBKDF CPU-exhaustion) is allowlisted. `laravel/passport v13.11.0` pins
phpseclib exactly to 4.0.1, so no fixed version is installable without replacing
Passport. QRPay does not parse attacker-supplied encrypted keys, PFX, or CMS
payloads. Tracked as R-28 with a review trigger.

### G8 — Formatting gate

`./vendor/bin/pint --test` runs on every changed PHP file. Formatting is not a
substitute for correctness; it is included so review does not spend attention
on whitespace and so diffs stay small.

Changing a legacy file therefore reformats that file. M017 applied this to
eight files, including `routes/api/merchant_api.php`; the churn is deterministic
and limited to files the change already touches.

### Enforcement

- All gates are required status checks on the protected branch.
- A failing gate is fixed, not bypassed. Bypass requires an explicit,
  recorded decision in the pull request and a follow-up task.
- Gates run through a single documented entry point (`composer check`, which
  calls `tools/verify.sh`) so a developer can reproduce CI locally with one
  command. The MySQL job remains separate because it needs a disposable
  database service.
- The workflow must not depend on untracked local state (`.env`, `vendor`
  contents, node modules) beyond what CI installs from the lock files.

## Consequences and limitations

- The first pull request after adoption will be red until the boot blocker is
  fixed and the full suite is made to pass. That is the intended exposure: the
  branch is already non-functional, and the gate makes that visible instead of
  latent.
- G3 will initially be slow because the suite currently errors at bootstrap;
  once the blocker is fixed, the measured runtime should be recorded and the
  gate optimised if it exceeds a tolerable duration.
- Full PHPStan coverage of the legacy application is explicitly out of scope;
  the ratchet is scoped, and expanding it is a separate decision.
- G5's route-action resolution can only prove that the class and method exist,
  not that the route is authorised. Authorisation still requires tests.
- No gate here replaces the MySQL concurrency tests required for wallet and
  settlement invariants (see `TESTING_STRATEGY.md`).
- This ADR does not itself fix the conflict markers, the CI workflow, or the
  lock drift. It defines the gates that QODOR-002 must turn on.

## Verification

Adoption is complete when:

1. a deliberately committed conflict marker fails CI (demonstrated on a
   throwaway branch and reverted);
2. removing a route's middleware, without adding it to the public allowlist,
   fails CI;
3. duplicating a route name fails CI;
4. a clean clone can run the single documented command and reproduce the CI
   result;
5. the current boot blocker is fixed and the full suite result is recorded.

Local status 2026-11-16: item 5 is satisfied. Items 1–3 were demonstrated
locally with reverted probes: appending markers to `README.md` failed G1;
appending a parse error to a tracked test failed G2; removing an allowlist entry
for `POST global/get-states` failed the middleware assertion; removing
`user.qr.scan` from the duplicate allowlist failed the uniqueness assertion.
Item 4 is `composer check` (green locally: 94 tests, 1,017 assertions, 1
MySQL-only skip). CI execution of the workflow itself remains unverified until
the branch is pushed; the MySQL job is the highest-risk unexecuted step.

## Implementation status (2026-11-16)

| Gate | Local status | CI status |
|---|---|---|
| G1 conflict markers | `tools/check-conflict-markers.php`: 1,927 files clean; fail-closed probe executed | Wired in `gates` job |
| G2 PHP syntax | 950 non-Blade files clean; fail-closed probe executed | Wired in `gates` job |
| G3 full suite | 94 tests, 1,017 assertions, 1 MySQL-only skip, green | Wired in `gates` job, full suite |
| G4 MySQL migration/concurrency | Concurrency test written and skipped on SQLite; no local MySQL available | Wired in `mysql` job (unexecuted) |
| G5 route registry | Global uniqueness, action resolution, middleware assertions with shrink-only allowlists; `route:list --json` smoke in MySQL job; fail-closed probes executed | Wired |
| G6 PHPStan ratchet | PHPStan not installed | Deferred by recorded decision; no gate claimed |
| G7 composer gates | `validate --strict` green; audit allowlist implemented and green | Wired in `gates` job |
| G8 Pint | Passes on all 30 changed PHP files | Wired in `gates` job |

Boot-blocker verification item 5 is satisfied; items 1–4 are locally
demonstrated, with CI execution pending under R-02.
