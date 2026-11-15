# ADR 0016 — Exchange Settlement State Machine (No Fund Movement)

**Status:** In progress; hardening under PR review  
**Date:** 2026-11-15

## Context

Sanctum Scan records buyer payment references and delivery receipt as separate exchange facts. A submitted reference is not proof that money was paid, and a buyer's receipt of goods is not proof that the seller received funds. The settlement lifecycle must preserve those distinctions.

The current settlement foundation adds a separate `settlement_status` projection and immutable exchange timeline events. It does not debit or credit wallets, call a payment provider, or settle external rails.

## Decision

Use a separate settlement state machine:

`awaiting_payment → payment_evidence_submitted → payment_verified → settled`

A platform administrator may record `payment_rejected` from `payment_evidence_submitted`. Cancellation may set settlement status to `cancelled` only after the exchange itself is cancelled.

### Actor boundaries

- The system actor may only record `awaiting_payment → payment_evidence_submitted` after payment evidence submission. Its actor ID is the reserved system value zero.
- Only an authenticated administrator actor with a positive admin ID may record payment review decisions.
- A merchant or buyer actor cannot directly mutate settlement status.
- Unknown actor guards, malformed actor IDs and transitions outside the actor-specific map are rejected.

### Verification and settlement guards

- A `payment_verified` transition requires an existing immutable payment-evidence record whose amount and currency match the exchange, and an explicit platform-verified decision.
- A `settled` transition requires the exchange to be in `received` status with a recorded `received_at` timestamp, and the prior settlement state must already be `payment_verified`.
- Every accepted transition is atomic with its append-only exchange timeline event.
- Rejected transitions do not mutate the exchange or append an event.

## Non-goals

- No wallet debit, credit, transfer, escrow or refund.
- No M-Pesa, card, bank or other external payment API.
- No automatic payment verification from a reference string or screenshot.
- No change to the existing exchange delivery, dispute or payment-evidence lifecycle.
- No merchant-facing claim that settlement means an external payment rail has completed.

## Consequences and remaining work

This state machine records an internal platform decision. It is not itself proof of bank settlement or receipt of funds. Before production use, the repository still needs a fully authorised admin interface, a separately reviewed payment-verification evidence process, operational reconciliation, and successful CI execution. A rejected evidence record is immutable; replacement/resubmission requires a separately designed versioned evidence model and is intentionally not simulated by mutating the original record.
