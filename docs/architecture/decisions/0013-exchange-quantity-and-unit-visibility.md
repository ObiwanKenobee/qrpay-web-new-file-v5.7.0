# ADR 0013: Exchange quantity and unit visibility

- Status: Accepted
- Date: 2026-11-14
- Depends on: ADR 0004 — Public scan and resource registry, ADR 0005 — Economic exchange and transaction evidence

## Context

ADR 0004 deliberately kept quantity and unit private to the merchant in the first public registry release. The exchange record (ADR 0005) already snapshots negotiated quantity, unit and amount into `sanctum_scan_exchanges` and both buyer and merchant API `present()` methods already return these fields.

The remaining gap is the public scan page and the resolver that builds its data. Buyers and the public cannot see declared quantity from a QR scan, which limits the usefulness of the agricultural batch record as a market-facing document.

## Decision

- Remove the private-quantity restriction from `ScanReferenceResolver::economicResourceDocument()`.
- Expose `quantity` and `unit` in the `details` block for agricultural batches and any resource that has both fields set.
- Display declared quantity and unit on the public scan page in the resource details grid.
- Quantity and unit remain merchant-declared. The label "Declared quantity" is used on the public page to make the provenance explicit.
- No change to the exchange API responses — quantity and unit are already returned there.

## Consequences

The public scan page now shows the declared batch size alongside origin, variety and harvest date. This supports the agricultural market intelligence use case described in the README without exposing any buyer, transaction or pricing information.

Quantity is a merchant declaration. It is not independently verified weight, volume or count. The existing provenance disclaimer on the scan page covers this.
