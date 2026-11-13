# Sanctum Scan: Private Dispute Evidence API

Milestone 007 adds private evidence attachments to an open dispute and a separate platform-admin review workflow.

## Participant endpoints

Buyer prefix: `/api/user`

Merchant prefix: `/merchant-api/merchant`

Both prefixes expose:

| Method | Path suffix | Purpose |
|---|---|---|
| GET | `/sanctum/exchanges/{exchangeId}/dispute/evidence` | List evidence for the participant's dispute |
| POST | `/sanctum/exchanges/{exchangeId}/dispute/evidence` | Attach a file to an open dispute |
| GET | `/sanctum/exchanges/{exchangeId}/dispute/evidence/{evidenceId}/download` | Download a participant-scoped evidence file |

These routes use the existing buyer or merchant authentication, account-status and 2FA middleware. All are inside the existing PIN-setup and rate-limit guard for dispute mutations/evidence access.

## Upload

Use `multipart/form-data`:

```http
POST /api/user/sanctum/exchanges/42/dispute/evidence
Authorization: Bearer <user-token>
Content-Type: multipart/form-data
```

Fields:

- `evidence_type`: `proof_of_delivery`, `payment_receipt`, `quantity_record`, `quality_record`, `correspondence` or `other`.
- `file`: required PDF, JPEG or PNG file, maximum 10 MiB.
- `caption`: optional participant-supplied description, maximum 500 characters.

The same endpoint shape is available under the merchant prefix. Uploads are accepted only while the dispute is `open`. Repeating an identical file (same SHA-256 digest) within the same dispute is rejected.

## Evidence response

The API returns the evidence ID, type, sanitized original filename, detected MIME type, byte size, SHA-256 digest, caption, submitting side, submission timestamp and verification status.

A new record has verification status `pending`. The storage disk and internal path are never returned.

## Download and integrity

Downloads are available only to the authenticated buyer or merchant on the exchange. A request for another participant's evidence returns not found. Files are streamed as private attachments with `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.

The server recomputes SHA-256 before download. A missing or modified file is not served and produces `evidence_integrity_failure`.

## Platform administrator review

The review endpoints are under the application's configured admin route prefix and existing authenticated admin middleware. Route names are:

- `admin.sanctum.evidence.index` — list pending evidence (50 per page).
- `admin.sanctum.evidence.download` — download evidence after the same integrity check.
- `admin.sanctum.evidence.review` — record a review decision.

Review request example:

```json
{
  "review_status": "verified",
  "review_method": "document_review",
  "review_summary": "The document structure and reference information were reviewed against the submitted dispute context."
}
```

Allowed review statuses: `verified`, `rejected`, `unable_to_verify`.

Allowed methods: `document_review`, `source_cross_check`, `field_inspection`, `other`.

A written summary is required (20–2,000 characters). Reviewer identity is derived from the authenticated admin session, never from a request field. The review binds to the evidence SHA-256 digest and is immutable. Only one review decision can be recorded per evidence item in this milestone.

## Trust boundaries

A `verified` review is a platform administrator's recorded conclusion about the submitted artifact and the method stated in the review. It is not third-party certification, proof of physical delivery, proof of product quality or proof that a payment settled. Participant declarations remain participant declarations. No wallet, transaction, payment-order or settlement state is changed.

Evidence is kept on the application's private `local` storage disk and is not included in public scan responses. Production use still requires operational controls for protected backups, malware scanning, access monitoring, retention and durable storage.
