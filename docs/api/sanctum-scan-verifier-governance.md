# Sanctum Scan verifier governance API

All administrator routes are inside the existing authenticated admin, role-permission and Google 2FA middleware group. The URL prefix is the configured admin prefix; examples use \`{admin-prefix}\`.

Verifier routes use the authenticated user API guard and existing account-status and two-factor middleware. A verifier must already have an accepted membership and a current evidence-specific mandate.

## Administrator credential review

### Record an institutional registration check

POST \`/{admin-prefix}/sanctum/verifiers/{institutionId}/credentials\`

Request fields:

- \`membership_id\`: omit for an institutional registration record; set to an existing membership for an individual verifier credential.
- \`credential_scope\`: \`institution_registration\` when \`membership_id\` is omitted; otherwise the exact verification scope covered by the credential.
- \`credential_type\`, \`issuer_name\`, \`credential_reference\`: required identifying metadata.
- \`jurisdiction\`, \`source_url\`: optional source context.
- \`review_method\`: \`registry_lookup\`, \`issuer_confirmation\`, \`document_review\` or \`other\`.
- \`review_status\`: \`verified\`, \`rejected\` or \`unable_to_verify\`.
- \`review_summary\`: required review rationale, 20–2000 characters.
- \`expires_at\`: optional expiry.

Each submission creates a new immutable record. The latest record for the institutional registration or membership/scope controls authorization. A rejected or unable-to-verify record supersedes an earlier positive review. Verified records with an expired \`expires_at\` no longer authorize evidence access.

This endpoint records an administrator's manual check; it does not call an external government or professional registry and does not certify accreditation.

### Review credential history

GET `/{admin-prefix}/sanctum/verifiers/{institutionId}/credentials`

Returns the institution's credential review records, newest first, with membership association, review source, status, summary, reviewer and expiry.

## Verifier conflict declaration

### Declare or disclose a conflict

POST \`/api/user/sanctum/verifier/memberships/{membershipId}/evidence/{evidenceId}/conflict-declaration\`

Request fields:

- \`has_conflict\`: required boolean.
- \`disclosure_statement\`: required explanation, 20–2000 characters. For a no-conflict declaration, record the scope and basis of the declaration.

The declaration is tied to the active membership and matching evidence mandate. Institutional registration and member qualification reviews must be current and verified first. A declaration is immutable. A later declaration that discloses a conflict supersedes a no-conflict declaration and blocks further evidence access and attestation under that mandate. A disclosed conflict cannot be cleared for the same mandate by submitting another declaration.

### Evidence access and attestation

The existing download and attestation routes now also require:

1. A latest verified and current institution registration review.
2. A latest verified and current member credential review matching the mandate scope.
3. A current no-conflict declaration for that membership and mandate.
4. The existing active institution, active accepted membership, active mandate and evidence SHA-256 checks.

A verifier with a disclosed conflict receives an authorization failure and must not access or attest to the evidence under that mandate.

## Participant attestation appeal

### Submit an appeal

POST \`/api/user/sanctum/exchanges/{exchangeId}/dispute/evidence/{evidenceId}/attestations/{attestationId}/appeals\`

The same route is available to authenticated merchants under \`/merchant-api/merchant\`.

Request fields:

- \`reason_code\`: \`scope_disagreement\`, \`evidence_integrity\`, \`undisclosed_conflict\`, \`factual_error\`, \`methodology\` or \`other\`.
- \`statement\`: required explanation, 20–2000 characters.

Only the buyer or merchant recorded on the exchange can appeal. The application derives the actor from the authenticated guard; request-body actor IDs are ignored. Each side can lodge one immutable appeal for a particular attestation. The appeal snapshots the attestation's evidence SHA-256.

The participant evidence listing includes the appeal reason, statement, submission time and any later process decision alongside the unchanged institutional attestation.

## Platform appeal review

### List pending appeals

GET \`/{admin-prefix}/sanctum/verifier-appeals\`

Returns a paginated queue of appeals without a process decision.

### Record a process decision

POST \`/{admin-prefix}/sanctum/verifier-appeals/{appealId}/decision\`

Request fields:

- \`outcome\`: \`upheld\`, \`overturned\`, \`correction_recommended\` or \`dismissed\`.
- \`decision_summary\`: required explanation, 20–2000 characters.

Only one immutable decision can be recorded for an appeal. The original attestation remains unchanged and visible alongside the appeal and decision.

## State and trust boundaries

Credential reviews, conflict declarations, appeals and appeal decisions are retained as append-only records. Appeal and decision events are also appended to the related exchange timeline.

An appeal decision is a platform process finding, not independent arbitration, a court ruling, a regulator's decision or a compensation award. No endpoint changes the exchange, dispute, wallet, financial transaction, refund or settlement state.