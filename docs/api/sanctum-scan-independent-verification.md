# Sanctum Scan independent verification API

All administrator routes are inside the existing authenticated admin, role-permission and Google 2FA middleware group. The URL prefix is the configured admin prefix; examples below use {admin-prefix} as a placeholder.

All verifier routes use the authenticated user API guard, account-status and existing user two-factor middleware. The user must accept an invitation before acting as a verifier.

## Administrator registry

### List institutions

GET /{admin-prefix}/sanctum/verifiers

Returns institution records and counts of memberships, mandates and attestations.

### Register an institution

POST /{admin-prefix}/sanctum/verifiers

Request fields:
- name: required, maximum 180 characters
- organization_type: public_agency, research_institute, inspection_body, cooperative, nonprofit, standards_body or other
- country_code: required two-letter country code
- registration_reference: optional, maximum 120 characters
- website: optional URL

This endpoint curates a registry entry. The registration reference is not independently checked by the application.

### Invite an existing user

POST /{admin-prefix}/sanctum/verifiers/{institutionId}/members

Request body contains user_id. The account must be active with verified email and phone. The user must accept the invitation through their own account before membership becomes active. A revoked membership cannot be reactivated.

### Suspend, reactivate or revoke an institution

POST /{admin-prefix}/sanctum/verifiers/{institutionId}/status

Request body contains status: active, suspended or revoked.

Revocation is terminal. Suspending or revoking an institution revokes all its active mandates; reactivating the institution does not silently restore those mandates.

### Suspend, reactivate or revoke a membership

POST /{admin-prefix}/sanctum/verifiers/memberships/{membershipId}/status

Request body contains status: active, suspended or revoked.

A pending invitation must be accepted by the invited user; an administrator cannot activate it on the user's behalf. An accepted member can be reactivated after suspension, provided the institution is active. A revoked membership is terminal.

## Evidence-specific mandate

### Grant a mandate

POST /{admin-prefix}/sanctum/evidence/{evidenceId}/mandates

Request fields:
- institution_id: registered institution identifier
- verification_scope: document_authenticity, source_provenance, delivery_observation, quality_observation, payment_document or other
- mandate_reason: required explanation, 20–2000 characters
- expires_at: required future date and time

Only one active mandate per institution and evidence item is allowed. A previous expired, completed or revoked mandate can be reissued; its changes are retained in the registry event log.

### Revoke or complete a mandate

POST /{admin-prefix}/sanctum/evidence/mandates/{mandateId}/status

Request body contains status: revoked or completed.

## Verifier account

### List memberships and invitations

GET /api/user/sanctum/verifier/memberships

### Accept or decline an invitation

POST /api/user/sanctum/verifier/memberships/{membershipId}/accept

POST /api/user/sanctum/verifier/memberships/{membershipId}/decline

Only the user account named in the invitation can respond.

### Download mandated evidence

GET /api/user/sanctum/verifier/memberships/{membershipId}/evidence/{evidenceId}/download

Access requires an active membership, active institution, matching active mandate and unexpired authorization. The service verifies the file against the stored SHA-256 before streaming it privately.

### Record an institutional attestation

POST /api/user/sanctum/verifier/memberships/{membershipId}/evidence/{evidenceId}/attestations

Request fields:
- verification_scope: must exactly match the mandate
- verification_method: document_review, source_cross_check, site_inspection, direct_confirmation or other
- outcome: corroborated, not_corroborated or unable_to_assess
- basis_summary: required explanation, 20–2000 characters

Each membership can record one attestation per mandate. The exchange buyer and the user account that submitted the evidence cannot attest to that evidence.

## Trust and state boundaries

An attestation records the institution, membership, authenticated user, method, scope, outcome, rationale, time and exact evidence SHA-256. It is separate from a platform-admin review and a participant statement. It does not prove payment settlement, establish legal accreditation, change the dispute or exchange state, or certify facts outside its stated scope.

The current reference implementation does not include third-party identity-document verification, professional credential validation, cryptographic signing, external email invitation delivery or an appeal workflow. Merchant and user accounts are separate in this repository; without an established mapping, the application cannot prove the verifier is unrelated to the merchant account that submitted an evidence file.
