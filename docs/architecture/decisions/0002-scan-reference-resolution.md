# ADR 0002: Opaque, Read-Only Scan Reference Resolution

- **Status:** Accepted for the initial protocol slice
- **Date:** 2026-11-13
- **Depends on:** ADR 0001

## Context

The application already has QR records for users, merchants and agents, plus a merchant payment-order API. Those records and payment flows are not a general-purpose protocol for resolving products, assets, agreements, services or community projects.

The first new protocol slice must not move funds or expose internal database identifiers.

## Decision

Introduce a versioned Sanctum Scan reference with these properties:

- Reference format: \`snc_\` followed by 32 cryptographically random bytes encoded as unpadded base64url.
- Persist only the SHA-256 hash of the full reference.
- Bind a reference to an allowlisted resource type and an internal resource ID.
- Store intentionally public display metadata separately from private resource data.
- Support active/revoked status and optional expiry.
- Resolve references through a read-only API endpoint.
- Return a uniform not-found response for malformed, unknown, expired and revoked references.
- Apply an endpoint-specific IP-based rate limit in addition to the existing API middleware.
- Never return internal resource IDs, stored hashes or the reference token in the resolution response.

## Endpoint

\`GET /api/sanctum/v1/scan/{reference}\`

Successful response:

\`\`\`json
{
  "schema_version": "1.0",
  "type": "scan_resolution",
  "data": {
    "resource": {
      "type": "merchant",
      "display_name": "Example Merchant",
      "description": "Public description shown to a scanner."
    },
    "expires_at": null
  }
}
\`\`\`

Unavailable response:

\`\`\`json
{
  "error": {
    "code": "scan_reference_unavailable",
    "message": "This scan reference is invalid, expired, or unavailable."
  }
}
\`\`\`

## Issuance and revocation

The initial issuance interface is an internal Laravel service, not a public unauthenticated route. A future authenticated merchant or institutional workflow must add explicit authorization, audit attribution, consent and appropriate rate limits before exposing issuance through HTTP.

The plaintext token is returned to the issuer once and must be delivered to the QR-generation workflow securely. The database stores only its SHA-256 hash.

Revocation is performed through the internal service and is immediately respected by resolution.

## Security boundaries

Resolving a reference is not:

- proof of the scanner's identity;
- permission to access a private resource;
- authorization to initiate or approve a payment;
- proof of payment authorization or settlement;
- proof that goods or services were delivered.

Payment execution, settlement and delivery verification remain separate, explicitly authorized workflows.

Public display name and description fields must not contain personal, financial, credential or otherwise sensitive information.

## Consequences

### Positive

- Provides one resolver for multiple economic object types.
- Avoids exposing internal database IDs.
- Limits damage from a database read by storing only hashed tokens.
- Makes invalid, expired and revoked references indistinguishable to callers.
- Establishes a testable protocol boundary before payment integration.

### Trade-offs

- The initial resource binding uses an internal type/ID pair and does not yet provide portable cross-installation identifiers.
- The first endpoint returns only a deliberately small public description.
- QR issuance is internal until an authorized creation workflow is designed.
- SHA-256 is appropriate here because the reference has high cryptographic entropy; this is not a password-hashing use case.
