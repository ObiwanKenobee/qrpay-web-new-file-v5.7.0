# ADR 0011: Independent attestation reassessment

- Status: Proposed
- Date: 2026-11-13
- Scope: Sanctum Scan independent verifier registry

## Context

Milestone 010 deliberately limits correction and withdrawal to the original verifier user and exact membership. This preserves authorship, but it leaves an operational gap when the original verifier leaves the institution, loses account access, or cannot responsibly issue a correction or withdrawal.

Allowing another person to mutate the first attestation would falsely transfer authorship. Treating a new verifier as the original issuer would be impersonation. The platform therefore needs a safe way to preserve institutional review continuity without granting successor identities edit rights over historical statements.

## Decision

A different active member of the same registered institution may issue a **new independent reassessment** of an earlier attestation when all of the following hold:

1. The member is invited and accepts using their own active, email-verified and phone-verified user account.
2. The new member has a current, latest verified credential review for the original attestation's exact verification scope.
3. The institution remains active and its latest registration review remains verified and current.
4. A new, active, evidence-specific mandate is issued for the same evidence and scope. The prior attestation's mandate cannot be reused.
5. The new member records a no-conflict declaration for the new mandate.
6. The private evidence object still matches its stored SHA-256 digest.
7. The reassessor is not the original attestation's membership or user.

The service creates a normal new immutable attestation through the existing M8–M9 authorization pipeline. It then appends an immutable relationship record linking the new attestation to the exact prior attestation version considered. The link snapshots the prior revision ID (when present), version number, active/revoked state, institution, evidence ID and digest, reassessing membership/user and reason.

The prior attestation and every M10 revision remain unchanged. A reassessment does not transfer ownership, revise, revoke, replace, validate, or reactivate the prior statement. Multiple independently mandated reassessments may reference the same earlier attestation.

## API

`POST /api/user/sanctum/verifier/memberships/{membershipId}/attestations/{attestationId}/reassessments`

The authenticated API user is the reassessor. The request carries only method, outcome, basis summary and reason summary. Evidence ID and verification scope are derived from the prior attestation; actor IDs are never accepted from the request body.

## Consequences

- Institutional review can continue through a new accountable verifier without impersonating the former issuer.
- Participants can see both the source attestation and linked reassessment in the evidence history.
- The exact prior version considered is captured even if the source later receives another M10 revision.
- New reassessments are subject to the same evidence-integrity, conflict, membership, mandate and credential checks as ordinary attestations.
- A fresh assessment remains an issuer's scoped statement, not proof that the prior statement is false or that the new statement is objectively correct.

## Trust boundaries and non-goals

- This is not legal succession, employment verification, accreditation, or proof that the new member has authority to speak for the institution outside the registered verifier workflow.
- It is not an automatic appeal outcome, dispute ruling, or arbitration decision.
- The prior attestation is not marked superseded. Participants must interpret the separate statements, their scopes, versions and provenance.
- The relationship does not establish a verified mapping between user and merchant accounts; that cross-guard identity limitation remains.
- The reassessment does not change the underlying exchange, dispute, payment, wallet, refund or settlement state.

## Security invariants

- No user may reassess their own attestation through the reassessment endpoint.
- Only a different active membership of the same active institution may reassess it.
- A reassessment requires a distinct active mandate for the same evidence and original scope.
- Current institution and exact-scope verifier credentials, a new no-conflict declaration and intact evidence are mandatory.
- The link and the new attestation are append-only; both the original attestation and its M10 revision chain remain immutable.
- The reassessment references the exact source version visible at the time it was recorded.
- The reassessment transaction locks rows in the shared governance order: institution → reassessing membership → source attestation → mandate. This serializes institution suspension and source corrections with reassessment without reversing the M010 correction lock order.
