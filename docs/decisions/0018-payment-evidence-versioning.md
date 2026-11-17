# ADR 0018 — Payment Evidence Versioning and Reopened Verification

**Status:** Implemented
**Date:** 2026-11-16
**Depends on:** ADR 0005 (exchange evidence), ADR 0017 (settlement matrix)
**Supersedes:** the terminality of `payment_rejected` in ADR 0017 §2

## Context

ADR 0017 made `payment_rejected` terminal because the payment-evidence table
held exactly one row per exchange (`exchange_id` was unique) and the model
forbade updates. That was correct for a one-shot submission, but it blocks
normal reconciliation: a buyer who was rejected for a mistyped reference could
never supply the corrected reference, and the exchange was permanently closed
in the settlement record.

The roadmap's Phase III requires realistic reconciliation, so rejection must be
a resting state from which the buyer can append a corrected version — without
overwriting the rejected evidence or the administrator's decision.

## Decision

### 1. Evidence is append-only and versioned

`sanctum_scan_exchange_payment_evidence` gains:

| Column | Meaning |
|---|---|
| `version` | unsigned integer, starts at 1, increments per exchange |
| `supersedes_id` | nullable self-reference to the previous version |

The unique key on `exchange_id` is replaced by `unique(exchange_id, version)`
with an explicit short index name (`scan_payment_evidence_exchange_version_unique`)
so the identifier stays within MySQL's 64-character limit.

Rows remain immutable through the model (`updating`/`deleting` throw). A
correction is a new row, never an update.

`SanctumScanExchange::paymentEvidence()` returns the highest version
(`latestOfMany('version')`); `paymentEvidences()` returns the full ordered
history.

### 2. Only the buyer may reopen a rejection

The transition table adds exactly one entry:

| From | Allowed to | Actor |
| --- | --- | --- |
| `payment_rejected` | `payment_evidence_submitted` | buyer (`api`) only |

`payment_rejected` is removed from `SettlementStateMachine::TERMINAL`.
`settled` and `cancelled` remain terminal. Administrators cannot reopen a
rejection by writing a status; they must wait for the buyer's new evidence and
then verify version *n*.

### 3. Submission rules

- First submission requires settlement `awaiting_payment` and no existing
  evidence.
- A resubmission requires settlement `payment_rejected` **and** an existing
  latest version. The new row's `supersedes_id` points at that latest version.
- Any other settlement state with existing evidence returns
  `payment_reference_exists` (409).
- The version is computed under the existing exchange row lock; the composite
  unique index is the database backstop against duplicate versions.
- The event type is `payment_reference_submitted` for version 1 and
  `payment_reference_resubmitted` afterwards. Event data carries `version` and
  `supersedes_id`.

### 4. Visibility

- Buyer and merchant projections include `payment_evidence.version` and keep
  returning the latest version only.
- The admin settlement projection returns the latest `payment_evidence` plus
  `payment_evidence_history`, an ordered list of every version with
  `version`, `supersedes_id`, reference, amount, currency, and submission time.
- Settlement events remain the authority for what was accepted or rejected;
  `verification_status` on an evidence row stays `unverified` because the row is
  an assertion, not a verification.

## Consequences and limitations

- History is preserved: a rejection and the superseded reference remain
  queryable after resubmission.
- The administrator verifies the latest version, not necessarily the version
  that was rejected. `payment_evidence_history` makes the sequence explicit but
  does not itself bind a decision to a version; the settlement events record
  each decision and its timestamp.
- The `down()` migration first checks that every stored row is still an
  initial version (`version = 1`) with no predecessor link. Any non-initial or
  superseding row causes an explicit `RuntimeException` before the migration
  changes a foreign key, index or column. The original one-row constraint is
  therefore restored only while the existing data is representable by that
  schema. A refused rollback leaves version history and the versioning schema
  intact. Application writes must be quiesced during schema migration; the
  preflight is not a lock against concurrent submissions.
- A rejected settlement on an exchange that later closes stays `payment_rejected`.
  `cancelForClosedExchange` does not add a rejected → cancelled edge, so closing
  the exchange does not rewrite the rejection.
- No provider statement ingestion, automatic matching, or money movement is
  introduced. Verification remains an authenticated administrator's recorded
  assertion.

## Verification

- `SettlementStateMachineTest` includes the rejected → submitted edge in the
  144-combination matrix and asserts only `api` may use it.
- `SettlementLifecycleTest::test_admin_rejection_allows_a_versioned_resubmission_and_reopened_verification`
  covers: rejection, direct-verification refusal, version-2 submission,
  preserved version-1 row, supersession link, admin history projection,
  resubmission event, and verification of version 2.
- `PaymentEvidenceVersioningMigrationTest` asserts the composite unique key,
  the removal of the single-column unique, successful rollback with preserved
  version-1 evidence, and fail-closed rollback with unchanged multi-version
  history, indexes and self-reference foreign key.
