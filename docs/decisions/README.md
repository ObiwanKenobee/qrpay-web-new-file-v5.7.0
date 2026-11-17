# Architecture Decision Records

This directory is the canonical location for new architecture decision records (ADRs).

## Relationship to `docs/architecture/decisions/`

ADRs 0001–0013 and 0015 predate this directory and remain in
`docs/architecture/decisions/`. They are **not moved** here, because existing
milestone documents and pull requests link to their current paths. New records
are written to this directory, and this index is the single place that lists
both locations.

## Numbering

The next available number is **0019**. Number 0014 was never written on this
branch; the gap is intentional and must not be reused or backfilled with a
record that was never decided. Numbers are never recycled.

## Index

| ADR | Title | Theme | Location |
| --- | --- | --- | --- |
| 0001 | Incremental adoption | Delivery strategy | `docs/architecture/decisions/0001-incremental-adoption.md` |
| 0002 | Scan reference resolution | Sanctum Scan | `docs/architecture/decisions/0002-scan-reference-resolution.md` |
| 0003 | Merchant scan identity | Sanctum Scan | `docs/architecture/decisions/0003-merchant-scan-identity.md` |
| 0004 | Public scan and resource registry | Sanctum Scan | `docs/architecture/decisions/0004-public-scan-and-resource-registry.md` |
| 0005 | Economic exchange and transaction evidence | Sanctum Scan | `docs/architecture/decisions/0005-economic-exchange-and-transaction-evidence.md` |
| 0006 | Bilateral dispute resolution | Sanctum Scan | `docs/architecture/decisions/0006-bilateral-dispute-resolution.md` |
| 0007 | Private dispute evidence and platform review | Sanctum Scan | `docs/architecture/decisions/0007-private-dispute-evidence-and-platform-review.md` |
| 0008 | Independent verifier mandates and attestations | Sanctum Scan | `docs/architecture/decisions/0008-independent-verifier-mandates-and-attestations.md` |
| 0009 | Verifier credentials, conflicts and appeals | Sanctum Scan | `docs/architecture/decisions/0009-verifier-credentials-conflicts-and-appeals.md` |
| 0010 | Versioned attestation revisions | Sanctum Scan | `docs/architecture/decisions/0010-versioned-attestation-revisions.md` |
| 0011 | Independent attestation reassessment | Sanctum Scan | `docs/architecture/decisions/0011-independent-attestation-reassessment.md` |
| 0012 | Buyer identity verification prerequisite | Sanctum Scan | `docs/architecture/decisions/0012-buyer-identity-verification-prerequisite.md` |
| 0013 | Exchange quantity and unit visibility | Sanctum Scan | `docs/architecture/decisions/0013-exchange-quantity-and-unit-visibility.md` |
| 0014 | — (not written) | — | — |
| 0015 | Manual buyer identity review | Sanctum Scan | `docs/architecture/decisions/0015-manual-buyer-identity-review.md` |
| 0016 | Deterministic verification gates | Engineering process | `docs/decisions/0016-deterministic-verification-gates.md` |
| 0017 | Settlement slice and evidence-based milestones | Delivery strategy | `docs/decisions/0017-settlement-slice-and-evidence-based-milestones.md` |
| 0018 | Payment evidence versioning and reopened verification | Sanctum Scan | `docs/decisions/0018-payment-evidence-versioning.md` |

The **Status** of each record is authoritative in the record itself (Proposed,
Accepted, Implemented, Superseded, or Rejected). This index does not duplicate
status because a status copied into two files drifts.

## Required format for new records

Every new ADR contains, in order:

1. `# ADR NNNN — Title`
2. `**Status:**`
3. `**Date:**` (absolute, ISO 8601)
4. `## Context` — the measured problem, with repository evidence
5. `## Decision` — the rule that follows, stated normatively
6. `## Consequences and limitations` — what becomes true, what does not, and
   what remains unverified

Rules:

- An ADR records a decision, not a plan. Steps belong in
  `docs/engineering/` milestone plans.
- Claims about existing behaviour must cite a file path, route, migration, or
  executed command. Unverified assumptions are labelled as hypotheses.
- An ADR that changes a security, money, identity, or persistence invariant
  must state which tests enforce the new invariant and which verification gate
  (ADR 0016) fails if the invariant regresses.
- Superseding an ADR edits its **Status** to `Superseded by ADR NNNN`; records
  are never deleted.
