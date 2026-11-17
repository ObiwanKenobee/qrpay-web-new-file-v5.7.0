# M025 — Pilot Participant and Value-Chain Scope

**Status:** Proposed scope; partner and participant commitments not yet confirmed  
**Date:** 2026-11-17  
**Depends on:** M002–M024 capabilities, subject to M024/M024-A review and verification  
**Branch:** `sanctum-scan/m025-nakuru-potato-pilot-scope`

## 1. Decision

Define a small, bounded first pilot around **Irish-potato batch exchanges in one production/aggregation catchment in Nakuru County, Kenya**.

The pilot tests whether Sanctum Scan makes an existing produce transaction easier to identify, agree, evidence, reconcile and review. It is not a county-wide digitisation programme, a commodity marketplace, a payment product, an agronomy system or proof that a crop is safe, certified, correctly graded or delivered.

**Geographic boundary:** one contiguous ward/market catchment in Nakuru County, chosen after participant discovery. Do not select a ward, collection centre or market until a real seller and repeat buyer confirm the route and the operating location.

**Commodity boundary:** fresh Irish potatoes; each digital resource represents one identifiable seller-controlled batch. Record variety as a participant declaration; do not assume a particular variety.

**Transaction boundary:** seller issues a fixed-quantity, fixed-total-price offer to a named buyer in KES; buyer accepts or declines; seller declares dispatch; buyer declares receipt; buyer submits a payment reference; an authorised operator reviews the available evidence and records the settlement decision. Payment takes place outside Sanctum Scan.

## 2. Why this is the preferred first candidate

The Kenya National Bureau of Statistics' *National Agriculture Production Report 2025* identifies Nakuru as the leading county in Irish-potato production for 2024, reporting 476,876 tonnes (provisional). The report also states that 90% of Kenyan potatoes are grown on smallholdings below 0.5 acres. This makes a smallholder-facing Nakuru pilot a defensible discovery candidate, not proof that any particular ward, cooperative or buyer is ready to participate.

Nakuru already has digital potato-data and extension activity. A Government Advertising Agency report dated 29 September 2026 describes the County Government/CIP VarScout work on crop-variety, pest, disease, weather, market and field-observation information. Sanctum Scan should not duplicate this agronomic and extension-data function. Its distinct test is the bilateral commercial exchange record: who offered what, to whom, on what terms, what each party declared, what evidence was submitted and what an authorised reviewer decided.

Potential discovery channels—not confirmed partners, endorsements or commitments—include the Nakuru County agriculture/extension ecosystem, the National Potato Council of Kenya (NPCK), producer organisations, collection/aggregation businesses and repeat produce buyers. Confirm current mandates, introductions and willingness directly before using any organisation's name in public pilot materials.

## 3. Participant model

### Minimum viable cohort

| Participant | Proposed count | Role in the pilot | System representation |
|---|---:|---|---|
| Producer-seller or producer-owned aggregation business | 1–2 | Controls and is authorised to sell the listed batch; creates batch resources and issues offers | Existing merchant account |
| Repeat produce buyers | 2 | Receive named offers, accept/decline, confirm receipt and submit payment references | Existing user accounts |
| Field facilitator / data steward | 1 | Helps participants understand the workflow, checks paper/source records and records operational friction | No new privileged application role in this milestone |
| Reconciliation operator | 1–2 | Reviews the private case history and uses existing settlement decision controls under least-privilege admin permissions and 2FA | Existing admin account with explicit route permissions |

Target 8–12 exchange records during a four-week live period, following a two-week readiness/discovery period. This is a learning sample, not a statistically representative sample or a promise of production volume.

### Participant eligibility gates

Before onboarding, confirm for each seller and buyer:

1. The legal/person-in-control identity and authority to make the offer or accept it.
2. The existing merchant/user account path, KYC and authentication prerequisites, device availability, connectivity and language support.
3. A real trading relationship and a specific buyer demand or purchase process; do not create synthetic offers solely to populate the system.
4. The physical batch, its owner or authorised seller, the unit of measure, the weigh/measurement practice, collection point and intended delivery/collection responsibility.
5. Willingness to retain ordinary invoices, weigh slips, dispatch notes and external payment records for the agreed pilot period.
6. A plain-language explanation of what the scan page makes public, what remains private, and what is only a participant declaration.

