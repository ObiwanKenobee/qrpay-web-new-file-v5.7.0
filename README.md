# Sanctum Scan

### The Open Protocol for Verified Economic Exchange

**Scan. Identify. Exchange. Verify. Coordinate. Flourish.**

Sanctum Scan is an initiative by **[Atlas Sanctum](https://atlassanctum.me)** exploring an open, interoperable infrastructure for economic exchange.

It connects people, businesses, merchants, farmers, cooperatives, institutions, assets and communities through a common digital interface for identifying economic participants, expressing intentions, coordinating transactions, verifying exchanges and recording outcomes.

A QR code is the entry point, not the product.

The larger ambition is to make economic activity more visible, trustworthy, inclusive and intelligent.

> **Implementation status:** This repository currently contains an existing Laravel QRPay application that is being evaluated and extended toward Sanctum Scan. The architecture below describes the intended direction; proposed capabilities are not claims that they are already implemented.

> **We are not simply building another payment application. We are exploring the infrastructure that enables people and institutions to create, exchange, preserve and multiply value.**

---

## 1. The Problem

Economic activity is everywhere, but the systems that represent it remain fragmented.

Farmers produce food. Merchants distribute goods. Transporters move resources. Cooperatives pool capital. Families support one another. Communities build shared infrastructure.

These activities generate enormous economic value, yet much of their surrounding information remains disconnected.

A conventional financial transaction might tell us:

```text
KES 5,000 transferred.
```

But it may not tell us:

- Who participated in the exchange?
- What was being exchanged?
- Was the agreed quantity delivered?
- Were the obligations fulfilled?
- Where did the economic value originate?
- What happened after settlement?
- Can the participants establish a verifiable economic history?
- Could the transaction contribute to better economic decisions?

The problem extends beyond payments.

It involves identity, trust, coordination, information asymmetry, fragmented records, access to productive capital and the ability to verify economic outcomes.

**The world can move money without adequately understanding the economic relationships behind it.**

Sanctum Scan explores how to close this infrastructure gap.

---

## 2. The Vision

### From QR Payments to Economic Infrastructure

Traditional QR payment systems primarily answer:

> Where should I send the money?

Sanctum Scan aims to answer:

> Who am I interacting with, what is being exchanged, what are the agreed terms, how can the exchange be verified, and what happened afterward?

The proposed experience:

```text
SCAN
  |
  v
IDENTIFY
  |
  v
UNDERSTAND
  |
  v
VERIFY
  |
  v
AGREE
  |
  v
EXCHANGE
  |
  v
RECORD
  |
  v
LEARN
  |
  v
FLOURISH
```

A scan could resolve a merchant, person, product, agricultural batch, asset, agreement, service, payment request or community project.

The objective is to create a common digital interface connecting physical economic activity with trusted digital infrastructure.

---

## 3. Core Principles

### 3.1 Ancient Wisdom, Modern Technology

Sanctum Scan explores how enduring principles of economic life can inform modern digital institutions.

| Principle | Inspiration | Modern application |
|---|---|---|
| Mutuality | Ubuntu and Harambee | Cooperative exchange and mutual assistance |
| Fairness | Just weights and measures | Transparent pricing and verifiable quantities |
| Reciprocity | Community exchange traditions | Accountable economic relationships |
| Stewardship | Biblical stewardship | Responsible management of capital and shared resources |
| Collective responsibility | Commons governance | Participatory management of shared assets |
| Restoration | Jubilee principles | Responsible finance and economic resilience |

These principles are not decorative additions to the technology.

They are questions about how economic infrastructure should serve people.

Technology should make fairness, accountability, reciprocity and stewardship easier to practice at scale.

Local communities should participate in defining the rules governing their own resources, identities and economic relationships.

### 3.2 Build for the Real Economy

Start with existing economic activity rather than inventing artificial use cases.

### 3.3 Open Infrastructure

Create interoperable protocols that developers, businesses, institutions and communities can build upon.

### 3.4 Trust by Design

Make verification, privacy, accountability and security foundational architectural concerns.

### 3.5 Financial Inclusion and Economic Capability

Move beyond access to digital payments toward productive participation, economic resilience and opportunity.

### 3.6 Evidence over Claims

Distinguish verified events from assumptions, estimates, predictions and impact claims.

### 3.7 Human Flourishing

Financial and technological systems should contribute to stronger livelihoods, healthier communities and regenerative relationships with the natural world.

---

## 4. The Sanctum Scan Architecture

The proposed architecture consists of interconnected capabilities.

```text
                  SANCTUM SCAN
                       |
             PHYSICAL WORLD INTERFACE
                       |
       +---------------+----------------+
       |               |                |
    Identity         Assets          Services
       |               |                |
       +---------------+----------------+
                       |
                SANCTUM EXCHANGE
                       |
       +---------------+----------------+
       |               |                |
    Intent          Agreement        Payment
       |               |                |
       +---------------+----------------+
                       |
                 SANCTUM LEDGER
                       |
       +---------------+----------------+
       |               |                |
    Evidence       Verification     History
       |               |                |
       +---------------+----------------+
                       |
                SANCTUM COMMONS
                       |
       +---------------+----------------+
       |               |                |
  Cooperatives     Shared Assets    Governance
       |               |                |
       +---------------+----------------+
                       |
              ATLAS INTELLIGENCE
                       |
       +---------------+----------------+
       |               |                |
    Economic        Evidence         Decision
     Graph           Graph           Engine
       |               |                |
       +---------------+----------------+
                       |
             MEASURABLE FLOURISHING
```

These are logical capabilities, not a requirement to deploy separate microservices.

### 4.1 Identity Layer

Represent and verify economic participants.

Potential entities:

- Individuals
- Merchants
- Farmers
- Businesses
- Cooperatives
- Organizations
- Assets
- Products
- Community projects

Identity capabilities may include:

- Persistent identifiers
- Verifiable credentials
- Authentication
- Authorization
- Identity verification integrations
- Consent management
- Selective information disclosure
- Delegated authority

The long-term goal is portable economic identity without requiring public exposure of private information.

### 4.2 QR Intelligence Layer

A QR code becomes a machine-readable interface for resolving an economic object or intention.

Potential QR types:

| Type | Purpose |
|---|---|
| Identity QR | Resolve an identity or credential |
| Merchant QR | Identify a merchant |
| Payment QR | Resolve a payment request |
| Asset QR | Identify a registered asset |
| Product QR | Resolve product information |
| Agreement QR | Reference an order or obligation |
| Donation QR | Resolve a contribution request |
| Project QR | Access community project information |

Example transaction intent:

```json
{
  "schema_version": "1.0",
  "type": "payment_request",
  "reference": "snc_req_01H...",
  "merchant": "did:atlas:merchant123",
  "amount": {
    "value": "250.00",
    "currency": "KES"
  },
  "purpose": "produce_purchase",
  "expires_at": "2026-12-01T12:00:00Z"
}
```

This is an illustrative schema, not a finalized protocol specification.

The QR should resolve an appropriately signed and authorized request. Sensitive information and payment credentials must not be embedded in publicly readable QR payloads.

### 4.3 Exchange Layer

Represent the economic relationships surrounding a transaction.

Examples:

- Orders
- Invoices
- Quotations
- Delivery confirmations
- Service agreements
- Payment requests
- Payment instructions
- Settlement references
- Disputes
- Refunds
- Asset transfers

This allows the system to understand more than the movement of money.

### 4.4 Payment and Settlement Layer

Integrate with existing financial infrastructure.

Potential integrations:

- Mobile money
- Banking APIs
- Payment service providers
- Domestic payment networks
- Cross-border payment services
- Future tokenized settlement infrastructure

The guiding principle is payment-rail interoperability.

Sanctum Scan should not require people to abandon existing financial relationships.

Actual fund custody, transfers and settlement should be performed through appropriately authorized financial institutions and providers.

### 4.5 Trust and Verification Layer

Establish reliable evidence about economic interactions.

Potential capabilities:

- Cryptographic signatures
- Transaction verification
- Credential verification
- Audit trails
- Risk assessment
- Fraud detection
- Dispute records
- Provenance tracking
- Device and session security

Trust should not be reduced to a universal numerical score.

Instead, Sanctum should build verifiable histories of identities, credentials, transactions, agreements and fulfilled obligations.

### 4.6 Economic Intelligence Layer

Transform appropriately authorized transaction information into useful intelligence.

Potential capabilities:

- Merchant analytics
- Agricultural market intelligence
- Supply-chain visibility
- Economic relationship mapping
- Transaction pattern analysis
- Liquidity insights
- Opportunity discovery
- Decision support

The objective is to understand how value moves through real economies.

### 4.7 Impact and Regenerative Layer

Connect economic exchange with measurable outcomes.

Potential domains:

- Agriculture
- Food security
- Water
- Climate
- Renewable energy
- Biodiversity
- Education
- Healthcare
- Housing
- Community infrastructure

Impact information should be independently verifiable where possible.

A transaction tagged as supporting a tree-planting project is not automatically evidence that a tree was planted or survived.

---

## 5. Real-World Applications

### 5.1 Agricultural Exchange Network

**Farmer → Aggregator → Transporter → Buyer → Retailer**

Sanctum Scan could help establish verifiable records throughout an agricultural value chain.

Potential capabilities:

- Farmer identity and registration
- Produce batch identification
- Product traceability
- Quantity and price records
- Buyer verification
- Delivery confirmation
- Payment reconciliation
- Agricultural transaction histories
- Supply-chain intelligence

Example:

```text
FARMER REGISTRATION
        |
        v
PRODUCE IDENTIFICATION
        |
        v
BUYER DISCOVERY
        |
        v
ORDER AGREEMENT
        |
        v
DELIVERY CONFIRMATION
        |
        v
PAYMENT SETTLEMENT
        |
        v
VERIFIABLE TRANSACTION RECORD
        |
        v
ECONOMIC INTELLIGENCE
```

Potential long-term opportunity:

Make legitimate agricultural activity more visible to suppliers, cooperatives, financial institutions and agricultural development organizations.

### 5.2 Merchant Economy

Digitally connect local merchants, customers and suppliers.

Potential capabilities:

- Merchant onboarding
- QR-based payments
- Digital receipts
- Customer transaction references
- Supplier records
- Inventory integrations
- Financial reconciliation
- Merchant analytics

### 5.3 Cooperative Finance

Bring digital infrastructure to collective economic organizations.

Potential applications:

- Chamas
- SACCOs
- Farmer cooperatives
- Community organizations
- Producer groups
- Community investment groups

Potential features:

- Membership records
- Contribution tracking
- Group procurement
- Shared asset management
- Expenditure approvals
- Governance records
- Financial reporting
- Authorized institutional integrations

Deposit-taking and lending activities require the appropriate regulatory permissions.

### 5.4 Community Infrastructure

Connect financial coordination with physical infrastructure.

Examples:

- Community water systems
- Irrigation
- Solar installations
- Storage facilities
- Agricultural equipment
- Educational infrastructure

Sanctum could maintain records connecting funding, ownership or control, maintenance, access and measured outcomes.

### 5.5 Diaspora and Productive Investment

Connect people living abroad with legitimate local economic opportunities.

Potential use cases:

- Direct supplier payments
- Agricultural investment
- Productive asset financing
- Community projects
- Verified project contributions
- Local enterprise support

The objective is to complement existing remittance services with better economic context, accountability and transparency.

### 5.6 Agentic Commerce

Explore a future where authorized AI agents can participate in economic coordination.

For example:

An agricultural cooperative could authorize an agent to:

1. Identify a purchasing requirement.
2. Discover approved suppliers.
3. Compare quotations.
4. Verify supplier credentials.
5. Request member approval.
6. Initiate an authorized transaction.
7. Record the outcome.

Human authority, spending limits, permissions and auditability remain essential.

---

## 6. Starting in Kenya

### Building from Local Economic Reality

Sanctum Scan's initial research context is Nakuru County, Kenya, with Mosop and the wider Rongai area providing a potential agricultural and rural-commerce proving ground.

Rather than assuming what local communities need, the project aims to understand existing economic relationships and identify the most meaningful infrastructure gaps.

Potential initial research domains:

| Domain | Research opportunity |
|---|---|
| Agriculture | Farmer-to-market exchange |
| Retail | Merchant and supplier coordination |
| Cooperatives | Collective financial administration |
| Transport | Goods movement and delivery verification |
| Water | Shared infrastructure accountability |
| Energy | Productive energy access |
| Diaspora | Local economic participation |

### Geographic Expansion Hypothesis

```text
MOSOP / RONGAI
       |
       v
NAKURU
       |
       v
KISII / KIBERA / MACHAKOS
       |
       v
KENYA
       |
       v
EAST AFRICA
       |
       v
AFRICA
       |
       v
GLOBAL INTEROPERABILITY
```

The expansion strategy is to prove local utility, establish trust, improve interoperability and gradually expand into additional economic contexts.

---

## 7. Relationship with Atlas Sanctum

Sanctum Scan is part of the larger Atlas Sanctum ecosystem.

Atlas Sanctum is developing a broader vision for regenerative intelligence infrastructure.

Sanctum Scan provides a potential interface for capturing and coordinating real-world economic exchanges.

```text
                     ATLAS SANCTUM
                           |
          +----------------+----------------+
          |                |                |
     OBSERVATORY       SANCTUM SCAN      ATLAS APIs
          |                |                |
     Intelligence       Exchange        Integrations
          |                |                |
          +----------------+----------------+
                           |
                    ECONOMIC GRAPH
                           |
                    EVIDENCE GRAPH
                           |
                 REGENERATIVE DECISION
                       ENGINE
                           |
                COORDINATED ACTION
                           |
                MEASURABLE OUTCOMES
```

### Ecosystem connections

**Atlas Observatory**

Economic observations, trends, opportunities and institutional intelligence.

**Sanctum Scan**

Identity resolution, exchange, transaction evidence and economic coordination.

**Economic Graph**

Relationships connecting people, institutions, money, assets, places and opportunities.

**Evidence Graph**

Provenance, verification, uncertainty and outcome evidence.

**Commons Governance**

Rules and accountability around common resources and collective institutions.

**Regenerative Decision Engine**

Observe → Understand → Decide → Coordinate → Act → Verify → Learn.

---

## 8. Open Development, Licensing and Source Provenance

Sanctum Scan's long-term direction is open, interoperable infrastructure that developers, businesses, institutions and communities can build upon.

That ambition must be matched by clear software ownership, licensing and third-party attribution.

### Current repository status

This repository contains a substantial pre-existing Laravel application with payment, wallet, identity, merchant, agent, QR and provider-integration functionality.

At the time of this update:

- The repository does not contain a root `LICENSE` file.
- `composer.json` declares an MIT license identifier, but that metadata alone does not establish redistribution rights for every file or dependency in this repository.
- The application contains purchase-code and marketplace-product configuration.
- Composer references an externally hosted `reachead/laravel-sdk` package.
- The original project documentation identified the codebase as a versioned QRPay product.

These are material source-provenance signals. The current application must not be assumed to be relicensable or redistributable under Apache-2.0, MIT or another open-source license until the applicable upstream terms, ownership and dependency notices have been reviewed.

**No new root license is introduced by this change.** Do not remove existing notices, redistribute vendor-owned code, or claim that the complete inherited application is open source without documented rights to do so.

### Intended open-source boundary

Subject to provenance review, Atlas Sanctum intends to develop original Sanctum Scan specifications, schemas, SDKs, test fixtures and independently authored modules as openly reusable components.

The existing application can serve as the integration host while new capabilities are introduced incrementally. Each new component should have:

- A documented source and ownership record.
- An explicit license approved for that component.
- Clear dependency and attribution notices.
- Tests for its public contracts.
- Security and privacy review appropriate to its function.

### Commercial services and operational controls

Open software can coexist with commercial hosted services, managed deployments, institutional integrations, support and specialized intelligence.

Regardless of licensing, production customer data, payment credentials, secrets, signing keys and operational security controls must remain protected.

Atlas Sanctum and Sanctum Scan names and marks should be governed separately from software licensing.

See [the source-provenance review](docs/security/source-provenance.md) and [the first architecture decision](docs/architecture/decisions/0001-incremental-adoption.md).
 
---

## 9. Technology Direction

The architecture is intended to remain modular, API-first and suitable for constrained connectivity environments.

### Application Layer

- Laravel
- PHP
- TypeScript
- React
- React Native

### Data Layer

- PostgreSQL
- Redis
- Structured event records
- Geospatial data infrastructure where needed

### Integration Layer

- REST APIs
- Webhooks
- Payment provider adapters
- Mobile money integrations
- Banking integrations
- Identity verification services

### Infrastructure

- Docker
- AWS
- CI/CD
- Object storage
- Monitoring and observability

### Security and Identity

- Role-based and attribute-based access control
- Cryptographic signatures
- Secure authentication
- Consent management
- Encryption
- Audit logging
- Credential verification
- Privacy-aware data sharing

These technologies describe the intended direction, not a claim that every integration or module is already implemented.

---

## 10. Offline-First Economic Infrastructure

Connectivity should not automatically mean economic exclusion.

Sanctum Scan will investigate offline-aware architecture for environments with intermittent connectivity.

Potential capabilities:

- Local transaction preparation
- Cached public merchant information
- Offline identity references
- Signed transaction intents
- Deferred synchronization
- Conflict detection
- Idempotent event processing
- Local receipt generation

Important distinction:

An offline transaction record is not proof of a completed payment.

Actual settlement requires confirmation from the relevant payment infrastructure.

Offline payment authorization, limits, replay prevention and double-spend risks must be addressed explicitly before production use.

---

## 11. Event-Driven Economic Architecture

Economic activity should be represented as structured events.

Illustrative lifecycle:

```text
IDENTITY_RESOLVED
        |
        v
TRANSACTION_INTENDED
        |
        v
AGREEMENT_RECORDED
        |
        v
PAYMENT_AUTHORIZED
        |
        v
PAYMENT_SETTLED
        |
        v
DELIVERY_CONFIRMED
        |
        v
TRANSACTION_RECONCILED
        |
        v
EVIDENCE_RECORDED
        |
        v
INTELLIGENCE_UPDATED
```

Not every economic exchange requires a monetary transaction. The architecture should support non-financial agreements, asset transfers, service delivery and other forms of value exchange.

Events must preserve their provenance, timestamps, references, authorization context and verification status.

---

## 12. Security, Privacy and Responsible Finance

Trust cannot be achieved by simply collecting more personal information.

Sanctum Scan should be developed around:

- Data minimization
- Consent-based information sharing
- Secure identity resolution
- Least-privilege authorization
- Encryption in transit and at rest
- Cryptographic integrity
- Replay protection
- Fraud prevention
- Transparent audit trails
- Secure key management
- Responsible AI
- Data retention controls
- User rights and data protection

Financial histories should not automatically be treated as public reputation profiles.

A person's economic circumstances should not be reduced to a simplistic trust score.

Automated decision systems should be transparent, challengeable and designed to avoid discriminatory outcomes.

Customer data, transaction information, credentials, production secrets and encryption keys must never be included in public repositories.

### Regulatory Considerations

Sanctum Scan is an infrastructure and software initiative, not a claim to operate as a licensed financial institution.

Real-world financial services must be implemented in accordance with applicable requirements, including:

- Kenya's National Payment System framework
- Applicable payment service provider authorization
- Data protection requirements
- Consumer protection
- Anti-money-laundering obligations where applicable
- Digital credit regulation where relevant
- Virtual asset regulation where relevant

The operating model and applicable regulatory obligations must be assessed before launching regulated services.

---

## 13. Development Roadmap

The roadmap is deliberately progressive.

### Phase I — Protocol Foundations

- [ ] Define the Sanctum Scan architecture
- [ ] Establish the economic object model
- [ ] Define QR schema and payload specifications
- [ ] Design identity references
- [ ] Define transaction intents
- [ ] Establish API contracts
- [ ] Define verification and signature requirements
- [ ] Establish repository conventions
- [ ] Publish initial technical documentation

### Phase II — Working Reference Platform

- [ ] User onboarding
- [ ] Merchant onboarding
- [ ] Identity references
- [ ] QR generation
- [ ] QR scanning
- [ ] Transaction intent resolution
- [ ] Order and invoice records
- [ ] Payment integration framework
- [ ] Transaction history
- [ ] Digital receipts
- [ ] Audit records
- [ ] Administrative interface

### Phase III — Real-World Exchange Pilot

- [ ] Select initial economic participants
- [ ] Map an agricultural or merchant value chain
- [ ] Test onboarding
- [ ] Complete a verified exchange
- [ ] Integrate an authorized payment provider
- [ ] Test reconciliation
- [ ] Evaluate dispute handling
- [ ] Measure participant benefits
- [ ] Document findings

### Phase IV — Cooperative and Economic Infrastructure

- [ ] Cooperative management
- [ ] Group financial records
- [ ] Shared asset registry
- [ ] Procurement coordination
- [ ] Delivery verification
- [ ] Offline synchronization
- [ ] Merchant intelligence
- [ ] Economic Graph integration

### Phase V — Regenerative Value Exchange

- [ ] Impact metadata
- [ ] Project registry
- [ ] Outcome verification
- [ ] Productive asset representation
- [ ] Institutional integrations
- [ ] Regenerative finance research
- [ ] Decision intelligence
- [ ] Advanced interoperability

### Phase VI — Global Economic Interoperability

- [ ] Multi-country protocol research
- [ ] Cross-border integrations
- [ ] Multicurrency transaction representation
- [ ] International identity interoperability
- [ ] Institutional partnerships
- [ ] Developer ecosystem
- [ ] Open protocol governance

---

## 14. Research Agenda

Sanctum Scan is guided by questions that extend beyond conventional fintech.

### The Nature of Value

- Can non-financial contributions be represented as verifiable economic records?
- How can productive assets and services become more visible to institutions?
- How can economic value be represented without creating unregulated money?

### Identity and Trust

- Can people carry their economic histories between institutions?
- Can trust be established through verifiable evidence rather than opaque scoring?
- How should disagreements and disputed exchanges be represented?

### Financial Coordination

- Can existing cooperative structures become more efficient through open infrastructure?
- Can verified agricultural activity support better financing decisions?
- How can diaspora participation connect with productive local opportunities?

### Governance

- Who owns the information generated by economic interactions?
- How should shared resources be governed?
- How can open infrastructure avoid creating new monopolies?
- How can communities retain control over their economic relationships?

### Future Research

- How will AI agents participate in authorized economic transactions?
- Can digital infrastructure support interoperable representations of productive assets?
- How can financial infrastructure contribute to ecological regeneration?
- What would an economy organized around human flourishing look like?

---

## 15. Measuring Success

Adoption and transaction volume alone are insufficient measures of success.

Sanctum Scan aims to measure whether it makes real economic activity more trustworthy and useful.

| Dimension | Potential metric |
|---|---|
| Adoption | Active participating businesses and organizations |
| Utility | Completed economic exchanges |
| Reliability | Transaction reconciliation accuracy |
| Efficiency | Time from agreement to settlement |
| Affordability | Total transaction and operational costs |
| Trust | Verification success and dispute resolution |
| Agriculture | Producer net proceeds and delivery reliability |
| Cooperation | Participation and accountable collective expenditure |
| Resilience | Productive investment and improved economic records |
| Impact | Independently supported real-world outcomes |

The central measure:

**Does the infrastructure measurably improve the quality of economic participation?**

---

## 16. First Demonstration

The initial research hypothesis is an agricultural exchange network in Nakuru County, Kenya.

A potential demonstration:

```text
FARMER
  |
  v
PRODUCE IDENTIFICATION
  |
  v
BUYER DISCOVERY
  |
  v
ORDER AGREEMENT
  |
  v
DELIVERY CONFIRMATION
  |
  v
PAYMENT THROUGH EXISTING RAILS
  |
  v
VERIFIABLE RECEIPT
  |
  v
ECONOMIC RECORD
  |
  v
ANALYTICS AND OPPORTUNITY DISCOVERY
```

The demonstration should establish whether the infrastructure improves:

- Transaction transparency
- Payment reconciliation
- Delivery accountability
- Economic recordkeeping
- Participant experience
- Access to useful market information

The first deployment will be determined by local research, participant willingness, institutional requirements and demonstrated need.

---

## 17. Repository Structure

The intended modular structure:

```text
sanctum-scan/
│
├── apps/
│   ├── web/
│   ├── mobile/
│   └── merchant/
│
├── app/
│   ├── Domains/
│   │   ├── Identity/
│   │   ├── Exchange/
│   │   ├── QR/
│   │   ├── Merchants/
│   │   ├── Payments/
│   │   ├── Ledger/
│   │   ├── Trust/
│   │   ├── Cooperatives/
│   │   └── Intelligence/
│   │
│   ├── Events/
│   ├── Policies/
│   └── Shared/
│
├── packages/
│   ├── qr-protocol/
│   ├── sdk/
│   ├── types/
│   └── integrations/
│
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
│
├── infrastructure/
│   ├── docker/
│   ├── aws/
│   └── deployment/
│
├── docs/
│   ├── architecture/
│   ├── protocol/
│   ├── api/
│   ├── security/
│   ├── research/
│   └── governance/
│
├── tests/
│
├── LICENSE
├── CONTRIBUTING.md
├── SECURITY.md
└── README.md
```

This is a proposed organization. Actual implementation should follow demonstrated domain boundaries rather than creating unnecessary abstractions.

---

## 18. Contributing

Sanctum Scan welcomes interdisciplinary contributions.

Areas of interest:

- Software engineering
- Fintech infrastructure
- Payment interoperability
- Identity and authentication
- Cryptography
- Cybersecurity
- Artificial intelligence
- Data engineering
- Agricultural technology
- Cooperative finance
- Digital governance
- Economics
- Climate and impact measurement
- Research and policy

We believe useful economic infrastructure should benefit from collective intelligence and open technical collaboration.

Contributors should consult the repository's contribution guidelines, security policy and licensing terms.

---

## 19. Current Project Status

**Stage:** Research, architecture and early development.

### Implemented protocol slices

**Milestone 002 — Read-only scan reference resolution**

- Versioned opaque references using 256-bit random tokens.
- SHA-256 hashes stored in the database.
- Expiry and revocation support.
- Public resolution API: `GET /api/sanctum/v1/scan/{reference}`.

**Milestone 003 — Merchant identity and QR lifecycle**

- Authenticated merchant reference issuance, listing and revocation.
- Merchant ownership and audit actor attribution.
- QR payloads contain opaque public scan URLs rather than email or phone numbers.
- Public merchant identity is limited to a business name and handle.

**Milestone 004 — Public scan experience and economic resource registry**

- Mobile-first public scan pages at `GET /scan/{reference}`.
- Shared resolver for public web and JSON API surfaces.
- Merchant-owned product and agricultural batch records.
- Merchant API for resource listing, creation, QR rotation and archival.
- QR codes resolve to public scan pages and contain only opaque references.
- Agricultural batch details include category, variety, declared origin and harvest date.
- Append-only provenance events record who supplied each statement and when.
- Provenance is explicitly labelled merchant-declared; no independent verification is implied.
- Quantity and unit are private to the merchant in this release.
- Archiving a resource revokes active scan references.

Merchant resource endpoints:

```http
GET    /merchant-api/merchant/sanctum/resources
POST   /merchant-api/merchant/sanctum/resources
POST   /merchant-api/merchant/sanctum/resources/{resourceId}/scan-reference
DELETE /merchant-api/merchant/sanctum/resources/{resourceId}
```

The public scan page is informational. It does not initiate payments, prove ownership, verify product quality, or establish delivery.

See [ADR 0002](docs/architecture/decisions/0002-scan-reference-resolution.md), [ADR 0003](docs/architecture/decisions/0003-merchant-scan-identity.md), and [ADR 0004](docs/architecture/decisions/0004-public-scan-and-resource-registry.md). Read the [merchant economic resource API guide](docs/api/sanctum-scan-resources.md) for request examples and lifecycle behavior. A [PHP lint and feature-test workflow](.github/workflows/sanctum-scan-php-lint.yml) checks changed PHP files and runs the Sanctum Scan lifecycle feature suite on pull requests.

**Milestone 005 — Economic exchange and transaction evidence**

- Merchant issues an offer for a published product or agricultural batch to a named active, email- and SMS-verified user.
- Buyer can list, accept or decline only offers addressed to their account.
- Merchant can dispatch only an accepted offer.
- Buyer can confirm receipt only after dispatch.
- Exchange records snapshot the resource identity and negotiated quantity, total amount, currency, terms and expiry.
- Append-only events record offer, acceptance, decline, dispatch, receipt and expiry/withdrawal actions with actor attribution.
- Buyer can submit one payment reference with the rail, reference, amount and currency.
- Submitted payment evidence is immutable and always marked unverified in this milestone.
- Payment evidence must match the accepted offer amount and currency.
- No wallet balances, financial transactions, payment orders or settlement states are changed.
- Existing checkout and payment infrastructure remains separate and authoritative for actual payment reconciliation.

Merchant exchange endpoints:

\`\`\`http
GET  /merchant-api/merchant/sanctum/exchanges
POST /merchant-api/merchant/sanctum/exchanges
POST /merchant-api/merchant/sanctum/exchanges/{exchangeId}/dispatch
POST /merchant-api/merchant/sanctum/exchanges/{exchangeId}/cancel
\`\`\`

Buyer exchange endpoints:

\`\`\`http
GET  /api/user/sanctum/exchanges
POST /api/user/sanctum/exchanges/{exchangeId}/accept
POST /api/user/sanctum/exchanges/{exchangeId}/decline
POST /api/user/sanctum/exchanges/{exchangeId}/receipt
POST /api/user/sanctum/exchanges/{exchangeId}/payment-reference
\`\`\`

The first exchange workflow does not reserve stock, execute payments, verify delivery, resolve disputes or reconcile provider settlement. Buyer receipt and merchant dispatch are attributed declarations, not independent proof.

See [ADR 0005](docs/architecture/decisions/0005-economic-exchange-and-transaction-evidence.md) and the [economic exchange API guide](docs/api/sanctum-scan-exchanges.md).


**Milestone 006 — Bilateral dispute and resolution records**

- Either participant can open one dispute after an exchange is accepted.
- Disputes capture a category, opening statement and append-only participant messages.
- Either participant may propose a resolution; only the other participant may accept or reject it.
- Rejected proposals return the dispute to open while preserving the message and event history.
- The initiating participant may withdraw only before the counterparty responds.
- Dispute access is scoped to the buyer and merchant on the exchange.
- Dispute actions append actor-attributed events to the exchange timeline.
- A mutually accepted resolution does not change exchange, payment or settlement state.
- Dispute statements are party-declared claims; no independent adjudication or evidence verification is implied.

Buyer dispute endpoints use `/api/user/sanctum/exchanges/{exchangeId}/dispute`; merchant endpoints use `/merchant-api/merchant/sanctum/exchanges/{exchangeId}/dispute`. Both expose read, open, message, propose-resolution, accept-resolution, reject-resolution and withdraw operations.

See [ADR 0006](docs/architecture/decisions/0006-bilateral-dispute-resolution.md) and the [dispute API guide](docs/api/sanctum-scan-disputes.md).


**Milestone 007 — Private dispute evidence and platform review**

- Either exchange participant can attach PDF, JPEG or PNG evidence (maximum 10 MiB) while the dispute is open.
- Files are stored on the private `local` filesystem disk under application-generated names; no public storage URL is issued.
- Evidence metadata stores the detected MIME type, size, submission actor and SHA-256 digest.
- The same file digest cannot be added to the same dispute more than once.
- Evidence metadata is immutable through its application model; private file contents are checked against the recorded SHA-256 digest before review or download.
- Buyer and merchant downloads are participant-scoped and verify the file digest before streaming.
- Authenticated platform administrators can review pending evidence using the existing admin role-permission and 2FA protections.
- Review records are immutable, bind to the exact evidence digest and record the reviewer, method, decision and summary.
- Review decisions are `verified`, `rejected` or `unable_to_verify`; a new submission starts as `pending`.
- Platform review is an internal administrator assessment, not independent third-party certification, proof of delivery, product quality or payment settlement.
- Evidence submission and review do not change the dispute, exchange, wallet, transaction or settlement state.

Buyer evidence endpoints use `/api/user/sanctum/exchanges/{exchangeId}/dispute/evidence`; merchant endpoints use `/merchant-api/merchant/sanctum/exchanges/{exchangeId}/dispute/evidence`. Each prefix supports listing, upload and participant-scoped download. The admin review queue is available under the configured admin route prefix as `admin.sanctum.evidence.*`.

See [ADR 0007](docs/architecture/decisions/0007-private-dispute-evidence-and-platform-review.md) and the [private evidence API guide](docs/api/sanctum-scan-evidence.md).



**Project:** Atlas Sanctum — Sanctum Scan.

**Initial geographic research context:** Nakuru County, Kenya.

**Long-term direction:** Open, interoperable infrastructure for verified economic exchange.

The architecture and roadmap describe intended capabilities. They should not be interpreted as claims that all components, financial integrations or production services currently exist.

The project will evolve through research, implementation, community participation, testing and real-world deployment.

---

## 20. The Thesis

Traditional fintech asks:

> How do we move money faster?

Sanctum Scan asks:

> How do we make economic exchange more trustworthy, intelligent, inclusive and regenerative?

What if a farmer's productive activity could generate useful and verifiable economic records?

What if a community could coordinate its resources with greater transparency?

What if businesses could establish trusted relationships without becoming dependent on closed platforms?

What if money, identity, goods, services and productive assets could interact through common open infrastructure?

What if technology could help make economic contributions visible while preserving human agency and community ownership?

These are the questions behind Sanctum Scan.

We are exploring a future in which digital infrastructure does not merely automate transactions.

It helps people and institutions coordinate their capabilities, steward their resources and create lasting value.

---

# Sanctum Scan

### Scan the world. Understand the exchange. Verify the value. Coordinate the future.

**An initiative by Atlas Sanctum**

*Setting hearts and minds above.*

[Website](https://atlassanctum.me) · [GitHub](https://github.com/atlasanctum)