# ADR 0001: Extend the Existing Laravel Application Incrementally

- **Status:** Accepted for the initial engineering baseline
- **Date:** 2026-11-13
- **Decision owners:** Atlas Sanctum maintainers

## Context

The repository is an established Laravel application rather than an empty QR prototype. The current codebase includes user, merchant and agent accounts; wallet and transaction models; payment-provider integrations; separate user, merchant and agent QR-code records; and a merchant payment API.

The current QR payment gateway uses a merchant payment-order request and a short-lived access token to create a checkout flow. The QR records in the existing models are separate from a general-purpose economic object or exchange protocol.

A full rewrite would duplicate existing capabilities, increase migration risk and delay validation with real users.

## Decision

Develop Sanctum Scan as a **modular extension of the existing Laravel application** until evidence demonstrates that a component needs to be extracted into a separate service.

1. Preserve existing user, merchant, agent, wallet and payment capabilities while their behavior and ownership are audited.
2. Treat existing QR and payment gateway code as legacy integrations/adapters, not as the complete Sanctum Scan protocol.
3. Introduce new bounded capabilities with explicit contracts and tests.
4. Keep the first vertical slice focused on resolving a safe, opaque scan reference to a server-side resource description.
5. Connect payment requests to existing payment rails through an adapter. A scan reference is not itself authorization, money, or evidence of settlement.
6. Keep payment settlement authoritative at the relevant payment provider and reconcile it explicitly before representing an exchange as settled.
7. Store timestamps consistently in UTC and localize presentation at the user or deployment boundary.
8. Do not introduce a universal personal trust score. Represent specific credentials, events, evidence and disputes with provenance and permissions.
9. Do not add a root open-source license until source provenance and redistribution rights for the inherited application have been established.

## QR and exchange safety requirements

- QR payloads must not contain passwords, API secrets, payment credentials, or unnecessary personal information.
- Prefer opaque, high-entropy references resolved server-side over embedding internal database identifiers or sensitive claims.
- Any time-bound action must have an explicit expiry and server-side status checks.
- State-changing operations must be authorized and protected against replay and duplicate submission.
- Use idempotency keys for operations that can create financial or other consequential effects.
- A local/offline intent or receipt must never be represented as a completed payment without authoritative settlement confirmation.
- Corrections to an economic history should be represented through traceable corrective events rather than silently rewriting past evidence.

## Initial implementation sequence

1. Establish environment and repository safety defaults.
2. Document existing QR, merchant, payment-order and settlement boundaries.
3. Define a versioned scan-reference contract.
4. Implement server-side reference resolution without moving money.
5. Add authorization, expiry, replay and idempotency tests.
6. Integrate one existing payment flow only after its behavior and security have been reviewed.

## Consequences

### Positive

- Reuses existing application behavior rather than starting over.
- Reduces duplicate identity, wallet and payment systems.
- Creates a path from a QR payment product toward broader economic exchange.
- Allows protocol and application boundaries to evolve with evidence.

### Trade-offs

- The Laravel monolith remains the initial deployment unit.
- Legacy code requires continued security and ownership review.
- New modules must avoid accidental coupling to existing database internals.
- Extraction into packages or services is deferred until justified by usage, scale or independent deployment needs.
