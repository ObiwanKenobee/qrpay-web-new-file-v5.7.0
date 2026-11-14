# Sanctum Scan — Engineering Roadmap

**Last reconciled:** 2026-11-14  
**Purpose:** Keep implemented work, branch state, and future scope consistent. This roadmap distinguishes code merged into `main` from code that exists only on a dependent feature branch.

## Repository state

| Milestone | Capability | Implementation state | Integration state |
|---|---|---|---|
| 002–009 | Scan references, resource registry, exchange/dispute lifecycle, private evidence, verifier institutions and governance | Implemented | On `main` |
| 010 | Versioned attestation corrections and protective withdrawal | Implemented | On `main` |
| 011 | Independent attestation reassessment | Implemented | On `main` |
| 012 | Participant-scoped provenance export | Implemented on `sanctum-scan/provenance-verifier-hardening-014` | Promotion to `main` pending |
| 013 | Offline provenance manifest verifier | Implemented on `sanctum-scan/provenance-verifier-hardening-014` | Promotion to `main` pending |
| 014 | Provenance verifier consistency hardening | Implemented on `sanctum-scan/provenance-verifier-hardening-014` | Promotion to `main` pending |
| 015 | Manual buyer identity review and optional acceptance gate | In progress on PR #16 | Not merged |
| 016 | Payment settlement state machine (no movement of funds) | Planned | Not started |
| 017 | Wallet settlement integration | Deferred until M016 and financial controls are validated | Not started |
| 018 | Agricultural batch traceability events | Planned | Not started |
| 019 | Cooperative and group exchange | Planned | Not started |
| 020 | Offline-aware transaction intent | Research and security design required | Not started |
| 021 | Public, anonymised economic intelligence snapshots | Planned | Not started |
| 022 | Developer SDK and open protocol package | Planned | Not started |

**Important branch distinction:** PRs #13–#15 were merged into their dependent feature branches. That does not mean those changes are present in `main`. The cumulative M012–M014 branch has diverged from the current mainline and requires a separately reviewed integration change. Do not advance M015 into a release claim until the provenance stack has been reconciled.

## Current engineering principle

The unit of progress is a trustworthy, reviewable capability—not a growing list of features. Preserve existing QR, payment, and wallet behavior unless a milestone explicitly changes it. Separate merchant declarations, participant statements, platform reviews, independent attestations, and verified outcomes. Keep personal evidence private and all governance decisions traceable.

## M012 — Participant-scoped provenance export

**ADR:** 0012  
**Status:** Implemented on the dependent provenance branch; integration pending.

The buyer and merchant can obtain a participant-scoped JSON provenance manifest for an exchange. The manifest contains the permitted event, evidence, review, attestation, revision, and reassessment history for that participant. Its digest is calculated over the canonical exported data. Private storage paths, raw files, payment credentials, and unrelated participant records are excluded.

## M013 — Offline provenance manifest verifier

**ADR:** 0013  
**Status:** Implemented on the dependent provenance branch; integration pending.

A local Artisan command validates an exported manifest without network or database access:

```bash
php artisan sanctum-scan:verify-provenance path/to/provenance-export.json
```

The verifier checks schema, digest, source-evidence binding, review completeness, revision sequencing, and reassessment links. A valid digest establishes internal consistency of the exported bytes; it does not prove the real-world truth of a claim, the identity of the issuer, trusted time, or the authenticity of source documents.

## M014 — Provenance verifier consistency hardening

**ADR:** 0014  
**Status:** Implemented on the dependent provenance branch; integration pending.

The offline verifier rejects malformed or incomplete platform-review digests, inconsistent current attestation projections, malformed revision references, invalid reverse reassessment references, and invalid or duplicate event identifiers. It remains an offline consistency verifier—not a digital-signature or factual-truth oracle.

## M015 — Manual buyer identity review and optional acceptance gate

**ADR:** 0015  
**Status:** In progress — PR #16.

### Objective

Introduce a narrow, auditable manual review process before a buyer accepts an exchange offer. This is a platform control, not an assertion that the system can reliably identify the same real-world person across the separate user and merchant authentication systems.

### Scope

