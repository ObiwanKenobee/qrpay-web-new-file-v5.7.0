# ADR 0020 — Participant-Scoped Provenance Export

**Status:** Implemented  
**Date:** 2026-11-17  
**Scope:** Read-only provenance snapshot for a Sanctum Scan exchange dispute

## Context

M005–M011 introduced exchange records, bilateral disputes, private dispute
evidence, platform review, independent verifier attestations, versioned
attestation revisions, appeals and reassessments. Participants need a portable
view of this connected record without exposing private storage details or
internal actor identifiers.

The implementation is in
`SanctumScanDisputeEvidenceController::provenanceExport` and
`DisputeEvidenceManager::provenanceContextForParticipant`, with the evidence
projection supplied by `listForParticipant`. Buyer and merchant API routes
expose the same manifest contract. The exchange is scoped to the authenticated
participant's buyer or merchant account before the dispute and evidence are
loaded.

## Decision

Provide a read-only JSON provenance manifest to either authenticated
participant of an exchange with a dispute.

Buyer endpoint:

```http
GET /api/user/sanctum/exchanges/{exchangeId}/dispute/evidence/provenance
```

Merchant endpoint:

```http
GET /merchant-api/merchant/sanctum/exchanges/{exchangeId}/dispute/evidence/provenance
```

The authenticated guard determines the participant identity. The caller cannot
supply or override the buyer/merchant actor ID in the request. An unrelated
account cannot use the endpoint to discover another exchange's dispute;
unauthenticated callers remain unauthorized.

The exported `data` object contains:

- an exchange snapshot: exchange ID/state, resource snapshot, agreed quantity,
  unit, amount, currency, terms and lifecycle timestamps;
- a dispute snapshot: dispute ID, category, state, resolution summary,
  lifecycle timestamps and participant messages;
- evidence presentations: original file name, MIME type, size, SHA-256,
  caption, submitting party role, platform review, independent attestations,
  versioned revisions, appeals, decisions and linked reassessments;
- an exchange event timeline with event ID/type, description, verification
  status, generalized actor type and recorded time.

The manifest envelope contains the format
`sanctum-scan-participant-provenance`, schema version `1.0.0`, generation
timestamp, hash algorithm `SHA-256`, hash scope `data`, and
`content_sha256`.

The digest is SHA-256 over the serialized `data` object only. Serialization
uses compact UTF-8 JSON with unescaped slashes and Unicode,
`JSON_INVALID_UTF8_SUBSTITUTE`, `JSON_PRESERVE_ZERO_FRACTION` and
`JSON_THROW_ON_ERROR`. The `generated_at` timestamp is outside the hashed
data, so identical data snapshots produce the same digest.

## Authorization and privacy boundary

The export excludes buyer/merchant account IDs, internal actor IDs, raw event
payloads, storage disk/path values, uploaded file bytes and dedicated
structured payment-reference fields. Event and message actors are generalized
to broad actor types such as `authenticated_user`, `merchant_account`,
`platform_administrator` and `system`.

The exporter deliberately preserves participant-authored content. Exchange
terms, dispute message bodies, resolution summaries, evidence captions and
original file names are included as recorded. They may themselves contain
payment references, contact details or other sensitive information; the
exporter does not redact or rewrite those statements. Treat the manifest as
private dispute data and review it before sharing.

Original evidence files remain available only through the existing
participant-scoped download flow, which checks the stored file's SHA-256 before
streaming. The export does not make evidence files public.

## Consequences and limitations

- Participants can retain and compare a portable snapshot of the connected
  dispute and evidence history.
- The digest detects changes to the exported data after generation. It is not
  a digital signature, proof of authorship, trusted timestamp, legal
  certification or proof that any underlying claim is true.
- The export is a projection of the records visible when each query runs.
  The current controller obtains provenance context and evidence through
  separate service reads rather than one shared database snapshot. Concurrent
  writes can therefore produce a mixed-time export. Consistent snapshot
  semantics are a tracked follow-up (M026), not a guarantee of this decision.
- Later events produce a later snapshot and digest; prior exports are not
  revoked.
- The export does not include payment-provider credentials, wallet ledgers,
  settlement evidence or private file bytes. Free-text fields remain as
  submitted, as described above.
- It does not create an adjudication authority or change exchange, dispute,
  payment, wallet, refund or settlement state.

## Implementation evidence

- Controller: `app/Http/Controllers/Api/SanctumScanDisputeEvidenceController.php`
- Participant scoping and snapshot projection:
  `app/Services/SanctumScan/DisputeEvidenceManager.php`
- Buyer/merchant route registration:
  `routes/api/api.php`, `routes/api/merchant_api.php`
- Lifecycle test:
  `EconomicExchangeLifecycleTest::test_exchange_participants_can_export_a_hashed_provenance_manifest_without_private_storage_or_actor_identifiers`
- API contract: `docs/api/sanctum-scan-provenance-export.md`

The implementation is present on `main`; GitHub Actions verification remains
subject to the hosted-runner issue tracked in repository issue #21.