A producer organisation may act as the seller only where it can show authority to sell the specific batch. The pilot must not silently combine goods owned by multiple farmers into one supposedly attributable batch. M002–M024 do not implement member-level ownership shares, pooled-lot allocation, stock reservation or multi-seller settlement.

**Qualification rule:** if the selected farmers cannot use the existing merchant account path, do not bypass KYC or misrepresent a group administrator as every farmer. Pause and choose a properly authorised seller entity, or scope a later participant/account capability before live transactions.

## 4. End-to-end pilot workflow

| Step | Actor | Required record / check | Evidence class |
|---|---|---|---|
| 0. Enrol | Facilitator + participant | Identity/account check, authority, pilot notice, privacy explanation and baseline process measurement | Administrative onboarding record |
| 1. Register batch | Seller merchant | `agricultural_batch`, display name, category, declared variety, origin label, occurrence/harvest date where known, quantity and unit | Merchant-declared resource |
| 2. Agree offer | Seller merchant | Named existing buyer, resource, exact quantity/unit, total amount, KES currency, collection/delivery terms and expiry | Merchant offer; buyer has not yet accepted |
| 3. Accept | Named buyer | Review and accept or decline the fixed terms | Buyer declaration |
| 4. Dispatch | Seller merchant | Declare dispatch/availability and optional dispatch reference/note | Merchant declaration; not independent proof |
| 5. Receive | Named buyer | Confirm receipt after dispatch and optionally record a concise receipt note | Buyer declaration; not independent proof |
| 6. Submit payment reference | Buyer | Submit rail, reference, amount and currency matching the accepted offer | Buyer-submitted, initially unverified evidence |
| 7. Reconcile | Authorised operator | Review the relevant case and independently available source material under an agreed manual procedure; verify, reject or leave unresolved | Separate administrator decision linked to the exact evidence version |
| 8. Review | Facilitator + participants | Compare actual record quality, effort, unresolved discrepancies and participant value with the baseline | Pilot evaluation record |

No step in this workflow initiates a payment or moves funds. Participants make any payment through their independently agreed existing channel. Sanctum Scan records references and decisions; a SHA-256 fingerprint is an audit binding, not a payment-provider confirmation or proof of transfer.

## 5. Product fit and operational constraints

The current protocol supports the narrow transaction above:

- Merchant-owned `agricultural_batch` resources with variety, origin label, date, private quantity and unit.
- Fixed-quantity merchant offers to named existing users, with amount and three-letter currency.
- Buyer acceptance/decline, merchant dispatch declaration, buyer receipt declaration and buyer payment-reference submission.
- Append-only exchange events and versioned payment evidence.
- Admin settlement decisions and the M024 read-only reconciliation projection.

It does **not** currently provide:

- Multi-producer ownership or contribution allocation inside one batch.
- Inventory reservation/decrement or oversell protection.
- Standardised potato grade, size, weight-tolerance, quality, food-safety or pesticide-residue certification.
- A logistics/warehouse actor, proof-of-delivery integration or independent weighing-device integration.
- M-Pesa/bank statement ingestion, payment-provider verification API, automatic transfer or refund.
- A farmer cooperative ledger, pooled settlement, credit/insurance underwriting or price discovery.
- Agronomy advice, variety recommendations, weather alerts or field-level crop monitoring.

### Mandatory operating controls

1. Use a distinct resource for each physical batch with a stable seller reference code. Do not re-use one batch resource for unrelated harvest lots.
2. Because stock is not reserved or decremented by the application, maintain a separate signed/controlled batch allocation sheet and permit only offers whose quantities remain available. Do not place concurrent offers against the same physical quantity.
3. Use one agreed unit (normally kg) for each batch/offer and record the actual weighing method. Any conversion or bag-count approximation must be stated, not silently treated as exact weight.
4. The seller must be authorised to sell the batch. The platform's merchant declaration does not establish title.
5. Do not publicly expose a farmer's personal contact details, exact homestead coordinates, private quantity, payment reference or private evidence.
6. Do not mark payment verified/settled on the basis of a screenshot or buyer reference alone where the agreed manual procedure cannot corroborate it. Leave evidence unverified or unresolved rather than manufacture certainty.
7. Do not treat merchant dispatch or buyer receipt as independent proof. Record any third-party weighbridge, transport or delivery evidence separately with its source and limitations.
8. A dispute is not a routine evidence-upload folder. Use the dispute process only for an actual participant disagreement and preserve the distinction between statements, submitted evidence, reviewer findings and settlement decisions.

