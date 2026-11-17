# Sanctum Scan Economic Exchange API

Milestone 005 adds bilateral offers between an authenticated merchant and an existing authenticated user.

The exchange API is not a wallet or payment processor. It records agreed terms, participant actions and unverified payment references. It does not initiate or settle payments.

## Merchant endpoints

All merchant routes require the existing \`merchant_api\` Passport guard, account checks and merchant 2FA. State-changing merchant routes use the PIN setup guard. Offer issuance also uses the existing KYC middleware.

### List exchanges

\`GET /merchant-api/merchant/sanctum/exchanges\`

Returns up to 100 most recent exchanges belonging to the authenticated merchant.

### Issue an offer

\`POST /merchant-api/merchant/sanctum/exchanges\`

Example:

\`\`\`json
{
  "resource_id": 41,
  "buyer_username": "nakuru-buyer",
  "quantity": "100.0000",
  "unit": "kg",
  "amount": "25000.00",
  "currency": "KES",
  "terms": "Collect from the agreed local market point.",
  "expires_in_hours": 48
}
\`\`\`

The resource must be published and owned by the authenticated merchant. The buyer must be an existing active user account with email and SMS verification completed. The unit must match the registered resource unit when one is configured.

The \`amount\` is the total agreed amount for the specified quantity, not a per-unit price. Currency is a three-letter uppercase code. The default offer lifetime is 72 hours; the optional lifetime must be 1–168 hours.

### Dispatch an accepted offer

\`POST /merchant-api/merchant/sanctum/exchanges/{exchangeId}/dispatch\`

Optional request:

\`\`\`json
{
  "dispatch_reference": "LOCAL-PICKUP-005",
  "dispatch_note": "Goods made available for collection."
}
\`\`\`

Only the merchant that issued the offer can dispatch it, and only after buyer acceptance. Dispatch is recorded as a merchant declaration.

### Cancel an offer

\`POST /merchant-api/merchant/sanctum/exchanges/{exchangeId}/cancel\`

The merchant can cancel an offer only while it is still awaiting buyer acceptance.

## Buyer endpoints

All buyer routes require the existing \`api\` Passport guard, email verification, account checks and user 2FA. State-changing actions use the PIN setup guard.

### List offers

\`GET /api/user/sanctum/exchanges\`

Returns only exchanges addressed to the authenticated buyer.

### Accept or decline

\`POST /api/user/sanctum/exchanges/{exchangeId}/accept\`

\`POST /api/user/sanctum/exchanges/{exchangeId}/decline\`

Only the named buyer can accept or decline an offer. Expired offers cannot be accepted. Acceptance is not a payment.

### Confirm receipt

\`POST /api/user/sanctum/exchanges/{exchangeId}/receipt\`

Optional request:

\`\`\`json
{
  "receipt_note": "Goods received by buyer."
}
\`\`\`

Receipt can be confirmed only after merchant dispatch. It is recorded as a buyer declaration, not independent proof of delivery.

### Submit a payment reference

\`POST /api/user/sanctum/exchanges/{exchangeId}/payment-reference\`

Example:

\`\`\`json
{
  "rail": "mpesa",
  "reference": "QJK8XY5ABCDE",
  "amount": "25000.00",
  "currency": "KES"
}
\`\`\`

Allowed rail labels: \`mpesa\`, \`bank_transfer\`, \`cash\`, \`card\`, \`other\`.

The amount and currency must match the accepted offer. A reference may be submitted once while settlement is \`awaiting_payment\`. If an administrator rejects it (\`payment_rejected\`), the buyer may submit one replacement; the new row is version 2 and points at version 1 through \`supersedes_id\`. Version 1 is never overwritten or deleted. A submission in any other settlement state returns \`payment_reference_exists\`.

The response marks the evidence \`unverified\` and includes its \`version\`. A reference is only a buyer-submitted identifier; it is not confirmation that money was transferred, received or settled. Verification and reconciliation are not implemented in milestone 005.

Payment reference submission uses the existing user PIN and KYC gates.

### Settlement decision evidence binding

Each administrator verification, rejection, and settlement event records the
exact immutable payment-evidence row it acted upon:

- `payment_evidence_id` — database identity of the evidence version;
- `payment_evidence_version` — buyer submission sequence number;
- `payment_evidence_sha256` — SHA-256 fingerprint of the complete version,
  including its exchange, version/supersession metadata, rail, reference,
  amount, currency, submitting actor and submission timestamp.

The fingerprint payload uses a fixed key order and compact UTF-8 JSON with
unescaped slashes/Unicode, invalid-UTF-8 substitution and preserved zero
fractions. The hash is an audit fingerprint, not a digital signature, trusted
timestamp, payment-provider confirmation or proof that funds moved. The
evidence row itself remains `unverified`; the administrator's decision is
recorded separately in the immutable exchange event.

## Exchange states

| State | Meaning |
|---|---|
| \`offered\` | Merchant issued an offer to a named buyer |
| \`accepted\` | Named buyer accepted the offer |
| \`declined\` | Named buyer declined the offer |
| \`dispatched\` | Merchant declared the goods/service dispatched |
| \`received\` | Named buyer declared receipt |
| \`cancelled\` | Merchant withdrew before acceptance or resource became unavailable |
| \`expired\` | Offer expired before acceptance |

Payment evidence has its own verification state and does not change the exchange state. The buyer and merchant projections return the latest evidence version; the admin settlement projection also returns \`payment_evidence_history\` with every version.

## Evidence and audit trail

Lifecycle actions append events with actor guard, actor ID, timestamp and declaration status. Event records and submitted payment evidence are immutable through the application model.

## Explicit boundaries

- No wallet is debited or credited.
- No record is inserted into the existing financial \`transactions\` ledger.
- Existing \`payment_order_requests\` and checkout flows are not repurposed.
- No stock is reserved or decremented.
- No settlement, delivery, ownership, quality or certification claim is independently verified by this API.
