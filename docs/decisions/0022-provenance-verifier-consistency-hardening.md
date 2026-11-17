# ADR 0022 — Provenance Verifier Consistency Hardening

**Status:** Implemented  
**Date:** 2026-11-17  
**Depends on:** ADR 0020 (export contract), ADR 0021 (offline verifier)

## Context

ADR 0021 verifies the export digest and validates that the saved manifest has
the supported shape. A digest match alone does not establish that all nested
records form a coherent history: a document can be changed and re-hashed while
retaining contradictory current projections or malformed cross-record links.

The first verifier validated the outer digest and core evidence relationships,
but further checks were required for completed platform reviews, attestation
current-state projections, reassessment version types and both directions of
reassessment links.

## Decision

Harden schema-1.0.0 verification without changing the export format, API
endpoints, database schema or financial workflows.

The verifier must:

1. Require a recognized platform review state: `pending`, `verified`,
   `rejected` or `unable_to_verify`. Pending reviews must not claim a
   completed-review digest. Completed reviews must carry a valid digest equal
   to the evidence digest, plus non-empty method, summary and review timestamp.
2. Validate the original attestation snapshot fields and the revision snapshot
   fields needed to explain the effective current statement.
3. Recompute the expected current attestation projection from the original
   attestation when there are no revisions, or from the final revision when
   revisions exist. Status, version, scope, method, outcome, basis summary and
   recorded timestamp must agree.
4. Validate sequential revision versions, predecessor links, supported
   correction/revocation actions and evidence digest binding. Revocation is
   terminal within a revision chain.
5. Validate reassessment identifiers and prior-version values as positive
   integers; require version 1 to have no prior revision ID; require later
   versions to identify the exact revision in the source history; restrict
   prior status to supported values; and reject self-references.
6. Validate both sides of reassessment links. A source-side link must match the
   target attestation's reverse link, and a reverse link must identify an
   existing source-side link with the same evidence digest.
7. Require non-empty event/message type, actor and timestamp fields and reject
   duplicate event/message identifiers within their respective collections.

Malformed or internally inconsistent exports fail closed with validation
errors. The verifier remains read-only and offline.

## Consequences and limitations

- Re-serializing a contradictory document and calculating a new outer digest
  does not bypass the structural consistency checks.
- The verifier validates declared relationships and payload integrity; it
  does not establish the factual truth of participant statements, platform
  review decisions or independent attestations.
- No cryptographic signing, trusted timestamp, exporter authentication,
  source-file byte inspection or factual adjudication is introduced.
- The export schema and API contract remain unchanged.
- This decision does not change exchange, dispute, payment, wallet, refund or
  settlement state.

## Implementation evidence

- Implementation: `app/Services/SanctumScan/ProvenanceManifestVerifier.php`
- Unit coverage: `tests/Unit/SanctumScan/ProvenanceManifestVerifierTest.php`
- Export compatibility: `EconomicExchangeLifecycleTest` and the M012
  participant export scenario.

The implementation is present on `main`; GitHub Actions verification remains
subject to the hosted-runner issue tracked in repository issue #21.
