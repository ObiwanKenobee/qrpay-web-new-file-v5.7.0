# Sanctum Scan independent attestation reassessment API

This endpoint creates a fresh, separately attributable attestation linked to an earlier institutional statement. It does not edit or withdraw the earlier attestation.

## Create a reassessment

`POST /api/user/sanctum/verifier/memberships/{membershipId}/attestations/{attestationId}/reassessments`

The route uses the existing authenticated user API guard, account-status checks and two-factor middleware. The acting verifier is derived from the authenticated session.

Request body:

- `verification_method`: `document_review`, `source_cross_check`, `site_inspection`, `direct_confirmation` or `other`.
- `outcome`: `corroborated`, `not_corroborated` or `unable_to_assess`.
- `basis_summary`: required explanation, 20–2000 characters.
- `reason_summary`: required explanation for reassessment, 20–2000 characters.

The evidence ID and verification scope are taken from the prior attestation. The request cannot supply a replacement actor, institution, evidence ID, scope, prior revision ID or digest.

## Authorization and evidence rules

The reassessor must be a different user and membership from the original issuer, with an active membership in the same active institution. The prior institution must have a current latest verified registration review. The reassessor must have a current latest verified credential review for the exact scope.

A new active evidence mandate is required for the same evidence and original scope. The prior mandate cannot be reused. A no-conflict declaration must be recorded for the new mandate. Existing private evidence integrity verification runs before the new attestation is created.

The transaction locks authorization rows in this order: institution, reassessing membership, source attestation, then mandate. This is the same governance order used by M010 correction handling. It prevents a source correction or institution-status change from racing the reassessment snapshot or creating a lock-order inversion.

The source attestation is locked before its latest revision is read. The link therefore records the exact source version and active/revoked state considered at the transaction's serialization point.

If any check fails, no new attestation or relationship record is retained.

## Participant history

The participant evidence listing remains:

`GET /api/user/sanctum/exchanges/{exchangeId}/dispute/evidence`

For each prior attestation, `reassessments[]` lists linked reassessments with:

- The prior revision ID, version number and status considered.
- The new attestation ID, institution, method, outcome and effective status.
- The reassessment reason, evidence SHA-256 and recorded timestamp.

The new attestation also includes `reassessment_of`, which points back to the prior attestation and the exact prior version considered. Both statements remain present in `independent_attestations[]`.

## Meaning and limits

A reassessment is a new institutional statement, not a successor editing the old statement. It does not mark the earlier attestation as superseded, does not decide which statement is true, and does not resolve an appeal or dispute. The original verifier's history remains unchanged.

This workflow is not legal institutional succession or automatic accreditation. It does not verify whether the new member has authority outside the platform's registered mandate and review process. The existing user/merchant cross-account identity-mapping limitation remains.

Reassessment events are appended to the verifier registry and exchange timeline. The operation does not change the exchange, dispute, payment, wallet, refund or settlement state.
