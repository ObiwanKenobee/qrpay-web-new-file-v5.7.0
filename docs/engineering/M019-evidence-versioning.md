# M019 — Settlement Evidence Versioning

**Status:** Implemented
**Date:** 2026-11-16
**Depends on:** M016 (settlement), M018 (operator middleware), ADR 0017
**Decision:** [ADR 0018](../decisions/0018-payment-evidence-versioning.md)
**Base branch:** `feature/qrpay-update`

## Goal

Let a buyer supersede a rejected payment reference with an append-only new
version and reopen verification, without rewriting the rejected evidence or the
administrator's decision.

## Delivered

### Schema

Migration `2026_11_16_130000_add_versioning_to_sanctum_scan_exchange_payment_evidence_table.php`:

- `version` unsigned integer, default 1;
- `supersedes_id` nullable self-reference;
- `unique(exchange_id)` replaced by
  `unique(exchange_id, version)` with the explicit name
  `scan_payment_evidence_exchange_version_unique`;
- self foreign key on `supersedes_id`.

Evidence rows remain immutable through the model. The composite index is the
database backstop against duplicate versions.

### State machine

- `payment_rejected -> payment_evidence_submitted` allowed for `api` only.
- `payment_rejected` removed from `TERMINAL`; `settled` and `cancelled` remain
  terminal.
- Administrators cannot reopen a rejection by writing a status.

### Submission

`POST /api/user/sanctum/exchanges/{exchangeId}/payment-reference`:

- first submission: settlement `awaiting_payment`, no evidence;
- resubmission: settlement `payment_rejected`, `version = latest + 1`,
  `supersedes_id = latest id`;
- other states with evidence: `payment_reference_exists` (409);
- event `payment_reference_resubmitted` for later versions, with `version` and
  `supersedes_id` in the event data.

Version numbering runs inside the existing exchange row lock; the unique index
enforces it if two submissions race.

### Projections

- Buyer and merchant: latest `payment_evidence` now includes `version`.
- Admin settlement: latest evidence plus `payment_evidence_history` (all
  versions with `version`, `supersedes_id`, reference, amount, currency,
  submitted time).

## Executed evidence (local, 2026-11-16)

| Check | Result |
|---|---|
| `vendor/bin/phpunit` on the state-machine, settlement, exchange, and middleware suites | green |
| `SettlementLifecycleTest` new scenario | rejection → refused direct verification → version-2 submission → version-1 preserved with supersession link → admin history → resubmission event → verification of version 2 |
| `PaymentEvidenceVersioningMigrationTest` | composite unique key present, old unique removed; `down()` restores the single-column unique (7 assertions) |
| `composer check` | **94 tests, 1,017 assertions, 1 skipped (MySQL-only), green** |
| G8 Pint | 30 changed PHP files pass |

## Changes

- `database/migrations/2026_11_16_130000_add_versioning_to_sanctum_scan_exchange_payment_evidence_table.php`
- `app/Models/SanctumScanExchangePaymentEvidence.php` — version casts
- `app/Models/SanctumScanExchange.php` — `paymentEvidence()` is latest-of-many;
  new `paymentEvidences()` history relation
- `app/Services/SanctumScan/SettlementStateMachine.php` — reopen edge; terminal
  set reduced
- `app/Http/Controllers/Api/User/SanctumScanExchangeController.php` —
  resubmission path, versioned event
- `app/Http/Controllers/Api/Merchant/SanctumScanExchangeMerchantController.php`
  — version in projection
- `app/Http/Controllers/Admin/SanctumScanExchangeSettlementController.php` —
  history projection
- `tests/Unit/SanctumScan/SettlementStateMachineTest.php`,
  `tests/Feature/SanctumScan/SettlementLifecycleTest.php` — matrix and lifecycle
- `tests/Feature/SanctumScan/EconomicExchangeLifecycleTest.php`,
  `tests/Feature/SanctumScan/AdminOperatorMiddlewareTest.php` — load the new
  migration
- ADR 0018, ADR 0017 amendment, ADR index, exchange API guide, feature map

## Limitations

- `down()` cannot restore `unique(exchange_id)` once version 2 exists without
  deleting history, so it fails in that case. Rollback is safe only before a
  resubmission; this is recorded in ADR 0018.
- Rejected settlement on an exchange that later closes remains rejected;
  cancellation does not rewrite it.
- No provider statement ingestion, automatic matching, or funds movement.
- The MySQL migration and concurrency checks remain unexecuted until CI runs.

## Next

Operational: first GitHub CI run, MySQL job, branch protection (R-02). Then a
Phase III pilot prerequisite decision (participant/value chain and the minimum
reconciliation evidence).
