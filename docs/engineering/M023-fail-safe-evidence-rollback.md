# M023 — Fail-Safe Payment Evidence Rollback Guard

**Status:** In progress  
**Date:** 2026-11-17  
**Depends on:** M019 evidence versioning, ADR 0018

## Goal

Prevent a schema rollback from removing the versioning columns and composite uniqueness contract after the payment-evidence table has stored a history that the original one-row-per-exchange schema cannot represent.

The invariant is strict: preserving payment evidence is more important than completing a downgrade. A downgrade must stop before making any schema change when it would collapse, orphan or otherwise erase version history.

## Delivered in this branch

- Adds an explicit preflight check at the start of the versioning migration's `down()` method.
- Refuses rollback if any row has `version != 1` or a non-null `supersedes_id`.
- Throws a descriptive `RuntimeException` before dropping the self-reference foreign key, recreating the single-column unique key, dropping the composite index or removing version columns.
- Extends migration tests to cover:
  - safe rollback while only initial version-1 evidence exists, proving that the evidence row itself remains present;
  - refusal with a valid two-version chain, proving both rows, version fields, composite index and self-reference foreign key remain intact;
  - refusal of a non-initial version even when its predecessor is missing, guarding against malformed/imported data as well as ordinary resubmissions.

## Rollback contract

A downgrade is allowed only if every persisted evidence row is an initial version (`version = 1`) and has no predecessor link (`supersedes_id IS NULL`). This is the exact data shape representable by the original `unique(exchange_id)` schema.

The guard is a preflight safety check, not a data-repair mechanism. Operators must not delete, rewrite, merge or manually renumber evidence to force a downgrade. Keep the versioning migration applied and roll back unrelated application changes without removing the evidence-history schema.

Run schema migrations with application writes quiesced, as required for safe schema changes generally. This check does not serialize concurrent evidence submissions against the migration process.

## Acceptance criteria

- [ ] A safe downgrade with no version history restores `unique(exchange_id)` and removes the versioning columns while preserving existing version-1 evidence.
- [ ] Any non-initial version or predecessor link refuses downgrade before schema changes begin.
- [ ] A refused downgrade leaves all evidence rows and values unchanged.
- [ ] A refused downgrade leaves the composite unique index and self-reference foreign key intact.
- [ ] No production API, state machine, provider integration, wallet or ledger behavior changes.
- [ ] PHP syntax, focused migration tests, full Sanctum Scan feature tests and the standard repository verification gates pass before status advances to Verified.

## Validation

The changes are prepared on the M023 branch. Runtime migration tests have not yet been executed in this environment. The hosted-runner allocation issue remains tracked in [repository issue #21](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/issues/21); a workflow failure without a runner and executed steps is not evidence of a test failure or pass.
