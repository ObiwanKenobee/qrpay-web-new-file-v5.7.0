# Roadmap Status — QRPay / Sanctum Scan

**Date:** 2026-11-17
**Branch:** `feature/qrpay-update`
**Method:** roadmap phases from [`README.md`](../README.md#13-development-roadmap) mapped to repository evidence per ADR 0017. A capability is only **Verified** when an executed test or command proves it; roadmap checkboxes are not evidence.

**Current executed baseline:** `composer check` → 96 tests, 1,049 assertions, 1 skipped (MySQL-only concurrency test), green on SQLite; G1, G2, G7, G8 green. Pint passes on all 32 changed PHP files. The MySQL job's full step sequence is green locally on MySQL 9.4 (migrations, route:list 1,347 routes, backfill down/up, concurrency test); only the GitHub runner run is pending.

Status vocabulary (ADR 0017): **Planned** · **In progress** · **Implemented** · **Verified** · **Retired**.

**Hard boundary:** M002–M016 services do not move funds and do not read or write legacy wallet/transaction tables. No roadmap item below changes that boundary.

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
| Verification and signature requirements | In progress | Independent verification and attestation revisions exist (M008–M011); provenance export/signature work (M012–M014) is absent, ADR 0014 is missing |
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

The code milestones recommended after M016 are complete. The next step is
operational: push the branch, confirm the M017 workflow and MySQL job run green,
and enable branch protection (R-02). Do not mark any Phase III item Verified
until that has happened.

After CI is verified, the next product milestone is a Phase III pilot
prerequisite: choose a participant and value chain, then define the minimal
onboarding and reconciliation evidence the pilot needs. Provider integration
remains out of scope until a separate decision defines the no-double-counting
boundary.
