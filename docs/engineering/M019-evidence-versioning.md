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
| `composer check` | **96 tests, 1,049 assertions, 1 skipped (MySQL-only), green** |
| G8 Pint | 32 changed PHP files pass |

## MySQL gate verification (local, 2026-11-17)

The full `mysql` CI job sequence was executed against a disposable MySQL 9.4
instance (root, empty database, `migrate:fresh`):

| Step | Result |
|---|---|
| `php artisan migrate:fresh --force` | Green, all migrations including `2026_11_16_130000` |
| `php artisan db:seed --class='Database\Seeders\RouteIntrospectionSeeder'` | Green |
| `php artisan route:list --json` | Exit 0, 1,347 routes |
| Backfill `migrate:rollback --path=…2026_11_16_120000…` then `migrate` | Exit 0; backfill reapplied |
| `SANCTUM_SETTLEMENT_CONCURRENCY=1` on `SettlementConcurrencyTest` | **1 test, 6 assertions, green** |

Three defects were found only by running the job:

1. `sanctum_scan_exchange_evidence_reviews` used the default foreign-key name
   `sanctum_scan_exchange_evidence_reviews_reviewed_by_admin_id_foreign`
   (67 characters), which exceeds MySQL's 64-character identifier limit. It now
   uses the explicit name `scan_evidence_review_admin_foreign`.
2. The versioning migration dropped `unique(exchange_id)` while the
   `exchange_id` foreign key still needed an index. `up()` now adds
   `unique(exchange_id, version)` first; `down()` restores `unique(exchange_id)`
   before dropping the composite key.
3. `route:list` instantiates controllers, and ten legacy virtual-card
   constructors dereference `VirtualCardApi::first()`, so the command failed on
   an empty database. The workflow now seeds a gate fixture
   (`RouteIntrospectionSeeder`, documented in ADR 0016's amendment).

The `SettlementConcurrencyTest` fixture also lacked the payment-evidence row the
verification guard requires, and `AdminOperatorMiddlewareTest`'s happy-path
exchange lacked one too. Both fixtures now insert matching unverified evidence;
the production contract was correct and unchanged.

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
- `database/migrations/2026_11_13_166000_create_sanctum_scan_exchange_evidence_reviews_table.php`
  — explicit short foreign-key name for MySQL
- `database/seeders/RouteIntrospectionSeeder.php`,
  `.github/workflows/sanctum-scan-php-lint.yml` — route-introspection fixture
  and its CI step
- ADR 0016 amendment, ADR 0018, ADR 0017 amendment, ADR index, exchange API
  guide, feature map

## Limitations

- M023 adds an explicit preflight guard to `down()`: rollback is refused
  before schema changes if any row is not version 1 or has a predecessor link.
  Tests assert the schema, indexes, self-reference and all evidence rows remain
  unchanged after refusal. Rollback is safe only while the table contains
  initial version-1 rows only; see [M023](M023-fail-safe-evidence-rollback.md).
- Rejected settlement on an exchange that later closes remains rejected;
  cancellation does not rewrite it.
- No provider statement ingestion, automatic matching, or funds movement.
- The MySQL migration and concurrency checks pass locally on MySQL 9.4; the
  GitHub Actions run remains pending (R-02).

## Next

Operational: first GitHub CI run, MySQL job, branch protection (R-02). Then a
Phase III pilot prerequisite decision (participant/value chain and the minimum
reconciliation evidence).
