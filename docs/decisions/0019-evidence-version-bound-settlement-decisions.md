# ADR 0019 — Evidence-Version-Bound Settlement Decisions

**Status:** Accepted
**Date:** 2026-11-17
**Depends on:** ADR 0017 (settlement state machine), ADR 0018 (payment evidence versioning)

## Context

ADR 0018 made payment evidence append-only and versioned. A buyer can submit
version 1, receive an administrative rejection, then append version 2 and
reopen verification. The current settlement event records the transition,
actor, reason and timestamp, but it does not identify which evidence version
the administrator reviewed. The admin projection can show the latest evidence
history, but the event itself does not bind a decision to one row.

That leaves an audit ambiguity: a later reader can infer which version was
current at the time only by reconstructing the event sequence and transaction
ordering. The immutable decision should state its evidence subject directly.

## Decision

For `payment_verified`, `payment_rejected` and `settled` transitions, the
immutable `settlement_status_changed` event must include:

- `payment_evidence_id`: the exact evidence row acted upon;
- `payment_evidence_version`: that row's version number;
- `payment_evidence_sha256`: a deterministic SHA-256 fingerprint over the
  complete immutable evidence payload.

The fingerprint is computed from an ordered payload containing the exchange
ID, evidence ID, version, superseded evidence ID, rail, reference, amount,
currency, submitting guard and actor ID, and submission timestamp normalized
to UTC. It is serialized as compact UTF-8 JSON with unescaped slash and Unicode
characters, invalid-UTF-8 substitution, and preserved zero fractions. The
ordered key list and serialization flags are part of the digest contract.

The decision event does not duplicate the payment reference or other evidence
fields. The existing evidence row remains immutable and its
`verification_status` remains `unverified`; the administrator's
`platform_verified` or `platform_rejected` decision is represented by the
separate immutable settlement event.

A decision transition fails with `payment_evidence_missing` when the evidence
row is absent. The verified transition continues to enforce amount and
currency equality with the accepted offer. The `settled` event is also bound
to the same evidence version as the preceding verification decision.

The admin settlement projection exposes the three binding fields for each
decision event.

## Consequences and limitations

- An auditor can identify the exact evidence version without relying on a
  reconstructed latest-row query.
- The fingerprint detects changes to the evidence payload when independently
  recomputed; it is not a signature, trusted timestamp, provider attestation or
  proof of payment.
- The event and evidence row are both stored in the existing exchange
  transaction boundary; no schema migration is required because event data is
  already JSON.
- This decision does not add payment-provider ingestion, automatic matching,
  wallet writes or movement of funds.

## Verification

- `SettlementLifecycleTest::test_full_settlement_lifecycle_moves_one_step_per_admin_action_and_is_audited`
  asserts version 1 is bound to verification and settlement events.
- `SettlementLifecycleTest::test_admin_rejection_allows_a_versioned_resubmission_and_reopened_verification`
  asserts rejection binds version 1 and the later verification binds version 2
  with a different fingerprint.
- The admin projection assertions verify the binding fields are returned.
- PHP lint, PHPUnit, and the MySQL job must execute in CI before this milestone
  is marked Verified.
