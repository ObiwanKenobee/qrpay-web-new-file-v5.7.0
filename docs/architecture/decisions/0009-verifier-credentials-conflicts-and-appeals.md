# ADR 0009: Verifier credentials, conflicts of interest and appeals

- Status: Accepted for the reference implementation
- Date: 2026-11-13
- Depends on: ADR 0008 — Independent verifier mandates and attestations

## Context

Milestone 008 established evidence-specific institutional mandates and immutable attestations. It did not yet require administrator-reviewed institutional registration or member qualifications, did not collect a conflict declaration before a verifier accessed evidence, and did not provide an appeal path for exchange participants.

The next layer must improve accountability without implying that a platform record is a regulator's accreditation, a court's determination or a payment confirmation.

## Decision

### Credential due diligence

- Record each credential check as a new immutable review row.
- An institution must have a latest, unexpired \`verified\` \`institution_registration\` review before its verifier members can access evidence or attest.
- A verifier membership must have a latest, unexpired \`verified\` review whose scope exactly matches the evidence mandate.
- A later rejected, unable-to-verify or expired credential review supersedes earlier positive checks for authorization.
- Record the issuer, reference, jurisdiction, source URL, review method, reviewer, outcome, summary and optional expiry.
- Credential records store references and review metadata, not identity-document uploads or secret credential material.

A platform administrator's \`verified\` review means that the administrator recorded a check against the stated source and method. The application does not automatically query government registries, accreditors or professional bodies. It does not assert legal recognition outside the specific source and review summary.

### Conflict-of-interest declarations

- Require the authenticated verifier to declare conflicts for the exact membership and mandate before the verifier can download evidence or attest.
- Bind the declaration to the verifier account, mandate and membership.
- Keep declarations immutable. A later disclosure of a conflict supersedes a previous no-conflict declaration and blocks the verifier from further access under that mandate.
- Do not treat a no-conflict declaration as proof that no undisclosed conflict exists.
- Existing structural checks against the exchange buyer and user-guard evidence submitter remain in force.

### Appeals

- Only the buyer or merchant recorded on the exchange can appeal an attestation about its dispute evidence.
- Permit one appeal per participant, per attestation. The appeal stores the exact evidence digest, reason code, statement, authenticated guard and actor, and submission time.
- A platform administrator may record one immutable process decision for each appeal: \`upheld\`, \`overturned\`, \`correction_recommended\` or \`dismissed\`.
- Preserve the original attestation and appeal statement. A decision is a separate record; it never edits the attestation, evidence file, dispute or exchange.
- Append appeal and decision events to the verifier registry event log and exchange event timeline.
- Expose submitted appeals and decisions to the exchange participants alongside the original attestation.

## Consequences and limits

The reference implementation provides an auditable due-diligence record and appeal workflow, not automatic external credential validation or formal arbitration. Administrator review is an internal process. An appeal outcome does not establish legal liability, reverse settlement, award compensation or automatically amend a dispute.

The repository still lacks verified identity mapping between user and merchant account tables. Therefore, it cannot establish that a verifier user account is unrelated to a merchant account that submitted evidence. Credential reviews and conflict declarations do not close that cross-account identity gap.

Before production use, the project still needs independent operational due diligence, secure evidence retention, access monitoring, verifier identity proofing, a documented conflict policy, institutional appeal service levels and specialist legal review.

## Security invariants

- The latest institutional registration review must be verified and current.
- The latest member credential review must be verified, current and match the mandate scope.
- The verifier must disclose conflicts before evidence can be accessed or attested.
- The authenticated verifier account is used; no actor ID is accepted from the request body.
- A disclosed conflict blocks further access under the mandate.
- Only exchange participants can appeal; appellant identity is derived from the authenticated buyer or merchant guard.
- Appeals and decisions are immutable and digest-bound.
- A platform process decision never changes the original attestation or financial/exchange state.