## 6. Data protection and participant safeguards

Before live data collection, the pilot owner must identify the data controller/processor roles, lawful basis for each processing purpose, access permissions, retention period, processor arrangements and contact for data-subject requests. The ODPC's 2026 guidance says consent, where relied on, must be specific, informed and freely given, with accessible mechanisms to record consent, withdrawal and rights requests. It also states that a DPIA is required before processing likely to create high risk to data-subject rights and freedoms. Obtain qualified privacy advice on whether a DPIA is required for the actual pilot design; this scope document is not a legal determination.

Minimum safeguards:

- Provide a short, plain-language participant notice in a language participants understand.
- Collect only identity and transaction information necessary for the agreed exchange and evaluation.
- Explain separately what is public scan information, private participant information and operator-only dispute/payment evidence.
- Do not publish participant names, phone numbers, exact household locations or individual volumes without a separate assessed purpose and lawful basis.
- Restrict reconciliation access to named admins with explicit route permissions and 2FA; keep access reviewable.
- Set a documented retention/deletion schedule for pilot copies, field worksheets and exported records.
- Establish a simple correction, access, objection and complaint channel before enrolling participants.
- Train the facilitator not to promise guaranteed payment, certified produce, legal title, verified delivery or increased income.

## 7. Baseline, metrics and decision thresholds

Collect a baseline from at least five recent comparable transactions using existing paper/phone processes before participants use Sanctum Scan. Record actual elapsed time, number of hand-offs, missing fields, quantity/price discrepancies, payment-reconciliation effort and dispute frequency. Do not reconstruct baseline values from memory after the pilot begins.

| Metric | Definition | Proposed pilot threshold |
|---|---|---|
| Real usage | Exchange records representing actual agreed trade, not demonstrations | 8–12 records; at least two repeat buyers |
| Batch completeness | Share of live offers whose source batch has variety, origin label, harvest/occurrence date where known, quantity and unit recorded | ≥90%; missing/unknown values labelled, never fabricated |
| Agreement completeness | Share of accepted offers with named counterparties, exact quantity/unit, total amount, currency and explicit terms | 100% |
| Event accountability | Share of accepted pilot exchanges with actor-attributed accept/dispatch/receipt/payment-evidence events where those stages occurred | 100% of applicable stages |
| Evidence discipline | Share of payment decisions tied to the exact submitted evidence version and a recorded manual review basis | 100%; zero unsupported verified/settled decisions |
| Inventory integrity | Physical batch quantity allocated across live offers compared with the controlled batch sheet | Zero known oversells |
| Reconciliation effort | Median staff time from receipt of payment reference to a documented review outcome, compared with baseline | Establish baseline first; report change without claiming causation from a small sample |
| Participant utility | Participants who would voluntarily use the record for another real transaction, with reasons | At least 70% of interviewed participants; report numerator and denominator |
| Harm/safeguards | Confirmed privacy incidents, unauthorised access, coercion or materially misleading claims | Zero unresolved high-severity incidents |

Do not use a small pilot to claim improved farmer incomes, reduced post-harvest loss, better food safety, climate impact, creditworthiness or county-wide efficiency. Those outcomes require a longer design, baseline, counterfactual and independent measurement.

## 8. Pilot timebox and stage gates

| Stage | Proposed timebox | Exit condition |
|---|---|---|
| Discovery and partner qualification | 2 weeks | One catchment, authorised seller(s), two real buyers, account eligibility and agreed operating procedure documented |
| Dry-run / shadow baseline | 1 week | Five comparable existing transactions measured; one synthetic walkthrough clearly marked as training only |
| Live pilot | 4 weeks | 8–12 real exchanges attempted; weekly reconciliation and participant check-ins |
| Evaluation and decision | 1 week | Metrics, incidents, participant feedback, operating costs and unresolved risks reviewed; go/no-go decision recorded |

These are planning targets, not a delivery commitment or evidence of partner availability. No live participant data should be collected before the readiness gate is passed.

## 9. Cost and dependency model

