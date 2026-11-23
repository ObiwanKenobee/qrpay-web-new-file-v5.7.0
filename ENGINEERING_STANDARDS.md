# Engineering Standards — QRPay / Sanctum Scan

**Status:** QODOR-001 baseline  
**Date:** 2026-11-15  
**Applies to:** all changes in this repository, with stricter rules for `app/Services/SanctumScan`, the Sanctum Scan APIs, migrations, and any money-moving path.

This document distinguishes:

- **Rules** — must be followed.
- **Current state** — what the repository actually enforces today.
- **Recommendation** — a proposed control that is not yet implemented.

---

## 1. Non-negotiable domain invariants

These derive from the accepted ADRs and the existing module design. They must not be weakened without a new ADR.

1. **A scan reference is not authorization, money, or settlement evidence.** It resolves to a presentation document only.
2. **Sanctum Scan does not move funds.** No Sanctum Scan service or controller may write to `transactions`, `user_wallets`, `merchant_wallets`, `agent_wallets`, or `payment_order_requests`.
3. **History is append-only.** Corrections, withdrawals, reviews, and decisions create new records. Existing evidence, attestations, reviews, and event rows are never updated or deleted.
4. **Every consequential decision has an authenticated actor and a timestamp.** System actions use an explicit system actor and are labelled as such.
5. **Participant scoping is mandatory.** A buyer or merchant may read or mutate only records tied to its own account/exchange. Admin access requires admin authentication, role permission, and 2FA.
6. **Merchant-facing responses must not reveal buyer identity-review state.**
7. **Private evidence stays private.** Dispute files use the private disk, application-generated names, participant-scoped reads, and digest verification. No public URL is issued.
8. **State transitions are atomic and concurrency-safe.** Use `DB::transaction` and row locks with a documented lock order. Do not rely on a read-then-write check outside a lock.
9. **Money-moving work requires a separate financial-invariant review.** Sanctum Scan milestones must not add wallet movement as a side effect.
10. **UTC storage, localized presentation.** Use `now()`/immutable datetime casts; localize at the boundary.
11. **No promises the system cannot keep.** Documentation must not describe a review, attestation, or receipt as proof it does not provide.
12. **No secrets or unnecessary personal data in QR payloads, logs, or public documents.** QR payloads contain opaque references only.

---

## 2. Current enforcement baseline

| Control | Current state (2026-11-16) | Evidence |
|---|---|---|
| PHP syntax check | Every tracked non-Blade PHP file | `tools/check-php-syntax.php` (G2) |
| Conflict-marker rejection | Enforced | `tools/check-conflict-markers.php` (G1) |
| Full test suite | Every push and PR, no file allowlist | `.github/workflows/sanctum-scan-php-lint.yml` (G3) |
| Tests on active feature branches | Enforced for all branches | Workflow triggers `push` and `pull_request` with no filters |
| Database migration test | Settlement migration up/down on SQLite; MySQL job wired | `SettlementBackfillMigrationTest`, workflow `mysql` job |
| MySQL compatibility test | Job wired, first run pending | `migrate:fresh`, `route:list`, concurrency test |
| Pint | Enforced on changed files | `tools/verify.sh` (G8) |
| Static analysis | **Absent** — deferred, no gate claimed | No PHPStan/Larastan dependency or config (R-10) |
| Dependency audit | Enforced with a reviewed advisory allowlist | `tools/check-dependency-audit.php` (G7, R-28) |
| Route name uniqueness | Enforced globally; allowlist empty since R-26 resolved | `RouteRegistryTest` (G5) |
| Route/middleware inventory | Enforced for state-changing routes | `RouteRegistryTest` + `tools/route-registry-allowlist.php` |
| Frontend build reproducibility | **Absent** — no lockfile, no build gate | `package.json` tracked without `package-lock.json` (R-15) |
| Deployment/rollback docs | AWS EC2 runbook tracked; no release automation | [`docs/operations/aws-ec2-deployment.md`](docs/operations/aws-ec2-deployment.md) (R-24) |

---

## 3. Mandatory checks for every change

### 3.1 Local pre-commit (recommended hook)

```bash
#!/usr/bin/env bash
set -euo pipefail

# 1. Reject merge-conflict markers anywhere in tracking-relevant source/docs.
if git diff --cached --name-only -z | xargs -0 grep -nE '^(<<<<<<<|=======|>>>>>>>)' 2>/dev/null; then
  echo "Merge-conflict markers staged. Resolve before committing."
  exit 1
fi

# 2. Lint every staged PHP file.
git diff --cached --name-only --diff-filter=ACMR -- '*.php' |
  while IFS= read -r f; do php -l "$f" >/dev/null || exit 1; done

# 3. Validate Composer metadata and lock consistency.
composer validate --strict --no-check-publish

# 4. Block accidental environment/secret files.
if git diff --cached --name-only | grep -E '(^|/)\.env($|\.)|auth\.json|\.pem$|\.key$'; then
  echo "Refusing to commit environment or key material."
  exit 1
fi
```

