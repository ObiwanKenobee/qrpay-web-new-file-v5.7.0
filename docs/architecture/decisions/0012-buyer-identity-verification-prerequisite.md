# ADR 0012: Buyer identity verification prerequisite

- Status: Accepted
- Date: 2026-11-14
- Depends on: ADR 0005 — Economic exchange and transaction evidence

## Context

ADR 0009 and ADR 0010 explicitly note that the platform cannot confirm whether a user account and a merchant account belong to the same real-world person. Before payment settlement carries financial weight, a minimum mitigation must be in place and the limitation must be documented.

The existing buyer eligibility gate (M005) requires only that the buyer account has verified email and SMS. This is insufficient for exchanges that will eventually trigger wallet transfers.

## Decision

### User identity stamp

Add `identity_verified_at` (timestamp, nullable) and `identity_verification_method` (string, nullable) to the `users` table. These columns are set by the application when an administrator records a `verified` identity check, and cleared when a subsequent `rejected` or `unable_to_verify` check is recorded.

### Admin-recorded identity checks

Administrators record identity checks through a new `sanctum_scan_user_identity_checks` table. Each row is immutable and captures: user, method, optional reference, review status (`verified` | `rejected` | `unable_to_verify`), summary, reviewing admin and timestamp.

Supported methods: `national_id_review`, `passport_review`, `utility_bill_review`, `in_person_verification`, `third_party_kyc`, `other`.

A platform administrator's `verified` review means the administrator recorded a check against the stated document or source. The application does not automatically query government identity registries. It does not assert legal identity outside the stated method and summary.

### Feature flag

A `sanctum_exchange_identity_gate` boolean column on `basic_settings` (default `false`) controls whether the gate is enforced. When `false`, the existing email and SMS verification requirements remain the only buyer eligibility conditions. When `true`, a buyer without `identity_verified_at` receives a `buyer_identity_unverified` 422 response on offer acceptance.

This allows the gate to be enabled progressively per deployment without a code change.

### Admin routes

```
GET  admin/sanctum/users/{userId}/identity-checks
POST admin/sanctum/users/{userId}/identity-checks
```

## Consequences and limits

This is an administrator-recorded due-diligence record, not biometric verification, government registry integration or legal identity proofing. The cross-guard identity limitation (a user and merchant account may belong to the same person) is mitigated but not closed by this milestone.

Before production use with financial obligations, the project needs: independent identity proofing, a documented identity policy, data protection review for identity document references, and specialist legal review of applicable KYC obligations.

No biometric capture, no third-party KYC API integration and no document upload are introduced in this milestone.
