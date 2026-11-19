# M020 — Evidence-Version-Bound Settlement Decisions

**Status:** In progress
**Date:** 2026-11-17
**Depends on:** M016 settlement state machine, M019 evidence versioning, ADR 0019

## Goal

Make every administrative verification, rejection and settlement event name
the exact immutable payment-evidence version it concerns. An audit reader must
not need to infer the reviewed version from a later "latest evidence" query.

## Delivered in this branch

- `ExchangeSettlementManager` attaches evidence row ID and version to
  `payment_verified`, `payment_rejected` and `settled` events.
- A deterministic SHA-256 fingerprint binds the event to the complete
  immutable version payload without copying its payment reference into the
  decision event.
- Transitions that require a decision subject fail closed when the evidence
  row is missing.
- The administrator's settlement projection returns the evidence ID, version
  and fingerprint for each relevant event.
- Lifecycle tests assert rejection of version 1, verification of version 2,
  distinct fingerprints, and the binding on settlement.

## Acceptance criteria

1. A version-1 rejection event identifies evidence version 1.
2. A version-2 verification event identifies evidence version 2.
3. The verification and rejection fingerprints are valid SHA-256 digests and
   differ when the underlying evidence differs.
4. The subsequent `settled` event references the same evidence version as the
   preceding `payment_verified` event.
5. The admin audit projection returns the same IDs, versions and fingerprints.
6. No migration, provider integration, wallet entry or funds movement is added.
7. PHP syntax, the full PHPUnit suite, MySQL migration/concurrency job, and Pint
   pass in CI before status advances to Verified.

## Explicit non-goals

- Proving that a payment reference exists with a provider.
- Cryptographic signing, trusted timestamps or external attestation.
- Updating evidence rows after submission.
- Financial ledger or wallet integration.

## Validation status

Not yet executed on this branch. The existing GitHub Actions runner-allocation
blocker remains tracked under [issue #21](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/issues/21).
