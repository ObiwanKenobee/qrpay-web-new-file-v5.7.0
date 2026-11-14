# Sanctum Scan — Laravel Implementation Roadmap

**Last updated:** 2026-11-13  
**Status:** Living document — update when a milestone is accepted or completed.

---

## Where we are

The table below maps every ADR to its implementation status.

| Milestone | ADR | What was built | Status |
|---|---|---|---|
| 002 | 0002 | Opaque scan references, SHA-256 storage, expiry, revocation, public resolution API | ✅ Done |
| 003 | 0003 | Authenticated merchant reference issuance, listing, revocation, audit attribution | ✅ Done |
| 004 | 0004 | Public scan pages, resource registry (products + agricultural batches), provenance events, QR rotation, archival | ✅ Done |
| 005 | 0005 | Economic exchange lifecycle (offer → accept → dispatch → receipt), payment evidence submission | ✅ Done |
| 006 | 0006 | Bilateral dispute open/message/propose-resolution/accept/reject/withdraw | ✅ Done |
| 007 | 0007 | Private dispute evidence (PDF/JPEG/PNG), SHA-256 integrity, admin platform review | ✅ Done |
| 008 | 0008 | Independent verifier institution registry, membership invitations, evidence mandates, immutable attestations | ✅ Done |
| 009 | 0009 | Verifier credential due-diligence reviews, conflict-of-interest declarations, participant attestation appeals | ✅ Done |
| 010 | 0010 | Versioned attestation corrections and withdrawal, predecessor chain, participant-facing effective state | ✅ Done |
| 011 | 0011 | Independent attestation reassessment by a different institution member | 🔵 ADR proposed — not yet implemented |

---

## Milestone 011 — Independent attestation reassessment

**ADR:** 0011 (Proposed)  
**Depends on:** M008, M009, M010

### What to build

- `SanctumScanAttestationReassessment` model and migration already exist in the codebase.
- Wire the service layer: a new `AttestationReassessmentManager` in `app/Services/SanctumScan/`.
- Authorization pipeline: institution active → member credential current and scope-matched → new mandate issued → no-conflict declaration → evidence SHA-256 intact → reassessor ≠ original membership.
- Route: `POST /api/user/sanctum/verifier/memberships/{membershipId}/attestations/{attestationId}/reassessments`
- Append events to verifier registry log and exchange timeline.
- Expose linked reassessments alongside source attestation in participant evidence listings.
- Feature tests covering the full authorization pipeline and the immutability invariants.
- ADR 0011 doc: update status from Proposed → Accepted.

### Files to create / modify

```
app/Services/SanctumScan/AttestationReassessmentManager.php   (new)
app/Http/Controllers/Api/User/SanctumScan/
    AttestationReassessmentController.php                      (new)
routes/api/api.php                                             (add route)
docs/api/sanctum-scan-attestation-reassessments.md             (already exists — verify completeness)
tests/Feature/SanctumScan/AttestationReassessmentTest.php      (new)
docs/architecture/decisions/0011-...md                         (status → Accepted)
```

---

## Milestone 012 — Buyer identity verification prerequisite

**Rationale:** Exchanges currently require only that the buyer account has verified email and SMS (M005). The verifier trust chain (M008–M011) is now mature enough to expose a gap: the platform cannot confirm that a user account and a merchant account do not belong to the same real-world person. Before adding payment settlement or financial obligations, this cross-guard identity limitation must be documented and a minimum mitigation introduced.

**Scope (narrow — do not over-engineer):**

- Add a `identity_verified_at` timestamp and `identity_verification_method` string to the `users` table.
- Admin UI: allow an administrator to record a manual identity check (method, reference, reviewer, timestamp) against a user account — same pattern as verifier credential reviews.
- Gate exchange offer acceptance behind `identity_verified_at` being non-null (configurable per environment via a feature flag in `BasicSettings`).
- Document the remaining cross-guard limitation explicitly in a new ADR 0012.
- No biometric capture, no third-party KYC API integration in this milestone.

### Files

```
database/migrations/..._add_identity_verification_to_users_table.php
app/Http/Controllers/Admin/SanctumScan/UserIdentityVerificationController.php
resources/views/admin/sections/user-care/identity-verification.blade.php
docs/architecture/decisions/0012-buyer-identity-verification-prerequisite.md
tests/Feature/SanctumScan/BuyerIdentityGateTest.php
```

---

## Milestone 013 — Exchange quantity and unit visibility

**Rationale:** M004 deliberately kept quantity and unit private to the merchant. M005 snapshots negotiated quantity and total amount into the exchange record. Before payment settlement can be meaningful, buyers need to see what quantity they agreed to receive, and the exchange record needs a unit field that is visible to both parties.

