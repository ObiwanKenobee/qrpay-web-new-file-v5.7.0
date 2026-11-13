# Sanctum Scan: Bilateral Dispute API

Milestone 006 adds a private dispute record to an accepted exchange. Buyer and merchant operations use identical paths under their respective authenticated API prefixes.

## Eligibility and states

A dispute can be opened only when the exchange is `accepted`, `dispatched` or `received`. The first dispute record is retained for the exchange; it cannot be deleted, replaced or reopened.

States:

- `open`: participants may add statements or one party may propose a resolution.
- `resolution_proposed`: only the other participant may accept or reject the proposal.
- `resolved`: the other participant accepted the proposal.
- `withdrawn`: the initiating participant withdrew before the other participant responded.

Rejecting a proposal returns the dispute to `open`. The proposal and rejection remain in the message and exchange event histories.

## Endpoints

Buyer prefix: `/api/user`

Merchant prefix: `/merchant-api/merchant`

| Method | Path suffix | Purpose |
|---|---|---|
| GET | `/sanctum/exchanges/{exchangeId}/dispute` | Read the participant's dispute record, or null |
| POST | `/sanctum/exchanges/{exchangeId}/dispute` | Open a dispute |
| POST | `/sanctum/exchanges/{exchangeId}/dispute/messages` | Add a statement while open |
| POST | `/sanctum/exchanges/{exchangeId}/dispute/propose-resolution` | Propose a resolution |
| POST | `/sanctum/exchanges/{exchangeId}/dispute/accept-resolution` | Accept the other participant's proposal |
| POST | `/sanctum/exchanges/{exchangeId}/dispute/reject-resolution` | Reject the other participant's proposal |
| POST | `/sanctum/exchanges/{exchangeId}/dispute/withdraw` | Withdraw an unresponded dispute as its initiator |

## Open a dispute

```http
POST /api/user/sanctum/exchanges/42/dispute
Content-Type: application/json
Authorization: Bearer <user-token>
```

```json
{
  "category": "quantity_mismatch",
  "statement": "The delivered quantity appears lower than the quantity in the accepted agreement."
}
```

Allowed categories: `non_delivery`, `quantity_mismatch`, `quality_concern`, `payment_question`, `terms_disagreement`, `other`.

## Add a statement

```json
{
  "message": "I have checked the delivery records and would like to compare the measured quantity."
}
```

Statements are append-only. A statement records the authenticated side (buyer or merchant), not a client-supplied identity.

## Propose and accept a resolution

The proposing participant sends:

```json
{
  "summary": "Both parties agree to arrange a joint quantity check before any further action."
}
```

The other participant may accept with an empty POST, or reject with:

```json
{
  "reason": "I cannot agree to the proposed inspection arrangement; I propose a different time."
}
```

The proposer cannot accept or reject their own proposal. Acceptance marks the dispute `resolved`; it does not establish an independent finding or alter payment settlement.

## Withdraw

Only the initiating participant may withdraw, and only while the dispute is open and before the other participant has added a statement. A withdrawn record remains in the history.

## Security and evidence semantics

- Existing user/merchant authentication, account-status and 2FA middleware remains in force.
- State-changing operations also require the existing PIN-setup guard and are rate-limited.
- Every route scopes the exchange to the authenticated buyer or merchant.
- Dispute messages are not editable or deletable through their Eloquent model.
- Exchange events record each dispute action with the actor and a party-declared verification label.
- No party can view or mutate another exchange's dispute.
- Dispute statements are claims by participants, not independently verified facts.
- This API does not adjudicate disputes, upload files, issue refunds, invoke chargebacks, change wallets, call payment providers or reconcile settlement.