The conflict-marker check is the single highest-value control: it would have prevented the current boot failure.

### 3.2 Pre-push / CI

The single command that mirrors CI:

```bash
composer check              # G1, G2, G3, G7, G8
SKIP_TESTS=1 composer check # fast gates while iterating
```

`tools/verify.sh` documents each step. The MySQL job (`migrate:fresh`,
`route:list --json`, backfill down/up, concurrency test) cannot run locally
without a disposable MySQL instance; CI owns it.

Do not add a gate that `composer check` cannot reproduce. The frontend build is
deliberately not a gate until a lockfile exists.

### 3.3 Required CI jobs (implemented)

| Job | Purpose | Failure policy |
|---|---|---|
| `gates` | G1 markers, G2 syntax, G3 full suite, G7 composer/audit, G8 Pint on changed files | Block merge |
| `mysql` | G4 `migrate:fresh`, backfill down/up, concurrency; G5 `route:list --json` smoke | Block merge |

Triggers are **all branches and all pull requests**, with no path filters.
Branch protection must mark both jobs required; enabling that is a repository
setting, not a code change.

### 3.4 Test execution honesty

- A test is not “passing” unless it was executed in the reported revision and environment.
- A CI job that fails before running application code is an infrastructure failure, not a pass.
- Do not report a milestone as validated when the suite cannot boot.
- Record the exact command and the summary line (tests/assertions/errors) in the PR or milestone note.

---

## 4. Recurring agent mistakes and deterministic enforcement

| Observed mistake | Evidence | Deterministic control |
|---|---|---|
| Committing unresolved merge-conflict markers | `BasicSettings.php:37-45` contains `<<<<<<<`, `=======`, `>>>>>>>` | Pre-commit + CI conflict-marker scan; `php -l` all PHP |
| Declaring a capability “implemented” when only docs/schema exist | M012–M014 absent; M016 orphan service | Capability manifest + `capability:verify`; require a passing feature test for each claimed milestone |
| Leaving a service or migration unwired | `ExchangeSettlementManager` has no caller; settlement columns unread | Route/service binding test; feature test that exercises the HTTP surface; dead-code/static analysis |
| Duplicating an implementation instead of reconciling | Two identity controllers; duplicate `sanctum.identity-checks.*` route names | Route-name uniqueness test; one owner per capability in the manifest |
| Writing tests that bypass the real schema and middleware | Handcrafted SQLite tables; `withoutMiddleware` in tests | `RefreshDatabase`/migration-backed tests; at least one HTTP test per state-changing route with production middleware |
| Changing `composer.lock` unintentionally in a feature commit | Branch diff includes a 778-line lockfile change | Dedicated dependency commit; review lock diff; `composer validate --strict` |
| Ignoring CI branch filters | `feature/qrpay-update` pushes are not validated | CI trigger on all branches; branch protection on the active branch |
| Allowing docs to drift from code | Roadmap/ADR status and numbering inconsistencies | Link each ADR/API doc to routes/tests; run link and manifest checks in CI |
| Mixing several milestones in one commit | Latest commit touches identity, settlement, docs, routes, views, and a model | One capability per PR; commit review checklist |
| Treating SQLite as proof of concurrency safety | `lockForUpdate` is a no-op in SQLite tests | MySQL concurrency tests for any locked path |
| Reporting a test pass without evidence | The current suite errors at boot | PR template requires the executed command and summary |

---

## 5. Code and design standards

### 5.1 Layering

- Controllers: validation, actor resolution, response shaping. No multi-step domain logic.
- Services: transactions, locks, state preconditions, append-only writes, invariant enforcement.
- Models: persistence and relations. New domain models should use explicit `$fillable` or a documented reason for `$guarded`; `$guarded = ['id']` is acceptable only for legacy compatibility, not as a security boundary.
- Events: prefer explicit event records written by services over framework events for audit history. If framework events/listeners are introduced, document ordering and failure semantics.

### 5.2 Transactions and locking

- Wrap every multi-row state change in `DB::transaction`.
- Lock rows with `lockForUpdate()` before checking and mutating state.
- Document lock order in a service comment when more than one table is locked.
- Do not open nested transactions that can commit independently. Use one outer transaction per business action.
- Re-read and verify state inside the lock; do not trust a model instance loaded before the transaction.
- Add a MySQL concurrency test for any new locked path.

### 5.3 State machines

- Define allowed transitions in one place (a constant map or dedicated class), not scattered `if` statements.
- Reject unknown target states and invalid current states explicitly.
- Make terminal states explicit and irreversible.
- Distinguish system transitions from actor-driven transitions; a `'system'` actor must not bypass validation.
- Add an exhaustive transition-matrix test: every `(current, target)` pair is allowed or denied as specified.

