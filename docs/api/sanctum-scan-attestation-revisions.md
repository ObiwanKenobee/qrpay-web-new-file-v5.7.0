# Sanctum Scan versioned attestation API

These endpoints extend the verifier routes introduced in Milestone 008 and governed by Milestone 009. They use the authenticated user API guard, account-status checks and two-factor middleware. The acting verifier is always derived from the authenticated session; request-body actor identifiers are not accepted.

## Correct an issued attestation

POST /api/user/sanctum/verifier/memberships/{membershipId}/attestations/{attestationId}/corrections

Request fields:

- verification_method: document_review, source_cross_check, site_inspection, direct_confirmation or other.
- outcome: corroborated, not_corroborated or unable_to_assess.
- basis_summary: required explanation of the corrected assessment, 20–2000 characters.
- reason_code: factual_error, methodology_error, new_evidence, conflict_discovered, credential_issue, evidence_integrity or other.
- reason_summary: required explanation of why the correction is being issued, 20–2000 characters.

Only the original attestation's verifier user and exact membership can issue the correction. The institution and membership must remain active; the original mandate must be active and current or completed, not revoked. The latest institution registration and exact-scope verifier credentials must be verified and current. The latest conflict declaration must be a no-conflict declaration. The source evidence object must pass the existing SHA-256 integrity check.

The verification scope remains the original mandate's scope. A correction creates the next immutable revision version; it does not update the source attestation.

## Withdraw an issued attestation

POST /api/user/sanctum/verifier/memberships/{membershipId}/attestations/{attestationId}/revocation

Request fields:

- reason_code: factual_error, methodology_error, new_evidence, conflict_discovered, credential_issue, evidence_integrity or other.
- reason_summary: required explanation, 20–2000 characters.

Only the original verifier user and recorded membership can withdraw the attestation. The original account must remain active and verified.

Withdrawal deliberately remains possible after a mandate is completed, expires or is revoked, and after a conflict or credential issue is discovered. It does not require the evidence file to be readable or to pass integrity checks; this preserves a safe way to withdraw a questionable statement when evidence storage has failed.

Withdrawal creates an immutable revision that preserves the last effective assessment and records the reason. It is terminal: the attestation cannot be corrected or reactivated afterward. A new attestation, if appropriate, requires a new mandate and a separate issuance process.

## Participant evidence view

The existing participant evidence listing at:

GET /api/user/sanctum/exchanges/{exchangeId}/dispute/evidence

continues to expose the original fields for each institutional attestation and adds:

- current.status: active or revoked.
- current.version_number: version 1 for the original, or the latest revision version.
- current.verification_method, current.outcome and current.basis_summary: the effective statement.
- revisions[]: the append-only history, including version, predecessor ID, action, reason, digest and recorded time.

The original values remain visible in the original attestation fields. A correction does not conceal them. A withdrawal is represented as a terminal latest revision, not deletion.

Appeals and platform process decisions remain separate and are shown alongside the revision history. Neither an appeal decision nor a correction/revocation changes the exchange, dispute, payment, wallet, refund or settlement state.

## Revision version rules

- Original attestation: version 1, stored in sanctum_scan_independent_attestations.
- First correction or withdrawal: version 2.
- Each later correction links to the preceding revision and increments the version.
- Every revision retains the exact original evidence SHA-256.
- No revision can be updated or deleted.
- No revision may follow a withdrawal.
