# ADR 0015 — Manual Buyer Identity Review at Exchange Acceptance

**Status:** Proposed; implementation in progress on PR #16  
**Date:** 2026-11-14

## Context

Sanctum Scan currently operates with separate user and merchant authentication guards. Email, SMS, and the platform's existing KYC status do not establish that two accounts across those guards belong to different real-world people. This can matter before a buyer accepts an economic exchange offer.

A full identity-proofing system would introduce substantial personal-data, consent, retention, vendor, regulatory, and redress obligations. Biometrics and automatic third-party KYC are not appropriate prerequisites for this narrow milestone.

## Decision

Introduce a manually administered identity-review ledger for user accounts and an optional feature gate at buyer offer acceptance.

Each review is an append-only record containing:

- target user account;
- review method;
- optional internal case/reference code;
- decision: `verified`, `rejected`, or `unable_to_verify`;
- concise review summary;
- authenticated administrator identity;
- review and creation timestamps.

The current user state is a projection of the latest review. A `verified` result sets the current verification timestamp and method. A later non-verified result clears those fields. Neither action changes or deletes prior review records.

The optional gate is disabled by default. When enabled, the buyer acceptance transaction locks the settings row and then the current buyer row before making the decision. Identity reviews lock the same buyer row. This serializes acceptance against a concurrent review decision.

The merchant may issue an offer to an otherwise eligible buyer regardless of this identity-review state. Only the buyer's acceptance is gated. The merchant API must not expose this private status. If acceptance is blocked, the exchange remains `offered` and no acceptance event is written.

Administrative review and gate configuration endpoints are protected by the existing admin authentication, role, and two-factor middleware. The reviewer and gate-change actor are taken from the authenticated admin session.

## Data protection rules

- The reference field is for an internal case identifier only.
- Do not store national ID or passport numbers, raw identity documents, document images, biometric templates, or other unnecessary identity data in this table.
- Do not return identity-review status to merchant-facing APIs.
- Keep the review history immutable and available only to authorised administrators.
- Record gate changes in the application log with the authenticated admin identifier.

## Consequences and limitations

- This is a manually reviewed account eligibility flag, not a guarantee of legal identity, uniqueness, AML compliance, or that user and merchant accounts do not share an owner.
- It does not replace a data-protection impact assessment, documented retention policy, user redress process, or jurisdiction-specific compliance review.
- The flag must remain disabled until the operator has an appropriate review procedure and authorised personnel.
- Existing payment, wallet, and settlement behavior is unchanged. No funds are moved by this decision.
