# Roadmap Status — QRPay / Sanctum Scan

**Date:** 2026-11-17
**Branch:** `main` with stacked, unmerged work through M024-A (PRs #25–#30)
**Method:** roadmap phases from [`README.md`](../README.md#13-development-roadmap) mapped to repository evidence per ADR 0017. A capability is only **Verified** when an executed test or command proves it; roadmap checkboxes are not evidence.

**Last recorded local baseline (before M020–M024):** `composer check` → 96 tests, 1,049 assertions, 1 skipped (MySQL-only concurrency test), green on SQLite; G1, G2, G7, G8 green. Pint passed on all 32 PHP files in that earlier revision. The MySQL job's full step sequence was green locally on MySQL 9.4 (migrations, route:list 1,347 routes, backfill down/up, concurrency test). This historical baseline does not validate M020–M024 or M024-A.

Status vocabulary (ADR 0017): **Planned** · **In progress** · **Implemented** · **Verified** · **Retired**.

**Hard boundary:** M002–M020 Sanctum Scan services do not move funds and do not read or write legacy wallet/transaction tables. M020 only binds immutable review events to evidence versions. No roadmap item below changes that financial boundary.

---

## Phase I — Protocol Foundations

| Roadmap item | Status | Evidence / gap |
|---|---|---|
| Sanctum Scan architecture | Verified | [`ARCHITECTURE.md`](../ARCHITECTURE.md) §9 |
| Economic object model | Verified | M002–M011 tables, constraints, and append-only event records; module tests |
| QR schema and payload specification | Verified | `ScanReferenceResolutionTest`, `MerchantScanReferenceLifecycleTest` |
| Identity references | Implemented | M015 checks and optional gate; ADR 0012 now records the implemented 403 contract |
| Transaction intents | Verified | offer/accept/decline lifecycle in `EconomicExchangeLifecycleTest` |
| API contracts | Verified | HTTP tests for buyer/merchant/admin Sanctum routes |
| Verification and signature requirements | In progress | M008–M014 provide independent verification, participant-scoped provenance export, offline SHA-256 manifest verification and verifier-consistency checks (ADRs 0020–0022); cryptographic signing and trusted timestamps remain absent. M012 snapshot consistency under concurrent writes remains a separate M026 follow-up. |
| Repository conventions | Implemented | [`ENGINEERING_STANDARDS.md`](../ENGINEERING_STANDARDS.md); ADR 0016 gates wired in M017; CI execution and PHPStan ratchet pending |
| Initial technical documentation | Implemented | README plus architecture/feature/testing/ADR set; known drift tracked in R-13 |

## Phase II — Working Reference Platform

| Roadmap item | Status | Evidence / gap |
|---|---|---|
| User onboarding | Implemented (legacy) | Existing registration/KYC/PIN/2FA flow; not re-audited in this slice and partially untested (R-10) |
| Merchant onboarding | Implemented (legacy) | Existing merchant panel and API; not re-audited |
| Identity references | Implemented | M015 manual review + optional acceptance gate |
| QR generation | Verified | Merchant issuance/rotation/revocation tests |
| QR scanning | Verified | Public resolution tests and scan page |
| Transaction intent resolution | Verified | Resource registry + exchange offer resolution |
| Order and invoice records | In progress | Exchange agreement snapshots exist; no invoice document or numbering |
| Payment integration framework | Implemented (legacy) | Legacy gateways/wallets are outside the Sanctum boundary and carry open P1 risks (R-08/R-09) |
| Transaction history | Verified | Append-only per-exchange event timeline |
| Digital receipts | In progress | Receipt confirmation records a buyer declaration, not a receipt document |
| Audit records | Verified | Append-only exchange/dispute/verifier/settlement events |
| Administrative interface | In progress | JSON admin APIs for settlement, evidence, and identity; production middleware stack verified by `AdminOperatorMiddlewareTest`; no verified admin UI flow |

## Phase III — Real-World Exchange Pilot

| Roadmap item | Status | Evidence / gap |
|---|---|---|
| Select initial economic participants | Planned | No code |
| Map an agricultural or merchant value chain | Planned | No code |
| Test onboarding | Planned | Depends on CI/MySQL gates (R-02, R-10) |
| Complete a verified exchange | In progress | Offer → acceptance → dispatch → receipt → unverified evidence → admin verify/settle is implemented and tested, including rejection-and-resubmission via versioned evidence; no funds or provider confirmation move |
| Integrate an authorized payment provider | Planned | Deliberately outside M016; later milestone must not simulate provider settlement with a status write |
| Test reconciliation | In progress | Manual admin verification with typed conflicts and versioned evidence history; no provider statement ingestion |
| Evaluate dispute handling | Implemented | Bilateral disputes, private evidence review, independent attestations, appeals, reassessments |
| Measure participant benefits | Planned | No instrumentation |
| Document findings | Planned | — |

## Phase IV — Cooperative and Economic Infrastructure

Planned. No cooperative, group-finance, shared-asset, procurement, offline-sync, merchant-intelligence, or economic-graph code exists in the Sanctum module. The resource registry and append-only events are reusable foundations, not implementations of this phase.

## Phase V — Regenerative Value Exchange

Planned. No impact metadata, project registry, outcome verification, or productive-asset representation exists.

## Phase VI — Global Economic Interoperability

Planned. No cross-border, multicurrency settlement, or international identity interoperability beyond a single `currency` field on offers.

---

## Recommended next milestones

1. ~~**M017 — Activate deterministic gates (Phase 0 completion).**~~ Implemented 2026-11-16: [M017 record](M017-verification-gates.md). Remaining follow-ups: first GitHub run, branch protection, PHPStan ratchet (deferred), npm lockfile (not claimed).
2. ~~**M018 — Operator-surface hardening.**~~ Implemented 2026-11-16: [M018 record](M018-operator-surface-hardening.md). Production-middleware tests, controller removal (R-12/R-17), and ADR 0012 reconciliation are complete.
3. ~~**M019 — Settlement evidence versioning.**~~ Implemented 2026-11-16: [M019 record](M019-evidence-versioning.md), [ADR 0018](../decisions/0018-payment-evidence-versioning.md). Rejection is now a resting state reopened only by a buyer's new evidence version.

**M020 — Evidence-version-bound settlement decisions** is in progress:
[implementation record](M020-evidence-decision-binding.md), [ADR 0019](../decisions/0019-evidence-version-bound-settlement-decisions.md).
The event now records the exact evidence row, version and deterministic
fingerprint for verification, rejection and settlement decisions.

**M021 — Canonical provenance ADR reconciliation** is in progress on
`sanctum-scan/m021-canonical-provenance-adrs`: [engineering record](M021-canonical-provenance-adrs.md).
Canonical ADRs 0020–0022 now describe M012–M014, while the old paths remain as
compatibility pointers. ADRs 0012/0013 remain unchanged and 0014 remains
unassigned.

**M022 — Genuine settlement concurrency testing** is in progress on
`sanctum-scan/m022-genuine-settlement-concurrency`:
[M022 engineering record](M022-genuine-settlement-concurrency.md). Two separate
PHP workers now contend for the same MySQL exchange-row lock while requesting
opposite administrator decisions. The test requires one committed winner, one
`stale_expected_status` loser and one matching immutable event. The G4 workflow
now explicitly runs the Sanctum Scan feature suite before its dedicated
concurrency test. CI execution remains pending.

**M023 — Fail-safe payment-evidence rollback guard** is in progress on
`sanctum-scan/m023-fail-safe-evidence-rollback`:
[M023 engineering record](M023-fail-safe-evidence-rollback.md). The versioning
migration now has an explicit preflight refusal before schema changes whenever
non-initial or superseding evidence exists. Regression tests assert that the
history and schema remain intact after refusal.

**M024 — Permission-controlled operator reconciliation workspace** is in progress on
`sanctum-scan/m024-operator-reconciliation-workspace` (PR #29):
[M024 engineering record](M024-operator-reconciliation-workspace.md). The first
slice is read-only: it joins exchange state, evidence versions, settlement
decisions and dispute review history under the existing admin role/2FA stack.

**M024-A — Reconciliation security and projection tests** is in progress on
`sanctum-scan/m024-a-reconciliation-security-tests` (PR #30):
[M024-A engineering record](M024-A-reconciliation-security-tests.md). Feature
tests cover active queue cases, filters, pagination bounds, consolidated detail,
private-storage omission, and route-specific role authorization. They are
authored but not executed.

**M024-A1 — Restore executable validation** is blocked on owner-side GitHub
Actions investigation. PR #30 run [44081555371](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/actions/runs/44081555371)
failed again on rerun attempt 2 with both jobs reporting `runner_id: 0` and
zero steps. This is a pre-runner failure, not a PHP/test result. Existing
[incident issue #21](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/issues/21)
already records the cross-repository evidence and owner-side Settings/Support
actions. Do not add more diagnostic workflows or keep retrying until those
checks are completed.

**Next implementation milestone after validation is restored: M025 — Pilot
participant and value-chain scope.** It defines the pilot's participant/value-chain
scope and outcome measures. M026 tracks a consistent-database-snapshot
guarantee for M012 exports, which currently compose provenance context and
evidence from separate reads.

The M017 operational gate remains open: GitHub Actions must execute the gates
successfully and branch protection must require them (R-02). Local success is
not a substitute for CI. Do not mark any Phase III item Verified until the
required GitHub checks and pilot evidence exist. Provider integration and
wallet movement remain out of scope until a separate decision defines the
financial reconciliation and no-double-counting boundary.