**Scope:**

- Add `quantity`, `unit`, and `unit_label` to `sanctum_scan_exchanges` (migration).
- Expose these fields in buyer and merchant exchange API responses.
- Update the public scan page to show declared quantity and unit for agricultural batches when the resource is linked to an accepted exchange (only after buyer acceptance — not before).
- Provenance event: record quantity disclosure as an append-only event on the resource.
- ADR 0013.

---

## Milestone 014 — Payment settlement state machine

**Rationale:** M005 accepts payment evidence but marks it permanently `unverified`. The exchange has no settlement state. This milestone introduces a settlement state machine without touching the existing wallet or transaction infrastructure.

**Scope:**

- Add `settlement_status` enum to `sanctum_scan_exchanges`: `awaiting_payment | payment_evidence_submitted | payment_verified | payment_rejected | settled | cancelled`.
- Admin or authorized verifier can transition `payment_evidence_submitted → payment_verified | payment_rejected`.
- `payment_verified` does not move money. It records that the platform has checked the submitted payment reference against the stated rail.
- `settled` is set only when both `payment_verified` and `receipt_confirmed` are true.
- All transitions append to the exchange event timeline.
- No wallet balance changes in this milestone.
- ADR 0014.

---

## Milestone 015 — Wallet settlement integration

**Rationale:** M014 establishes a verified settlement state. This milestone connects it to the existing wallet infrastructure for internal (on-platform) transfers only.

**Scope:**

- When `settlement_status` transitions to `settled` and both parties are on-platform wallet holders, trigger an internal wallet transfer using the existing `Transaction` and `TransactionCharge` models.
- The transfer is idempotent (guard against double-settlement with a unique constraint on `exchange_id` in a new `sanctum_scan_settlement_records` table).
- Merchant wallet receives the agreed amount minus platform fee (configurable in `TransactionSettings`).
- Append `SANCTUM_EXCHANGE_SETTLED` to the exchange event timeline and create a standard `Transaction` record of type `SANCTUM-EXCHANGE`.
- External payment rail settlement (M-Pesa, Stripe, etc.) is out of scope for this milestone.
- ADR 0015.

---

## Milestone 016 — Agricultural batch traceability events

**Rationale:** The README describes a farmer → aggregator → transporter → buyer value chain. The resource registry (M004) captures declared origin and harvest date. This milestone adds structured traceability events to agricultural batch resources.

**Scope:**

- New `sanctum_scan_batch_traceability_events` table: `resource_id`, `event_type` (enum: `harvested | graded | weighed | transported | received | inspected`), `actor_type` (merchant | agent | admin), `actor_id`, `location_label`, `declared_quantity`, `unit`, `notes`, `recorded_at`.
- Merchant API: `POST /merchant-api/merchant/sanctum/resources/{resourceId}/traceability-events`
- Public scan page: show traceability timeline for agricultural batches (actor names redacted to role labels for privacy).
- All events are append-only and merchant-declared (same provenance labelling as M004).
- ADR 0016.

---

## Milestone 017 — Cooperative and group exchange

**Rationale:** The README identifies SACCOs, chamas and farmer cooperatives as a primary use case. This milestone introduces a minimal group identity layer without building a full cooperative finance module.

**Scope:**

- New `sanctum_scan_groups` table: `name`, `handle`, `type` (enum: `cooperative | chama | producer_group | community`), `admin_user_id`, `status`, `created_at`.
- New `sanctum_scan_group_members` table: `group_id`, `user_id`, `role` (admin | member), `joined_at`.
- A group can be the buyer on an exchange (extend `sanctum_scan_exchanges.buyer_type` to accept `group`).
- Group admin must approve exchange acceptance on behalf of the group.
- Append group governance events to the exchange timeline.
- No deposit-taking, lending or financial pooling in this milestone.
- ADR 0017.

---

## Milestone 018 — Offline-aware transaction intent

**Rationale:** The README section on offline-first infrastructure. This milestone introduces signed, time-limited transaction intents that can be prepared without connectivity and submitted later.

**Scope:**

- New `sanctum_scan_transaction_intents` table: `reference` (opaque), `type` (payment_request | exchange_offer), `payload_hash` (SHA-256 of canonical JSON), `signed_by` (user/merchant id), `signature` (HMAC-SHA256 using account-scoped key), `expires_at`, `submitted_at`, `status` (pending | submitted | expired | rejected).
- Intent creation API: `POST /api/user/sanctum/intents` — returns a signed intent reference that can be embedded in a QR code.
- Intent submission API: `POST /api/user/sanctum/intents/{reference}/submit` — validates signature, expiry and idempotency before creating the exchange or payment evidence record.
- Replay prevention: unique constraint on `payload_hash` + `signed_by`.
- Offline payment authorization, spending limits and double-spend prevention are explicitly out of scope and must be addressed before production use.
- ADR 0018.

