# ADR 0006: Bilateral dispute and resolution records

- **Status:** Proposed implementation
- **Date:** 2026-11-13
- **Scope:** Sanctum Scan exchange lifecycle

## Context

Milestone 005 introduced named-buyer exchange agreements, append-only exchange events and immutable, unverified payment-reference evidence. It intentionally did not provide a mechanism for participants to record disagreements. A bilateral exchange needs a structured way to preserve each party's statement and record whether a proposed resolution was mutually accepted.

## Decision

Add one participant-scoped dispute record per exchange, with an append-only message history and a two-party resolution flow.

A buyer or merchant may open a dispute only after an exchange has been accepted. The dispute categories are non-delivery, quantity mismatch, quality concern, payment question, terms disagreement and other. Either participant may add statements while the dispute is open.

Either participant may propose a written resolution. Only the other participant may accept or reject that proposal. Acceptance closes the dispute as resolved; rejection returns it to open while preserving the proposal and rejection in the immutable message history and exchange event timeline. The initiating participant may withdraw an open dispute only before the other participant has responded.

Every operation locks the exchange and dispute rows in a consistent order and derives authority from the authenticated guard and participant relationship. Request payloads never supply actor IDs.

## Evidence and trust boundaries

- Dispute messages cannot be updated or deleted through the application models.
- Dispute lifecycle actions append exchange events with actor attribution and declared verification status.
- A party's statement remains a party-declared statement, not independently verified evidence.
- A mutually accepted resolution records the parties' agreement; it is not an adjudication or legal finding.
- The original agreement, exchange status and submitted payment evidence remain unchanged.
- Dispute records do not initiate refunds, chargebacks, wallet mutations, payment-provider calls or settlement reconciliation.
- One dispute record is permitted per exchange in this first slice. Closed or withdrawn records remain visible to the two participants and cannot be reopened or replaced.

## API surface

The buyer and merchant use the same resource shape through their own authenticated route groups:

- Read dispute
- Open dispute
- Add a statement
- Propose a resolution
- Accept a proposal
- Reject a proposal
- Withdraw before counterparty response

## Consequences

### Positive

- Preserves disagreement and both parties' positions alongside the exchange's event history.
- Makes resolution explicitly bilateral rather than allowing one side to close the other side's claim.
- Establishes an auditable dispute state without silently changing financial or delivery records.

### Trade-offs and non-goals

- No independent mediator, arbitration provider, appeal workflow, evidence-file upload or automated decision is included.
- No statutory or contractual dispute deadline is inferred by this implementation.
- Dispute content is available only to the authenticated exchange participants; future institutional review access requires a separate authorization design.
