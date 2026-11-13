# ADR 0003: Merchant-Owned Scan References and QR Profiles

- **Status:** Accepted for the merchant identity slice
- **Date:** 2026-11-13
- **Depends on:** ADR 0001 and ADR 0002

## Context

The application already has a legacy `merchant_qr_codes` table and a login-time QR initializer. That legacy QR value is populated with a merchant email address or mobile number. Reusing it for Sanctum Scan would perpetuate personal contact information in QR payloads.

Sanctum Scan needs a merchant-owned public identity reference that resolves through the opaque, hash-backed resolver introduced in milestone 002.

## Decision

1. Use the authenticated `merchant_api` guard to identify the merchant. Never accept `merchant_id`, resource type or display identity from the request body.
2. Expose authenticated list, issue and revoke operations under the merchant API.
3. Require the existing merchant account status and two-factor middleware for merchant reference operations. Reference creation/revocation also passes the application's configured merchant KYC middleware.
4. Issue a `merchant` scan reference bound to the authenticated merchant's internal ID.
5. Record the issuing actor guard and ID, and the revoking actor guard and ID, as audit metadata.
6. Generate an SVG QR code containing only the public scan URL. Do not encode email, phone number, wallet identifiers, credentials or private profile fields.
7. Resolve a merchant reference against the current merchant record. Inactive or soft-deleted merchants do not resolve.
8. Return only the current business name and public handle. Do not expose email, mobile, address, KYC data, balances or transactions.
9. Keep the scan reference as a public profile locator, not as proof of identity, payment authorization, settlement or delivery.
10. Leave the legacy `merchant_qr_codes` behavior unchanged until its separate compatibility and privacy implications have been reviewed.

## API

Authenticated merchant endpoints:

- `GET /merchant-api/merchant/sanctum/scan-references`
- `POST /merchant-api/merchant/sanctum/scan-references`
- `DELETE /merchant-api/merchant/sanctum/scan-references/{referenceId}`

The create endpoint accepts only an optional `expires_in_days` value between 1 and 365. Omission creates a non-expiring reference that can be revoked by its owner.

The create response returns the public scan URL and an SVG data URI once. Listing never returns plaintext references or hashes. A lost QR should be replaced by issuing a new reference and revoking the old one.

Public resolution remains:

`GET /api/sanctum/v1/scan/{reference}`

## Security and privacy

- The merchant ID is sourced from the authenticated Passport merchant guard.
- List and revoke queries are scoped to the current merchant and resource type.
- Cross-merchant attempts to revoke a reference return not found.
- The issuer stores only the token hash.
- Audit records identify the authenticated merchant actor.
- QR data contains only the scan URL.
- Public resolution excludes contact, address, KYC, wallet and transaction data.
- Inactive and deleted merchant records are not publicly resolvable.
- Payment actions remain a separate workflow.

## Consequences

### Positive

- Merchants can issue and revoke their own public scan identity.
- The QR payload no longer exposes an email address or phone number.
- The existing public resolver is reused.
- Current business names are reflected without reissuing the reference.
- Merchant ownership and lifecycle actions have explicit audit attribution.

### Trade-offs

- A lost QR cannot be reconstructed from the hash; a replacement must be issued.
- Multiple active references may exist for a merchant until revoked or expired.
- QR SVG generation depends on the existing `simplesoftwareio/simple-qrcode` dependency.
- This first merchant profile exposes only a minimal public identity; a richer profile requires explicit consent and field-by-field privacy review.