- Store an append-only identity-review record containing the user, review method, internal case reference, decision, summary, reviewer identity, and timestamps.
- Keep the user's current eligibility projection on the user row for an efficient acceptance check.
- Permit authorised administrators to list review history, append a new review, and enable or disable the gate.
- Evaluate the gate inside the buyer's acceptance transaction. An unverified buyer receives a buyer-only error; the offer stays in the `offered` state.
- Never reveal identity-review state to the merchant when an offer is created or listed.
- A later `rejected` or `unable_to_verify` review clears the current verified projection but does not rewrite earlier reviews.

### Trust and privacy boundaries

- The gate is disabled by default and must be deliberately enabled by an authorised administrator.
- The reviewer identity comes from the authenticated admin session, never from request data.
- The optional reference field is for an internal case or review reference only. Do not store a national ID number, passport number, document image, biometric template, or raw identity document in this table.
- No biometric capture or third-party KYC integration is introduced.
- A manual review status is not a guarantee of AML compliance, uniqueness across user/merchant guards, or legal identity proof.
- Existing wallet balances, payment references, exchange settlement, and transaction processing are unchanged.

## M016 — Payment settlement state machine

Introduce a separate exchange settlement status and append-only transition events. Payment evidence remains unverified until an authorised review process records a decision. A `payment_verified` state is a recorded review result; it must not move money. A settled state may only be recorded when payment verification and buyer receipt conditions have both been satisfied.

**Non-goals:** no wallet debit/credit, no automatic M-Pesa or card settlement, and no external payment-rail integration in this milestone.

## M017 — Wallet settlement integration

Only after M016 has been implemented, reviewed, and validated, design an idempotent internal wallet transfer for eligible on-platform parties. It must reuse the existing transaction ledger, have a unique exchange settlement record, and be reviewed against the existing wallet invariants. External rails and cross-border transfers remain out of scope.

## M018 — Agricultural batch traceability events

Add append-only, role-attributed declarations for harvest, grading, weighing, transport, receipt, and inspection. Distinguish merchant-declared observations from independent or platform-verified facts. Public scan views should expose only appropriate summaries and role labels, never private dispute evidence or unnecessary personal data.

## M019 — Cooperative and group exchange

Introduce a minimal group identity and membership model only after governance, member authority, and dispute representation are specified. Do not introduce deposits, lending, pooled balances, or deposit-taking functionality as part of group identity.

## M020 — Offline-aware transaction intent

Design signed, expiring, replay-resistant transaction intents for later synchronisation. Offline intent is not offline payment authorisation. Spending limits, double-spend prevention, key lifecycle, device compromise, and conflict resolution must be addressed before any money-moving action is considered.

## M021 — Public economic intelligence snapshots

Expose aggregated, anonymised, read-only metrics. Apply minimum cohort thresholds and suppress low-volume slices before publication. Do not expose participant, merchant, buyer, exchange, or precise household identifiers.

## M022 — Developer SDK and open protocol

Extract stable reference, manifest, and intent schemas into a versioned package only after the protocol has meaningful interoperability tests and a clear compatibility policy.

## Deferred pending separate review

| Capability | Reason |
|---|---|
| M-Pesa or external mobile-money settlement | PSP approval, reconciliation, fraud controls, and regulatory review |
| Cross-border settlement | Multi-jurisdiction licensing, AML, and consumer-protection review |
| Biometric or automated KYC | Data protection impact assessment, vendor security, consent, retention, and redress |
| Tokenised assets | Virtual-asset regulatory assessment |
| AI agent-authorised transactions | Delegation, limits, revocation, audit, and liability model |
| Impact or regenerative outcome verification | Independent measurement and verification methodology |

## Delivery gates

1. Every new decision has an authenticated actor and a recorded timestamp.
2. Immutable history is append-only; corrections are new records, not edits.
3. Personal evidence remains private and is excluded from public or participant exports unless explicitly authorised.
4. State transitions are atomic, concurrency-safe, and covered by regression tests.
5. PHP lint and automated tests must execute successfully before a milestone is marked validated. A runner failure before the first job step is an infrastructure failure, not a test pass or an application-code failure.
6. Money-moving work requires a separate review of financial invariants and production controls.
