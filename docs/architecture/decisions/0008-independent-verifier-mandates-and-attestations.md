# ADR 0008: Independent verifier mandates and attestations

- Status: Accepted for the reference implementation
- Date: 2026-11-13
- Depends on: ADR 0007 — Private dispute evidence and platform review

## Context

Platform administrator review and independent institutional verification are different trust claims. A platform administrator must not be able to label an internal review as third-party verification, and an external verifier must not obtain broad access to private dispute material merely by joining an institution.

The reference implementation needs a narrow authorization path for an external evidence attestation:

1. A platform administrator curates an institution record.
2. The administrator invites an existing, active user account whose email and phone are verified.
3. The invited user accepts the membership using their own authenticated account.
4. A platform administrator issues a time-limited mandate for one evidence item and one verification scope.
5. An active member of that institution can privately retrieve only the mandated evidence and submit one immutable attestation under that mandate.
6. The attestation is bound to the stored evidence SHA-256 and appears separately from the platform review.

## Decision

- Keep institution registration and mandate issuance administrator-controlled.
- Treat an institution's registration reference and profile fields as declarations recorded by the administrator, not automatic proof of legal registration or accreditation.
- Reuse existing user authentication and verified account status; do not introduce a parallel verifier password or token system.
- Require invitation acceptance before a member can act.
- Scope access to an exact evidence item, institution, declared verification purpose and mandatory expiry.
- Verify the stored evidence hash before an authorized verifier download or attestation.
- Prohibit the buyer on the exchange and the user account that submitted the evidence from attesting to that evidence.
- Keep institutional outcomes as corroborated, not_corroborated or unable_to_assess; do not collapse them into a universal trust score.
- Make attestations and registry events append-only at the application model layer.
- Revoke active mandates whenever an institution is suspended or revoked.
- Present external attestations separately from platform administrator reviews and participant declarations.
- Do not mutate dispute status, exchange status, wallet state, financial transactions, payment settlement or refund state.

## Consequences

This creates a minimum auditable authorization path while avoiding a new credential system. It does not establish legal accreditation, independently validate an institution's registration documents, prove an individual's professional licence, provide cryptographic signatures or establish conflict-free relationships beyond the currently modelled buyer and evidence-submitter checks.

The repository uses separate user and merchant account tables and does not currently provide a verified cross-account identity mapping. Therefore, this milestone can reliably exclude the exchange buyer and a user-guard evidence submitter, but cannot establish that a user verifier is unrelated to the merchant account that submitted evidence. That check must not be represented as complete.

Before production use, institutional onboarding needs documented due diligence, verifier identity and qualification checks, conflict-of-interest declarations, retention and privacy controls, stronger audit export, cryptographic signing/key management, and a process for appeals and corrections. The current reference implementation does not claim these controls exist.

## Alternatives considered

- Reusing platform-admin reviews: rejected because an internal platform assessment is not an independent institutional attestation.
- Giving every verifier a broad evidence inbox: rejected because it exposes dispute evidence without evidence-specific authorization.
- Issuing standalone bearer verifier credentials: deferred because it would create a second authentication and revocation system before a justified need exists.
- Allowing participants to self-appoint as verifiers: rejected because it defeats basic independence for the same dispute.

## Security invariants

- Only an active institution and active, accepted member can use a mandate.
- A mandate is tied to one evidence row and one verification scope and must have a future expiry.
- A verifier must be authorized by membership and the evidence-specific mandate at the time of access.
- The stored file must still match the immutable SHA-256 captured at submission.
- The authenticated account, not a request-body actor ID, is the verifier identity.
- The exchange buyer and user evidence submitter cannot attest to that item.
- Attestations cannot be updated or deleted through their Eloquent model.
- Suspending or revoking an institution revokes its active mandates.
- No attestation changes the exchange, dispute or financial state.