### 5.4 API contracts

- Use explicit validation; return a stable error shape (`error.code`, `error.message`).
- Scope lookups by the authenticated actor in the query, not after loading.
- Use `whereNumber` for numeric route parameters.
- Throttle public and mutation endpoints.
- Do not expose internal storage paths, raw identity data, reviewer identities to participants, or buyer identity state to merchants.
- Document each endpoint in `docs/api/` and link it from the capability manifest.

### 5.5 Routes and middleware

- Every state-changing route must have explicit authentication, verification, and (where applicable) PIN/2FA middleware.
- Avoid `withoutMiddleware(...)`; when required for provider callbacks, document the compensating verification and add a signature test.
- Route names must be unique. Add a test that asserts the route name set has no duplicates.
- Public routes must be listed in an explicit allowlist and covered by a test.
- Do not derive security-relevant behavior (such as admin prefix) from a database row that may be missing. Provide a safe, deterministic default and log the fallback.

### 5.6 Data and privacy

- Store timestamps in UTC.
- Use immutable datetime casts for historical fields.
- Keep private evidence on the private disk; never expose a public URL.
- Do not store raw government identifiers, document images, biometrics, or unnecessary personal data in identity-review tables.
- Log identifiers, not secrets or personal payloads.
- Use digest binding (e.g. SHA-256) when referencing files whose integrity matters.

### 5.7 Security

The platform-wide policy is [SECURITY.md](SECURITY.md); this section lists the
implementation-level rules that support it.

- Verify webhook signatures where the provider supports them; if it does not, require an authenticated reconciliation path instead of trusting the payload.
- Do not expose cache/config/route clearing endpoints publicly.
- Do not issue broadcast/push tokens from caller-supplied user IDs without authentication.
- Remove or protect install/reset routes after installation.
- Use `APP_DEBUG=false` in non-local environments; keep `.env.example` free of usable secrets.
- Review dependency advisories and abandoned packages before release.

### 5.8 Documentation

- Every architectural decision gets an ADR with status, context, decision, consequences, and links to code/tests.
- Do not mark an ADR “Accepted” or a milestone “Implemented” until the corresponding code and tests exist and have executed.
- Keep the roadmap’s milestone-to-ADR mapping accurate.
- Do not reuse a missing ADR number; reserve it explicitly until the owning branch is reconciled.

---

## 6. Review checklist

A reviewer should be able to answer “yes” to all of the following:

- [ ] The change belongs to one capability and one milestone.
- [ ] No conflict markers, debug statements, secrets, or generated artifacts are committed.
- [ ] PHP lint passes for all changed files; Pint passes.
- [ ] The full test suite was executed and the summary is included.
- [ ] New state changes have an allowed-transition test and a participant-scoping test.
- [ ] New money paths have a financial-invariant review and a concurrency test.
- [ ] No Sanctum Scan code imports wallet/transaction/payment-order models.
- [ ] New routes are named uniquely, authenticated as intended, and documented.
- [ ] Migrations run on MySQL, including `down()` where practical.
- [ ] ADR/API/roadmap updates match the code’s actual status.
- [ ] Dependency changes are isolated and audited.
- [ ] Rollback is possible without rewriting history.

---

## 7. Definition of Done

A capability is done only when:

1. Its decision is recorded in an ADR.
2. Its schema and migration are applied and tested on MySQL.
3. Its domain rules live in a service with explicit preconditions and transactions.
4. Its HTTP surface has authorization, validation, participant scoping, and throttling tests.
5. Its state machine has an exhaustive transition test.
6. Its failure modes are tested (invalid state, duplicate submission, unauthorized actor, tampered evidence).
7. It does not move funds unless the milestone explicitly permits it and a financial review is recorded.
8. Its docs and roadmap status match reality.
9. The full CI suite passes on the exact commit.
10. A second engineer can run the capability from the documented commands.

---

## 8. Agent-specific operating rules

1. **Never “fix” a branch by discarding unfamiliar changes.** Inspect, report, and resolve deliberately.
2. **Never claim a test passed unless it ran.** Include the command and summary.
3. **Never mark a doc complete while code is broken.** A green document over a red build is a failure.
4. **Prefer one complete vertical slice** over many disconnected partial features.
5. **Stop at reconnaissance boundaries.** Do not rewrite functional code during investigation; record the exact remediation.
6. **Preserve the stack.** Do not introduce a new framework, database, queue, or service without a measured problem and an ADR.
7. **Use the repository’s existing patterns** (services, migrations, ADRs, API docs) before inventing new ones.
8. **Treat unresolved merge markers, duplicate route names, and orphaned services as release blockers.**
