# ADR 0010: Versioned independent-attestation corrections and withdrawal

- Status: Accepted for the reference implementation
- Date: 2026-11-13
- Depends on: ADR 0008 (independent verifier mandates and attestations) and ADR 0009 (verifier credentials, conflict declarations and appeals)

## Context

An immutable attestation must not become impossible to correct when an issuer discovers an error, receives material new evidence or identifies a conflict. Updating the original row would destroy the audit trail; creating a second unrelated attestation would obscure which statement supersedes the earlier one.

The system therefore needs a versioned, digest-bound chain that preserves the original statement and every subsequent change.

## Decision

### Version chain

- The original sanctum_scan_independent_attestations row is version 1 and remains immutable.
- Each correction or withdrawal creates a new immutable sanctum_scan_attestation_revisions row. The first revision is version 2.
- Every revision is bound to the same original attestation, evidence row and SHA-256 digest.
- Each later revision points to the immediately preceding revision. The per-attestation version number and unique predecessor constraint prevent forked history.
- Each revision stores a full effective snapshot of scope, method, outcome and basis summary, plus the action, reason, authenticated issuer and timestamp.
- The verification scope cannot be changed by a correction because it is fixed by the original mandate.

### Corrections

- Only the authenticated user recorded as the original attestation's verifier, using the same verifier membership, can issue a correction.
- The user, membership, institution and mandate must remain eligible for a correction. The mandate may be active or completed but cannot have been revoked; an active mandate must not be expired.
- The institution registration credential and exact-scope verifier credential must be current and verified.
- The latest conflict declaration for the exact membership and mandate must be a no-conflict declaration.
- The evidence object must still pass the existing private-storage SHA-256 integrity check.
- A correction creates a new version with its own method, outcome, basis and reason. It never edits the original attestation.

### Withdrawal

- Only the original verifier user and recorded membership may withdraw the attestation.
- Withdrawal remains available to that original verifier after a mandate is completed, expired or revoked, or after a conflict or credential problem is discovered. This deliberately keeps a protective withdrawal path open when a correction is no longer appropriate.
- Withdrawal does not require reading the evidence object; an unavailable or tampered file must not prevent the issuer from withdrawing a questionable attestation.
- The withdrawal revision retains the last effective snapshot and records the withdrawal reason.
- Withdrawal is terminal. No later correction can reactivate the attestation or create a successor version.

### Participant-facing effective state

The participant evidence response retains the original attestation fields and adds:
- current: the effective version, version number and active or revoked state.
- revisions: the full immutable, ordered history, including predecessor link, reason, evidence digest and timestamp.

The original attestation and previous revisions remain available for audit. An appeal and its process decision remain separate records and are not automatically converted into corrections or withdrawals.

### Events

Every revision appends a verifier-registry event and, where the evidence belongs to an exchange, an exchange-timeline event. Neither operation changes the exchange, dispute, wallet, payment, refund or settlement state.

## Consequences and limits

This is an issuer-controlled correction and withdrawal mechanism, not an independent adjudication process. A correction does not prove which version is factually true; it records what the authorized issuer now states and why. A withdrawal indicates that the issuer no longer stands behind the attestation; it does not erase the earlier version or automatically settle the dispute.

A platform administrator cannot silently rewrite an institutional attestation. A different verifier membership cannot revise the original issuer's attestation. If the original verifier account is no longer active, the application does not impersonate that person to revise the record; production governance will need a separately designed institutional succession and platform-level safety procedure.

The existing user/merchant cross-account identity limitation remains. This milestone does not establish that a verifier account is unrelated to a merchant account belonging to the same real-world person.

## Security invariants

- Original attestation and every revision are immutable.
- Revision version numbers are sequential per original attestation.
- Revisions form a single predecessor chain and all versions retain the exact source evidence SHA-256.
- Corrections require the original issuer, an active institution and membership, current M9 credential checks, a no-conflict declaration and valid private evidence integrity.
- Correction eligibility is serialized with institutional governance writes by locking institution, membership, attestation and mandate records in that order before creating the next revision.
- Withdrawal is available to the original issuer as a protective action even when institution/membership eligibility has changed, the evidence is unavailable or the mandate has ended or been revoked. It does not depend on active institution or membership status.
- Withdrawal is terminal.
- No revision operation changes exchange, dispute, payment, wallet, refund or settlement state.
