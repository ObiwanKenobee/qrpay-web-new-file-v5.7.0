# Sanctum Scan — Buyer Identity Review API

**Milestone:** 015  
**Status:** Implemented on PR #16; pending review and executable CI validation.

All paths below are relative to the configured admin prefix (normally `/admin`). Every route is inside the existing admin authentication, role, application-mode, and two-factor middleware group.

## List review history

`GET /sanctum/identity-checks?user_id={userId}`

Returns the most recent review records, paginated in descending record order. `user_id` is optional. Responses are administrative and must not be exposed through merchant or public APIs.

Each record includes the account identifier, username, review method, internal reference, decision, summary, reviewer identifier, and timestamps.

## Record a review

`POST /sanctum/identity-checks/{userId}`

Example request:

```json
{
  "method": "national_id_review",
  "reference": "CASE-NKR-IDENTITY-001",
  "review_status": "verified",
  "summary": "The authorised administrator reviewed the identity evidence and confirmed the account holder."
}
```

Allowed methods: `national_id_review`, `passport_review`, `utility_bill_review`, `in_person_verification`, `third_party_kyc`, `other`.

Allowed outcomes: `verified`, `rejected`, `unable_to_verify`.

The `reference` field is an internal case/review reference only. Never submit or store a government identification number, raw document, document image, biometric template, password, or other authentication secret in this field or summary.

The reviewer ID and review timestamps are assigned by the server. They are not accepted from the request body.

Every review creates a new immutable record. A later non-verified result clears the current eligibility projection on the user row; it does not alter previous reviews.

## Read and update the gate

- `GET /sanctum/identity-checks/gate`
- `PUT /sanctum/identity-checks/gate`

Example update:

```json
{
  "enabled": true
}
```

The gate is disabled by default. Only an active administrator who passes the enclosing admin middleware can change it. Gate changes are logged with the authenticated administrator ID.

## Buyer acceptance behavior

When the gate is enabled, the buyer's existing acceptance route:

`POST /api/user/sanctum/exchanges/{exchangeId}/accept`

returns HTTP `403` with error code `buyer_identity_unverified` when the buyer has no current verified identity-review projection.

A blocked acceptance does not change the exchange status or append an acceptance event. The merchant can still create the offer and is not told the buyer's identity-review state. After an authorised administrator records a verified review, the buyer may retry acceptance.

## Operational limitations

This workflow is a manual platform review, not a biometric or automated KYC service. It does not establish that separate user and merchant accounts belong to different people, and it does not by itself establish AML compliance or legal identity proof. Operators must define a lawful review process, access controls, retention, and redress before enabling the gate in production.

No wallet, payment reference, or settlement behavior is changed by this milestone.
