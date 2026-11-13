# ADR 0012: Participant-scoped provenance export

- **Status:** Proposed implementation
- **Date:** 2026-11-13
- **Scope:** Read-only provenance export for an existing Sanctum Scan exchange dispute

## Context

Milestones 005–011 establish an exchange record, bilateral dispute statements, private evidence, platform review, independent institutional attestations, versioned corrections and withdrawals, and separately attributable reassessments. These records are useful individually, but a participant needs a portable way to inspect the connected history without copying private database records or exposing internal storage details.

The README roadmap calls for audit records and a real-world exchange pilot. The next increment should make the existing evidence trail exportable while preserving the access boundaries already enforced by the exchange and dispute services.

## Decision

Provide an authenticated, read-only JSON provenance manifest to the buyer and merchant on the exchange.

The export contains:

- A bounded exchange snapshot: exchange ID and state, resource snapshot, agreed quantity, unit, amount, currency, terms and lifecycle timestamps.
- A bounded dispute snapshot: dispute ID, category, state, resolution summary and lifecycle timestamps.
- Participant dispute messages with message type, body, generalized actor type and timestamp.
- Existing evidence presentation, including file name, MIME type, size, SHA-256, caption and submitting party role; the existing platform review; independent attestations; versioned revisions; appeals and process decisions; and linked reassessments.
- A filtered exchange timeline containing event ID/type, description, verification status, generalized actor type and recorded time.

The manifest includes format, schema version, generation timestamp, hash algorithm, hash scope and a SHA-256 digest over the `data` object. The digest is computed from compact JSON using UTF-8, unescaped slashes and Unicode, invalid-UTF-8 substitution, and preserved zero fractions. The generation timestamp is outside the hashed data so identical snapshots yield identical digests.

## Authorization and privacy

- The existing authenticated API user and merchant route groups are used; the authenticated guard determines the participant identity.
- The service first scopes the exchange to the buyer or merchant account, then resolves its dispute and evidence.
- An unrelated account receives the existing not-found behavior; unauthenticated requests remain unauthorized.
- The export does not return buyer/merchant database IDs, internal actor IDs, raw event payloads, private storage disk/path, uploaded file bytes or payment references.
- Event and message actor IDs are converted to broad actor types such as `authenticated_user`, `merchant_account`, `platform_administrator` or `system`.
- Original uploaded evidence remains accessible only through existing participant-scoped routes that verify the stored SHA-256 before streaming.

## Consequences

### Positive

- Exchange participants can retain a portable snapshot of the evidence and decision trail.
- The manifest digest helps detect accidental alteration after export.
- Existing evidence, review and verifier records remain the source of truth; the export is a read-only projection.
- Buyer and merchant receive the same underlying snapshot and digest, improving the ability to compare copies.

### Trade-offs and non-goals

- The digest detects changes to the exported snapshot; it is not a digital signature, proof of authorship, external timestamp, legal certification or proof that the underlying claims are true.
- The manifest covers the records visible at generation time. Later events produce a new snapshot and digest; prior exports are not revoked.
- The export does not include private file bytes, provider payment credentials, payment references, wallet ledgers or settlement evidence.
- It does not create a new adjudication authority or change the status of an exchange or dispute.
- Canonical hashing depends on preserving JSON object key order and using the documented serialization flags when verifying the digest.
