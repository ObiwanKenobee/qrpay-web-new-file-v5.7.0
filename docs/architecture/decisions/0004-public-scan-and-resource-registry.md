# ADR 0004: Public Scan Experience and Economic Resource Registry

- **Status:** Accepted for the first public resource registry
- **Date:** 2026-11-13
- **Depends on:** ADR 0001, ADR 0002 and ADR 0003

## Context

Milestone 002 established a machine-readable opaque reference resolver. Milestone 003 established merchant-owned references. A QR that opens only a JSON API is not yet a usable public scanning experience, and the existing application has no suitable product/agricultural batch registry.

## Decision

1. Add a public mobile-first web route at `/scan/{reference}` while preserving the JSON API resolver.
2. Share resolution logic between the public web page and machine API through `ScanReferenceResolver`.
3. Introduce a merchant-owned economic resource registry for `product` and `agricultural_batch`.
4. Require the authenticated merchant guard to create, list, rotate and archive resources. Resource ownership comes from the authenticated principal, never request input.
5. Resource QR payloads contain only an opaque public scan URL.
6. Public resource documents include display name, description, category, merchant public handle, and carefully selected batch metadata.
7. Quantity and unit are retained for merchant-side operations but are not published by this first public profile. The QR expiry window is stored with the resource so reference rotation preserves that policy.
8. Record provenance statements as append-only events with explicit `merchant_declared` status.
9. Never label merchant-declared origin or product data as independently verified.
10. Archiving a resource revokes active scan references. Rotating a scan reference revokes all active references for that resource before issuing a new one.
11. Keep payment authorization, money movement, title/ownership proofs and independent quality certification outside this milestone.

## Resource fields

Common fields:
- resource type
- display name
- description
- category
- optional reference code
- merchant owner
- publication status

Agricultural batch fields:
- variety
- origin label
- harvest/production date
- quantity and unit (merchant-private in this release)

Provenance events capture:
- event type
- merchant statement
- optional location label
- optional event date
- verification status
- recording actor and timestamp

## API

Authenticated merchant endpoints:

- `GET /merchant-api/merchant/sanctum/resources`
- `POST /merchant-api/merchant/sanctum/resources`
- `POST /merchant-api/merchant/sanctum/resources/{resourceId}/scan-reference`
- `DELETE /merchant-api/merchant/sanctum/resources/{resourceId}`

Public surfaces:

- `GET /scan/{reference}` for human scanning.
- `GET /api/sanctum/v1/scan/{reference}` for machine-readable resolution.

## Consequences

### Positive

- QR scans open a usable, mobile-first public experience.
- Products and agricultural batches have durable merchant-owned records.
- Provenance statements carry explicit origin and verification status.
- Merchant-private quantities are not exposed publicly.
- API consumers and human scanners share the same resolution logic.

### Trade-offs

- Initial resource creation is merchant-managed; there is no public supplier discovery or marketplace.
- Provenance is merchant-declared, not independently verified.
- Public scan pages are informational only and do not collect payments.
- The registry does not yet model transfers between owners, buyer acceptance, or custody handoffs.
