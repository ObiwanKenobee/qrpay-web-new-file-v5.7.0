# Sanctum Scan Economic Resource API

This document describes the first merchant-owned product and agricultural batch registry.

All merchant endpoints require a valid access token for the existing `merchant_api` Passport guard. The resource owner is always derived from the authenticated merchant, never from request input.

## Base paths

Merchant API:

```text
/merchant-api/merchant/sanctum/resources
```

Public human scan page:

```text
/scan/{reference}
/```

Machine-readable resolver:

```text
/api/sanctum/v1/scan/{reference}
/```

## List resources

`GET /merchant-api/merchant/sanctum/resources`

Returns up to 100 most recently created resources belonging to the authenticated merchant. It does not return plaintext scan tokens or token hashes.

## Create a product

`POST /merchant-api/merchant/sanctum/resources`

Example request:

```json
{
  "resource_type": "product",
  "display_name": "Organic compost",
  "description": "Compost prepared for smallholder farms.",
  "category": "Farm inputs",
  "reference_code": "COMP-2026-01",
  "expires_in_days": 90
}
```

## Create an agricultural batch

`POST /merchant-api/merchant/sanctum/resources`

Example request:

```json
{
  "resource_type": "agricultural_batch",
  "display_name": "Shangi potato batch",
  "description": "Freshly harvested potatoes prepared for local trade.",
  "category": "Potatoes",
  "reference_code": "RNG-SHG-2026-004",
  "variety": "Shangi",
  "origin_label": "Rongai, Nakuru County",
  "occurred_on": "2026-11-10",
  "quantity": 250,
  "unit": "kg",
  "expires_in_days": 30
}
```

### Fields

| Field | Requirement | Meaning |
|---|---|---|
| `resource_type` | Required | `product` or `agricultural_batch` |
| `display_name` | Required | Public title, up to 140 characters |
| `description` | Optional | Public description, up to 2,000 characters |
| `category` | Required | Product category or crop category |
| `reference_code` | Optional | Merchant reference; unique per merchant and resource type |
| `variety` | Required for agricultural batches | Crop or product variety |
| `origin_label` | Optional | Merchant-declared origin label |
| `occurred_on` | Optional | Harvest or production date; cannot be in the future |
| `quantity` | Optional | Merchant-private quantity |
| `unit` | Optional | Merchant-private unit |
| `expires_in_days` | Optional | Scan reference lifetime, 1–365 days; omitted means no expiry |

The create response includes:
- Resource ID for the authenticated merchant's own management.
- Public `scan_url`.
- SVG QR image as a `data:image/svg+xml;base64,...` URI.
- Resource status and reference expiry.
- `provenance_status: merchant_declared`.

The QR payload contains only the opaque public scan URL.

## Rotate a resource QR

`POST /merchant-api/merchant/sanctum/resources/{resourceId}/scan-reference`

Creates a new reference for a published resource and revokes all currently active references for that resource. The resource's configured expiry window is preserved. A lost or exposed QR should be rotated.

## Archive a resource

`DELETE /merchant-api/merchant/sanctum/resources/{resourceId}`

Archives the merchant-owned resource and revokes its active scan references. Archiving remains available to the authenticated owner so public information can be withdrawn even when the merchant's KYC status later changes.

## Provenance interpretation

The first registry records:
- Resource registration statement.
- Optional merchant-declared origin statement.
- Recording actor and timestamp.
- Explicit `merchant_declared` verification status.

A merchant declaration is not an independent verification. The public page states that origin, ownership, quality, certification and delivery are not independently proven by this record.

Quantity and unit are stored for the merchant's own records but are not included in public scan responses.

## Security boundaries

- No public endpoint can create a resource or issue a reference.
- Merchant ownership is derived from the authenticated guard.
- List, rotate and archive operations are owner-scoped.
- QR tokens are opaque; only token hashes are persisted.
- Public display fields are escaped by the Blade view.
- Payment authorization, fund movement, delivery acceptance, legal title and independent certification are outside this API.