---

## Milestone 019 — Public economic intelligence API (read-only)

**Rationale:** The README describes merchant analytics and agricultural market intelligence. This milestone exposes aggregate, anonymised read-only data through a public API — no personal data, no individual transaction details.

**Scope:**

- New `sanctum_scan_intelligence_snapshots` table: daily aggregates per resource category and geographic label (county/region string, not precise coordinates).
- Aggregated fields: `exchange_count`, `settled_count`, `total_declared_value_kes` (KES only in v1), `avg_quantity_per_exchange`, `most_common_unit`.
- Scheduled job: `php artisan sanctum:aggregate-intelligence` — runs nightly, appends a new snapshot row.
- Public API: `GET /api/sanctum/v1/intelligence/snapshots?category=agricultural_batch&region=nakuru&from=2026-01-01`
- No individual merchant, buyer or exchange identifiers in the response.
- ADR 0019.

---

## Milestone 020 — Developer SDK and open protocol package

**Rationale:** The README describes open, interoperable infrastructure. This milestone extracts the QR schema, scan reference protocol and exchange intent schema into a standalone, independently licensed PHP package.

**Scope:**

- New directory: `packages/sanctum-scan-protocol/`
- Extract: QR payload schema validation, scan reference generation/verification, exchange intent canonical JSON builder, SHA-256 digest helpers.
- Composer package: `atlassanctum/sanctum-scan-protocol` with its own `composer.json`, `LICENSE` (Apache-2.0 for original code), `CHANGELOG.md` and `README.md`.
- The Laravel application depends on the package via a local path repository during development.
- CI: add a separate lint and test job for the package.
- ADR 0020.

---

## Deferred / out of scope until further research

The following capabilities are referenced in the README but deliberately excluded from the near-term roadmap pending regulatory review, operational security design or further field research:

| Capability | Reason deferred |
|---|---|
| M-Pesa / mobile money settlement | Requires PSP authorization and production security review |
| Cross-border payment integration | Multi-jurisdiction regulatory assessment needed |
| Biometric / third-party KYC | Identity proofing vendor selection and data protection review |
| Tokenized asset representation | Virtual asset regulation assessment |
| AI agent authorized transactions | Governance model and spending-limit design not yet defined |
| Impact / regenerative outcome tracking | Requires independent verification methodology |
| Diaspora productive investment | Regulatory and AML assessment |

---

## Sequencing rationale

```
M011  Complete the verifier trust chain
  |
M012  Establish minimum buyer identity gate
  |
M013  Make exchange quantity visible to both parties
  |
M014  Introduce settlement state machine (no money moves)
  |
M015  Connect settlement to on-platform wallets
  |
M016  Agricultural traceability events (parallel track)
M017  Cooperative group exchange (parallel track)
  |
M018  Offline-aware transaction intents
  |
M019  Public intelligence API
  |
M020  Extract open protocol package
```

Milestones 016 and 017 can be developed in parallel with 014–015 because they do not depend on the settlement state machine.

---

## ADR index

| ADR | Title | Status |
|---|---|---|
| 0001 | Incremental adoption | Accepted |
| 0002 | Scan reference resolution | Accepted |
| 0003 | Merchant scan identity | Accepted |
| 0004 | Public scan and resource registry | Accepted |
| 0005 | Economic exchange and transaction evidence | Accepted |
| 0006 | Bilateral dispute resolution | Accepted |
| 0007 | Private dispute evidence and platform review | Accepted |
| 0008 | Independent verifier mandates and attestations | Accepted |
| 0009 | Verifier credentials, conflicts and appeals | Accepted |
| 0010 | Versioned attestation revisions | Accepted |
| 0011 | Independent attestation reassessment | Proposed |
| 0012 | Buyer identity verification prerequisite | Planned |
| 0013 | Exchange quantity and unit visibility | Planned |
| 0014 | Payment settlement state machine | Planned |
| 0015 | Wallet settlement integration | Planned |
| 0016 | Agricultural batch traceability events | Planned |
| 0017 | Cooperative and group exchange | Planned |
| 0018 | Offline-aware transaction intents | Planned |
| 0019 | Public economic intelligence API | Planned |
| 0020 | Developer SDK and open protocol package | Planned |
