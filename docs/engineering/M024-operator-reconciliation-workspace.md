# M024 — Permission-Controlled Operator Reconciliation Workspace

**Status:** In progress  
**Date:** 2026-11-17  
**Depends on:** M016 settlement state machine, M018 operator-surface hardening, M019 evidence versioning, M020 evidence-bound decisions

## Goal

Give authorised operators one read-only case projection that connects exchange state, payment-evidence versions, settlement decisions, dispute statements, dispute evidence and evidence-review outcomes.

## Delivered in this branch

- Adds a paginated reconciliation queue for exchanges awaiting payment evidence/settlement attention or carrying an open/proposed dispute.
- Adds a consolidated exchange case detail endpoint with payment evidence history, settlement decision timeline, dispute messages, submitted dispute evidence and linked evidence-review outcomes.
- Places both endpoints inside the existing admin authentication, application-mode, admin-role and two-factor middleware group.
- Keeps the workspace read-only. Existing settlement transition and evidence-review endpoints remain the only decision write paths.
- Does not expose storage disk/path or raw uploaded file bytes in the case projection.

## Authority and privacy boundaries

- The projection is a convenience view, not a new source of truth.
- Evidence submissions, participant statements, administrator reviews and settlement decisions remain distinct records.
- Admin actor IDs and timestamps are retained in the projection; the authenticated admin session remains the authority for writes.
- Dispute statements, captions, references and review summaries may contain personal or payment-related information. Treat the workspace as private dispute data and restrict route permissions accordingly.
- No payment provider, wallet/ledger, settlement transfer or funds movement is introduced.

## Acceptance criteria

- [ ] Queue returns only active reconciliation cases and paginates with a bounded page size.
- [ ] Detail joins the immutable evidence and decision timeline without mutating source records.
- [ ] Admin auth, role guard and 2FA middleware are exercised by feature tests.
- [ ] Non-authorised admin cannot read the queue or case detail.
- [ ] Demo-mode and read-only behaviour remain intact.
- [ ] PHP lint, Sanctum Scan suite and production middleware tests pass.

## Validation

Runtime tests and PHP lint have not yet been executed. GitHub Actions runner allocation remains blocked as tracked in [issue #21](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/issues/21).
