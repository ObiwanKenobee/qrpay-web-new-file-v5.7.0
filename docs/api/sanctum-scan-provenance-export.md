# Sanctum Scan participant provenance export

## Purpose

A read-only JSON export of the connected exchange and dispute record for one of its two authenticated participants. It provides a portable evidence index and an integrity digest over the exported `data` object.

## Endpoints

Buyer:

```http
GET /api/user/sanctum/exchanges/{exchangeId}/dispute/evidence/provenance
```

Merchant:

```http
GET /merchant-api/merchant/sanctum/exchanges/{exchangeId}/dispute/evidence/provenance
```

Both endpoints use the existing authenticated user or merchant API session. A caller must be the buyer or merchant recorded on the exchange. An unrelated account cannot use the endpoint to discover another exchange's dispute. Requests are rate-limited.

## Export contents

The response contains:

- `manifest`: format, schema version, generated timestamp, hash algorithm, hash scope and content SHA-256.
- `data.exchange`: a bounded exchange snapshot, including the resource snapshot, agreed terms and lifecycle state.
- `data.dispute`: dispute category/state, resolution summary, timestamps and participant messages.
- `data.evidence`: the existing participant evidence representation, including file metadata and digest, platform review, institutional attestations, revisions, appeals and linked reassessments.
- `data.events`: a filtered exchange timeline.

The export intentionally omits raw event payloads, payment references, buyer/merchant database IDs, internal actor IDs, private storage disk/path and uploaded file bytes. The original files remain available through the existing participant-scoped download endpoints, which verify the file digest before streaming.

## Example response shape

```json
{
  "manifest": {
    "format": "sanctum-scan-participant-provenance",
    "schema_version": "1.0.0",
    "generated_at": "2026-11-13T18:00:00+03:00",
    "hash_algorithm": "SHA-256",
    "hash_scope": "data",
    "content_sha256": "<64-character-hex-digest>"
  },
  "data": {
    "exchange": {},
    "dispute": {},
    "evidence": [],
    "events": []
  }
}
```

## Verifying the digest

The digest covers only the `data` object, not the `manifest` or `generated_at` field. Preserve the received JSON object key order and serialize the decoded `data` object as compact UTF-8 JSON with the same flags:

```php
$canonicalData = json_encode(
    $export['data'],
    JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR
);

$actualDigest = hash('sha256', $canonicalData);

if (! hash_equals($export['manifest']['content_sha256'], $actualDigest)) {
    throw new RuntimeException('Provenance export digest mismatch.');
}
```

A matching digest means the exported `data` object matches the bytes used to compute the manifest at export time. It does not prove that a participant statement or verifier conclusion is factually correct, and it is not a digital signature or trusted timestamp.

## State and side effects

The endpoint performs no state transition and creates no new evidence or event record. It does not modify the exchange, dispute, payment, wallet, refund or settlement state. A later state change produces a later export with a different data digest.

## Versioning

The initial export schema is `1.0.0`. Consumers should check `manifest.schema_version` and treat unknown fields as extensible. A breaking shape or semantic change requires a schema major-version increment.
