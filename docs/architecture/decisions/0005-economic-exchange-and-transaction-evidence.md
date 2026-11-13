# ADR 0005: Economic Exchange and Transaction Evidence

- **Status:** Accepted for the first bilateral exchange workflow
- **Date:** 2026-11-13
- **Depends on:** ADR 0001, ADR 0002, ADR 0003 and ADR 0004

## Context

The resource registry can identify merchant-owned products and agricultural batches, but it does not yet represent an agreement between a buyer and a seller.

The existing application already has payment-order requests, merchant checkout and wallet settlement transactions. Sanctum Scan must not introduce a parallel wallet or payment processor, nor confuse a buyer/seller agreement with a completed payment.

## Decision

Introduce a separate economic exchange record linked to a merchant, an authenticated buyer and a registered resource.

### Offer creation

- Only an authenticated merchant can issue an offer.
- The offered resource must be published and owned by that merchant.
- The buyer is identified by an existing active user account username.
- The offer snapshots resource type, name and reference code so historical terms remain intelligible if the resource changes later.
- Quantity, unit, total offer amount, three-letter currency code, optional terms and expiry are stored on the exchange record.
- The amount represents the total for the offered quantity, not a per-unit price.
- Offers expire after 72 hours by default; the merchant can choose 1–168 hours.
- Creating an offer does not reserve inventory or move money.

### State machine

\`offered\` can transition to:
- \`accepted\` by the named buyer;
- \`declined\` by the named buyer;
- \`cancelled\` by the merchant before buyer acceptance;
- \`expired\` when the expiry time passes;
- \`cancelled\` by the system if the merchant or resource becomes unavailable before acceptance.

\`accepted\` can transition to \`dispatched\` by the merchant.

\`dispatched\` can transition to \`received\` by the named buyer.

Receipt confirmation is a buyer declaration, not independent proof of delivery. Accepted offers cannot be unilaterally cancelled through this initial API. Disputes, amendments and refunds are outside this slice.

### Event evidence

Every lifecycle action creates an append-only exchange event with:
- event type and description;
- actor guard and ID;
- timestamp;
- explicit verification status;
- optional event metadata.

Merchant dispatch is marked \`merchant_declared\`; buyer acceptance and receipt are marked \`buyer_declared\`; automatic expiry/withdrawal is \`system_recorded\`.

Events are immutable through the application model.

### Payment references

The buyer may submit one payment reference after offer acceptance.

- Supported rail labels: M-Pesa, bank transfer, cash, card or other.
- Submitted amount and currency must exactly match the accepted offer.
- The evidence record is immutable after submission.
- Its verification status is always \`unverified\` in this release.
- Submission does not change the exchange state, wallet balances, payment-order requests or transactions.
- No provider API is called and no settlement is inferred.

Future reconciliation must be a separate privileged workflow tied to a verified provider event or an authorized manual review. It must not overwrite the original submitted evidence.

## API

Merchant:
- \`GET /merchant-api/merchant/sanctum/exchanges\`
- \`POST /merchant-api/merchant/sanctum/exchanges\`
- \`POST /merchant-api/merchant/sanctum/exchanges/{exchangeId}/dispatch\`
- \`POST /merchant-api/merchant/sanctum/exchanges/{exchangeId}/cancel\`

Buyer:
- \`GET /api/user/sanctum/exchanges\`
- \`POST /api/user/sanctum/exchanges/{exchangeId}/accept\`
- \`POST /api/user/sanctum/exchanges/{exchangeId}/decline\`
- \`POST /api/user/sanctum/exchanges/{exchangeId}/receipt\`
- \`POST /api/user/sanctum/exchanges/{exchangeId}/payment-reference\`

All reads and actions are scoped to the authenticated participant. User IDs, merchant IDs and actor IDs are never accepted as authority from request payloads.

## Security and privacy

- Merchant offers require merchant authentication, account checks, PIN setup and the existing merchant KYC gate.
- Buyer actions require the existing user authentication, verification, account and 2FA stack; state-changing actions also use the PIN setup gate.
- Payment reference submission passes the existing user KYC gate.
- Rate limits apply to exchange operations.
- No public or unauthenticated exchange endpoint exists.
- Buyer contact details and private wallet information are never exposed in exchange responses.
- Exchange history is retained separately from the public product profile.

## Consequences

### Positive

- Links a scan-identified economic resource to a specific bilateral offer.
- Gives both parties a participant-scoped view of the same agreement.
- Preserves the offer terms and event history.
- Separates dispatch, receipt and payment evidence from actual settlement.
- Creates an auditable foundation for future reconciliation and dispute handling.

### Trade-offs and non-goals

- One payment reference is allowed per exchange in this first slice.
- Inventory is not reserved or decremented when an offer is issued or accepted.
- There is no public buyer marketplace, chat, negotiation or offer amendment workflow.
- No payment is initiated, captured, refunded or reconciled by this milestone.
- A buyer's receipt declaration does not establish legal delivery or quality acceptance.
