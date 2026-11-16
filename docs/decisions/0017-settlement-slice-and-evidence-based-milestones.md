# ADR 0017 — Settlement Slice and Evidence-Based Milestone Status

**Status:** Proposed
**Date:** 2026-11-16

## Context

Sanctum Scan is the only part of the application with a clean module boundary:
the services under `app/Services/SanctumScan` do not import wallet or legacy
transaction models. That makes it the right place to establish a complete,
verifiable vertical slice before any further feature work.

Current state, measured on `feature/qrpay-update` (`4ddb392`):

- M012–M014 are absent on this branch even though milestone documentation
  references them.
- M016 (settlement) exists only partially:
  - `database/migrations/2026_11_14_110000_add_settlement_status_to_sanctum_scan_exchanges_table.php`
    adds `settlement_status` and `settlement_status_updated_at`.
  - `app/Services/SanctumScan/ExchangeSettlementManager.php` defines statuses
    and transitions but **has no callers in the repository**.
  - `SanctumScanExchange` has no settlement casts.
  - `onPaymentEvidenceSubmitted()` is never invoked from
    `SanctumScanExchangeController::submitPaymentReference()`, so submitting
    payment evidence does not move the settlement status.
- The orphaned manager has three defects that would become real if it were
  wired in:
  1. `transition()` accepts **any** target status when `$actorGuard === 'system'`,
     with no transition-table check and no authorization separation.
  2. `cancel()` calls `transition(..., 'cancelled', ...)`, but
     `awaiting_payment` has no `cancelled` entry in `ADMIN_TRANSITIONS`, so the
     call silently returns the string `invalid_settlement_transition` and the
     caller cannot distinguish success from failure.
  3. `payment_rejected → payment_evidence_submitted` is declared as a
     "resubmission path", but the payment-evidence table is one row per exchange
     (`hasOne`; existence check at
     `SanctumScanExchangeController.php:274`), so that transition would claim
     evidence was submitted when no new evidence exists.
- Documentation has drifted from code: features and tests are described as
  present that are not on this branch, and ADR 0012 specifies a 422 status for
  the identity gate while the implementation returns 403
  (`SanctumScanExchangeController.php:119-126`).

This ADR fixes two things: the scope of the next milestone and the rule for
when a milestone may be called done.

## Decision

### 1. QODOR-002 is one vertical slice: the settlement state machine, without money movement

QODOR-002 completes M016 end to end:

- an explicit, single-source transition table;
- a service that enforces it under a row lock;
- the buyer evidence path wired into it inside the existing transaction;
- administrator endpoints to verify, reject, and settle;
- participant-visible read projection;
- exhaustive unit, HTTP, migration, and concurrency tests;
- documentation matching the code.

It does **not** move money, create ledger entries, call payment gateways, or
touch wallets. `settled` is a recorded assertion by an authenticated
administrator, not a financial transfer. This keeps the slice complete and
reviewable while leaving the money boundary to a later, separate decision.

### 2. The transition table is the only authority

The authoritative matrix for this slice:

| From | Allowed to | Actor |
| --- | --- | --- |
| `awaiting_payment` | `payment_evidence_submitted` | buyer, via evidence submission (system records it) |
| `awaiting_payment` | `cancelled` | buyer/merchant cancellation path or exchange expiry, only while no verified payment exists |
| `payment_evidence_submitted` | `payment_verified` | authenticated administrator |
| `payment_evidence_submitted` | `payment_rejected` | authenticated administrator |
| `payment_evidence_submitted` | `cancelled` | exchange cancellation/dispute path, only while no verified payment exists |
| `payment_verified` | `settled` | authenticated administrator |
| `payment_rejected` | terminal | — |
| `settled` | terminal | — |
| `cancelled` | terminal | — |

Rules that follow:

- No actor, including `system`, may write a status outside the table. The
  `system` actor is restricted to transitions explicitly marked as
  system-recordable; it does not bypass the matrix.
- Actor authorization is checked separately from state legality. A legal
  transition requested by an unauthorised actor is rejected, not silently
  ignored.
- Rejected transitions return a typed failure; they never return a status
  string that can be confused with a model.
- `payment_rejected` is terminal **because evidence versioning does not exist**.
  Allowing resubmission would require a new evidence row per attempt, a
  supersession link, and a rule for which evidence the administrator reviewed.
  That is a separate ADR. Until then, a rejected exchange is not resubmitted;
  it is closed.
- `payment_verified` may not transition to `cancelled` in this slice. A
  verified payment that later fails belongs to dispute/refund handling, which
  is out of scope and must not be simulated by a status write.
- Every accepted transition appends exactly one immutable event through
  `ExchangeEventRecorder`, inside the same database transaction.
- The exchange's `status` and the settlement status must remain consistent:
  settlement may not be `settled` unless the exchange was `accepted`,
  `dispatched`, or `received`, and existing rows whose exchange is
  `declined`, `cancelled`, or `expired` are backfilled to settlement
  `cancelled` (a data migration, not a schema change).

### 3. Milestones have evidence-based status

Milestone documentation and feature maps use exactly these statuses:

- **Planned** — agreed, not started.
- **In progress** — code exists on a branch, not merged.
- **Implemented** — merged, complete code path, no verification claim.
- **Verified** — merged, and the named automated tests pass in CI plus any
  required manual evidence is recorded with date and operator.
- **Retired** — intentionally removed; superseding record linked.

A document may not describe a feature as present unless it is at least
`Implemented` on the branch being documented, and may not say “passing” or
“complete” unless the evidence exists in CI at a recorded commit. Documents
must cite the test class/method or the command that establishes the claim.

### 4. Documentation is part of the slice

QODOR-002 is not complete until:

- `FEATURE_MAP.md`'s M016 row matches the implemented behaviour;
- the M016 state machine section matches the transition table above;
- ADR 0012's status-code contract is reconciled with the code (either change
  the code to 422 or amend ADR 0012 to 403 with rationale — one source of
  truth, explicitly chosen);
- the duplicate identity-check controllers and duplicate route names are
  resolved or recorded as an accepted, tracked limitation;
- the QODOR-002 plan in `docs/engineering/` is marked complete with links to
  the pull request and test evidence.

## Consequences and limitations

- The slice records settlement truth but does not settle funds. Operators must
  not treat `settled` as proof of payment until a later milestone adds
  financial reconciliation. This limitation must appear in the admin UI copy
  and the API documentation.
- Making `payment_rejected` terminal is a deliberate narrowing of the orphaned
  manager's intent. It removes a false affordance rather than preserving an
  unimplementable transition.
- Existing rows receive a backfilled settlement status; the backfill must be
  reversible and tested.
- Because the manager currently has no callers, wiring it in changes buyer
  behaviour for the first time. The buyer evidence endpoint must remain
  backwards compatible in its response shape except for the added settlement
  fields.
- No conclusion here authorises changes to legacy wallet, payment, or
  transaction code.

## Verification

- The exhaustive transition-matrix test enumerates every
  `(status, target, actor)` combination and asserts exactly the matrix above.
- HTTP tests cover buyer submission, administrator verification, rejection,
  settlement, invalid-state conflicts, unauthorised access, and participant
  scoping.
- A MySQL test exercises two concurrent administrator transitions and asserts a
  single winner and a single event.
- The migration test proves `up`, `down`, and the backfill on MySQL.
- CI runs the gates defined in ADR 0016 for the pull request.