No budget or commercial commitment is approved by M025. Estimate the pilot only after identifying the catchment and partners. The cost sheet should separately quote:

- Field discovery and participant onboarding/training.
- Local transport, airtime/data and low-connectivity support.
- Field facilitation and participant time compensation, where appropriate.
- Scales/weigh slips, printing and minimal recordkeeping materials.
- Privacy/security review, consent materials and incident handling.
- Support and reconciliation operator time.
- Any partner venue or aggregation-site costs.

Do not buy hardware, commission a mobile app, integrate a payment rail or build an analytics dashboard for this pilot. Reuse the current web/API workflow and paper fallback; record every manual workaround as a product gap.

## 10. Risks, stop conditions and unresolved decisions

| Risk / unresolved point | Control or decision gate |
|---|---|
| Seller lacks authority over the crop or group lots are mixed | Verify authority and retain lot-level attribution; stop rather than overstate traceability |
| Merchant account onboarding excludes the intended farmer | Test eligibility before recruitment; no KYC bypass or account sharing |
| Multiple offers exceed physical stock | Single controlled batch allocation sheet; no concurrent allocation of the same quantity |
| Weight, grade or quality disagreement | Agree physical measurement and terms before offer; do not claim standardised grading/certification |
| Payment reference cannot be corroborated | Keep evidence unverified/unresolved; no false settlement |
| Existing VarScout or extension data duplicated | Focus on exchange records; seek alignment and avoid copying agronomy datasets without permission |
| Personal/location/payment data exposed | Data minimisation, private defaults, permission checks, retention and incident plan |
| Network or device constraints exclude participants | Test actual devices/connectivity; keep assisted and paper fallback, then record additional support cost |
| Pilot looks positive only because sample is selected | Record inclusion criteria, failed offers, withdrawals and denominator for every metric |

**Go/no-go before live transactions requires written confirmation of:**

1. Named lead seller and two genuine buyers, with authority and account eligibility confirmed.
2. One selected collection/delivery route and one batch measurement practice.
3. A no-oversell allocation procedure and a dispute escalation contact.
4. A manual payment-evidence review protocol with an authorised reviewer and a clear “leave unverified” path.
5. Participant notice, lawful-basis assessment, data access/retention plan and DPIA screening.
6. Baseline measurements and cost ceiling approved by the pilot owner.

## 11. M025 deliverables and acceptance

M025 is a scope-and-readiness milestone, not a production feature release.

- [x] Candidate commodity and county selected using published evidence.
- [x] Participant roles and existing system representations mapped.
- [x] End-to-end exchange workflow bounded to implemented capabilities.
- [x] Unsupported capabilities and manual controls stated explicitly.
- [x] Baseline, metrics, timebox, cost categories, risks and go/no-go gates defined.
- [ ] Lead seller and two buyer participants confirmed.
- [ ] Catchment/market route and operating procedure agreed.
- [ ] Privacy/legal review and participant notice approved.
- [ ] Baseline collected and pilot budget approved.

**Implementation rule:** do not create new code or schema merely to make the pilot look complete. First verify that real participants can execute the bounded workflow safely with the existing product. Raise the next code milestone only from an observed, consequential gap.

## 12. Evidence and references

1. Kenya National Bureau of Statistics, *National Agriculture Production Report 2025*, §3.3.1, Irish Potatoes. The 2024 county production figures are marked provisional; Nakuru is reported at 476,876 tonnes. https://www.knbs.or.ke/wp-content/uploads/2025/10/National-Agriculture-Production-Report-2025.pdf
2. Government Advertising Agency / Kenya News Agency, “Nakuru turns to digital tools to boost potato farming,” 29 September 2026. Describes Nakuru County/CIP VarScout work and its field-data/extension focus. https://www.gaa.go.ke/index.php/nakuru-turns-digital-tools-boost-potato-farming
3. National Potato Council of Kenya, Nakuru county page. Useful as a discovery directory for potato-sector organisations and resources; not evidence of a committed partnership. https://npck.org/nakuru-4/
4. Office of the Data Protection Commissioner, *Guidance Notes for Data Protection Policies* (2026), including lawful basis, consent, data-subject rights and DPIA guidance. https://odpc.go.ke/wp-content/uploads/2026/07/ODPC-%E2%80%93-Guidance-Note-for-Data-Protection-Policies.pdf
