# M025-A — Participant Qualification and Baseline Discovery

**Status:** Desk research and field instruments prepared; no outreach or fieldwork recorded  
**Date:** 2026-11-17  
**Depends on:** M025 — Nakuru potato pilot scope (PR #32)  
**Branch:** `sanctum-scan/m025-a-participant-qualification-baseline`

## 1. Purpose and non-claims

M025-A converts the M025 pilot hypothesis into a repeatable field-discovery procedure. It must answer four questions before any live pilot:

1. Is there one real seller-to-buyer trading route for Irish potatoes that occurs repeatedly in one Nakuru catchment?
2. Can the seller lawfully and practically offer a distinct batch, with a credible measurement and no-double-allocation procedure?
3. Can two real buyers participate using the existing account and exchange workflow without account sharing or a KYC bypass?
4. Does a five-trade baseline show a material recordkeeping, hand-off or reconciliation problem that Sanctum Scan can help address?

This PR provides desk-research leads, qualification rules, interview scripts and blank data-capture templates. **It does not mean that any organisation has been contacted, any farmer or buyer recruited, any transaction sampled, or any partner qualified.** The named institutions and programmes below are referral channels to approach, not partners, endorsers or committed participants.

Do not start live transactions, collect personal identity documents, create test offers representing real trade, or introduce payment integrations as part of M025-A.

## 2. Desk-research findings and referral channels

### 2.1 Primary discovery channels

| Channel | What current public evidence supports | Use in discovery | What must be rechecked |
|---|---|---|---|
| Nakuru County agriculture / Farmer Field and Business School (FFBS) network | On 16 July 2026, the County reported FFBS activity in Kuresoi South, Kuresoi North, Molo and Nakuru Town West, with participating groups cultivating potatoes alongside other crops. | Ask the County agriculture/extension team for a voluntary introduction to one currently active potato producer group and its extension contact in a catchment where sellers already trade. | Current ward/group activity, potato ware-trade volumes, willingness to introduce participants and whether the group has a real buyer relationship. |
| National Potato Council of Kenya (NPCK) | NPCK reported that its 5 August 2026 Nakuru field day, organised with the County Government, brought together more than 440 farmers and value-chain stakeholders. Its published Value Chain Support remit includes actor linkages and market support. | Request an introduction to one producer-side contact and two independent ware-potato buyer contacts operating on the same route. Ask for current process information, not a farmer list. | Whether the contacts are still active, whether they handle ware potatoes rather than seed potatoes, current trading route, and willingness to participate. |
| FIPS Africa | Its 22 October 2026 publication describes an independent August 2026 midline evaluation of potato interventions in Nakuru, Meru and Nandi involving 673 respondents across the three counties. The Nakuru findings discuss Village-Based Advisors and Decentralised Seed Multipliers. | Possible referral route to local farmer-network or extension contacts. Keep this distinct from proof of a buyer channel: the reported work focuses on seed systems and farmer networks. | Current Nakuru operating areas, whether the contact has consent to make an introduction, and whether a referred producer sells ware potatoes to recurring buyers. |

### 2.2 Geographic shortlist — not a final catchment selection

Start discovery conversations in **Kuresoi South, Kuresoi North and Molo**, because the County's July 2026 FFBS update identifies potato-growing farmer groups in those sub-counties. Keep **Nakuru Town West** as a secondary location for an alternative producer or market-side referral. The actual catchment must follow the seller's existing route to a real buyer; do not choose a ward because it is convenient or because a programme operates there.

The NPCK Nakuru county profile lists potato production activity across nine sub-counties and describes the potato chain as involving producers, market agents, transporters, processors, vendors and retailers. The page also contains historical project and production details, including programmes with 2017–2022 periods. Use it as sector context, not proof of a currently active organisation, volume, market or buyer.

### 2.3 Secondary referral leads — verify before relying on them

- **Nakuru Smallholder Farmers Association (NASFA):** an Egerton Seed Potato CARP+ partner page describes NASFA as an umbrella organisation for farmer groups and farmer organisations across Nakuru County. Confirm that it is currently active, identify its current authorised contact and ask whether it can introduce a producer who already sells ware potatoes.
- **Nakuru Potato Cooperative Union (NCPU):** an Egerton Seed Potato CARP+ project page describes historical support for formation of the Union. Verify present legal/operating status, current officers, membership and actual marketing activity before treating it as a live referral source.
- **KALRO / Egerton / Baraka Agricultural College:** use as optional technical context for seed/production distinctions and referral introductions. These institutions are not substitutes for a commercial seller or buyer and should not be described as pilot partners without written confirmation.

### 2.4 Market price context

NPCK's public market-price page reported Nakuru ware potatoes at KES 53/kg in its 12 November 2026 update. Treat that as a dated indicative market signal only. It is not a farm-gate quote, agreed purchase price, guaranteed price, or valid comparison unless variety, grade, bag weight, volume, market level, delivery terms and date are comparable. Record actual negotiated prices from the baseline transactions rather than using this public figure to set pilot prices.

### 2.5 Initial outreach order

1. Contact Nakuru County agriculture/extension or the FFBS coordination contact and ask for a voluntary introduction to an active potato producer group in one of the shortlisted areas.
2. Contact NPCK Value Chain Support / Marketing and Cooperatives channels and request introductions to current ware-potato buyers who already purchase from the proposed producer route.
3. Ask the candidate seller to identify buyers from their five most recent real trading attempts; do not independently select convenient buyers who have no existing relationship.
4. Interview each buyer separately. Check that the seller and buyer describe the same recent transaction, quantity unit, delivery point and payment/reconciliation steps.
5. Only then decide whether one contiguous catchment and one repeat seller-to-buyer route exist.

**Public contact starting point for NPCK:** +254 712 338 633; npck@npck.org. These are publicly listed NPCK contact details, not a direct contact for a specific Nakuru farmer or buyer. County public switchboard: (051) 221 4142; request the relevant agriculture/extension office. No outreach has been sent from this repository work.

## 3. Minimum discovery sample and rules

### 3.1 Target cohort

- One primary seller who controls, or is demonstrably authorised to sell, an identifiable ware-potato batch.
- Two independent, real buyers who have purchased from that seller or the seller's producer-owned aggregation business.
- One field facilitator who is not the seller's or buyer's undisclosed agent.
- One named reconciliation operator, with a second operator as backup, who can use explicit admin route permissions and 2FA.

A second seller is optional and should only be added if the same collection/market route and operational workflow are genuinely comparable. Do not inflate the cohort with non-trading observers.

### 3.2 Five-transaction baseline

Review the **five most recent consecutive trade attempts** for the proposed seller-to-buyer route, ideally within the preceding 60 days. Include unsuccessful, cancelled, disputed or incomplete attempts if they occur in that consecutive sequence; do not select only clean, successful transactions. If fewer than five exist in 60 days, extend to 90 days and flag the period extension. If five still cannot be found, record the gap and do not claim a reliable baseline.

For the five records:
- Target at least one trade involving each of the two proposed buyer accounts.
- Prefer at least three transactions between one seller and one recurring buyer to test repeatability.
- Capture source-record type and confidence, not a copy of the source document.
- Record a counterparty pseudonym, not the person's name, phone number, account number or identity-document number.
- Separate historical declarations, document observations and buyer/seller corroboration. Do not reclassify participant statements as independent proof.

### 3.3 What counts as evidence during discovery

| Evidence class | Acceptable discovery treatment | Not acceptable |
|---|---|---|
| Seller/buyer interview | Record the statement, role, date and whether another party corroborated it | Presenting a recollection as independently verified fact |
| Existing business record | Inspect with permission; record only record type, relevant non-identifying fields and whether the values matched | Copying invoices, ID documents, bank/mobile-money screenshots or contact details into the repository |
| Physical batch/measurement | Observe the lot boundary and weighing/packaging practice, with permission | Claiming certified grade, ownership or exact weight from appearance |
| Payment reconciliation | Record stated payment channel, evidence type, time to confirmation and who checked it | Recording PINs, account identifiers, full mobile-money reference strings or making a payment based on a screenshot |
| Buyer/seller corroboration | Ask each party separately to describe the same event and note matching/conflicting fields | Asking one party to complete the other party's answers |

## 4. Mandatory qualification gates

Every gate must pass. A high score cannot compensate for a failed gate.

| Gate | Pass condition | Fail / pause condition |
|---|---|---|
| Seller authority | Seller demonstrates control of the specific batch or written/otherwise documented authority from the owners to sell it. | Unclear ownership, pooled produce with no allocation, or an intermediary unable to demonstrate authority. |
| Real counterparties | At least two independent buyers are identified from actual recent trading, not invented for the pilot. | No repeat buyer or no permission to approach the buyer. |
| Transaction recency | Five consecutive trade attempts can be sampled within 90 days. | No actual recent potato trade or only handpicked success stories. |
| Account eligibility | Seller and buyers can use the existing merchant/user account path and meet its current onboarding/authentication prerequisites. | Shared logins, one person impersonating multiple parties, or a request to bypass KYC/verification. |
| Physical quantity control | Batch is distinguishable; unit, measurement/packaging basis and manual allocation record are agreed. | Unreconciled quantities or inability to prevent the same stock being promised twice. |
| Voluntary participation | Each participant can refuse or withdraw without losing market access, services, credit, benefits or programme support. | Coercion by a leader, employer, buyer, funder, public official or programme gatekeeper. |
| Privacy readiness | Data controller/processor roles, purposes, lawful basis, access, retention, notices and DPIA screening are reviewed before personal data collection. | Unclear data ownership, unapproved copying of documents, unbounded retention or public exposure of private details. |
| Operational safety | Participants understand that scan, dispatch, receipt and payment evidence are records/declarations—not independent proof of quality, title, delivery or funds transfer. | Participants expect guaranteed payment, certification, insurance, credit or a dispute adjudication service that is not delivered. |

## 5. Participant qualification scorecard

Score the proposed seller–buyer route, not the charisma or seniority of one contact. A facilitator records a short evidence note for every score.

| Dimension | Weight | Full-score evidence |
|---|---:|---|
| Real and recent ware-potato trading | 20 | Five consecutive recent trade attempts in the defined route, with dates and trade stages corroborated. |
| Repeat buyer relationship | 15 | Two independent buyers identified, both with a real recent purchase relationship to the seller. |
| Seller authority and batch boundary | 15 | Named seller has authority; physical lot can be separated and identified; no ambiguous shared ownership. |
| Record availability | 15 | The majority of baseline fields can be checked against contemporaneous business records without retaining copies. |
| Quantity and measurement discipline | 10 | Agreed unit, scale/bag basis, tare/weight method and discrepancy procedure exist. |
| No-double-allocation control | 10 | Seller has a simple stock/allocation log and a person responsible for keeping offers within available batch quantity. |
| Existing digital/account readiness | 5 | Accounts can be used individually; device and connectivity path is practical, with an assisted fallback that does not involve credential sharing. |
| Participant availability and inclusion | 5 | Seller and both buyers can attend separate onboarding/interview sessions in an accessible language and format. |
| Review and privacy readiness | 5 | Named operator, voluntary participation, privacy notice and data-handling owner are accepted in principle. |
| **Total** | **100** | |

**Decision rule:** 75/100 or higher **and all mandatory gates pass** = qualified for a controlled readiness review. 60–74 = conditional; write one concrete remediation and re-score. Below 60 = do not recruit into the pilot. Any failed mandatory gate is a no-go regardless of points.

Do not average the scores of three different parties to hide a weakness. Record seller and buyer readiness separately, then score the proposed route.

## 6. Seller interview guide

Start with the participant's normal business process. Do not begin by pitching Sanctum Scan or suggesting that the technology will improve prices.

1. Tell me about the last five times you sold ware potatoes. Where did each sale start and end?
2. Who controlled each lot and who was authorised to agree the sale?
3. How do you separate one harvest/collection batch from another? What happens when produce from multiple farmers is combined?
4. What variety, quantity and unit did you describe to the buyer? How was weight measured? Are bags weighed individually, sampled, assumed or converted?
5. Who decides grade/quality, and what happens when the buyer disagrees?
6. How was the price agreed: per kg, per bag, per lot, or another basis? Which costs/deductions changed the net amount?
7. How did the buyer place or accept the order? What record shows the final agreed terms?
8. How did you record dispatch or collection? Who was present? What evidence remains?
9. How did you know payment had been made and received? Who checked it, through which source and how long did that take?
10. Describe the most recent quantity, quality, delivery or payment disagreement. What resolved it?
11. Which records could you voluntarily let a facilitator inspect without taking copies? What information must remain private?
12. Would you be willing to use your own account and device for a real exchange workflow? What would prevent that?

## 7. Buyer interview guide

Interview each buyer separately from the seller.

1. Describe your last five ware-potato purchases from this seller or source.
2. How frequently do you buy, in what volumes and at which collection/delivery point?
3. What batch identifiers, variety, grade or quality attributes matter to your purchase?
4. Who checks the quantity and how is the measurement recorded?
5. How do you agree price, packaging, transport, deductions and rejection/return conditions?
6. What causes a price or quantity to change after the initial agreement?
7. What record do you retain for order, dispatch, receipt and payment?
8. How do you reconcile the payment with the seller's record? Who checks it and how long does confirmation normally take?
9. What are the most common reasons a purchase is delayed, disputed, rejected or partly accepted?
10. Would you confirm receipt in a digital record after your own inspection? What must the record not imply?
11. Which information may a public scan show, and which must remain private?
12. What would make this workflow useful enough to repeat? What would make you stop using it?

## 8. Baseline measurement procedure

### 8.1 Interview and observation sequence

1. Obtain the approved participant notice and record a voluntary participation decision before collecting personal data.
2. Interview seller and each buyer separately; do not let one party answer on behalf of another.
3. Select the five most recent consecutive trade attempts using the sequence rule in §3.2.
4. Inspect permitted source records in place. Do not photograph, upload, copy or retain the full document unless the data owner and privacy reviewer have approved a specific purpose and handling method.
5. Fill one row per trade attempt in `templates/m025-a-baseline-trades.csv`.
6. Compare the seller's and buyer's accounts for agreed quantity/unit, price, dispatch/collection, receipt and payment confirmation. Record mismatch; do not resolve it by guessing.
7. Reconstruct elapsed time only where the dates/times are evidenced. Mark estimates and recall-only values.
8. Review the results with both parties separately and confirm which observations may be used in a de-identified pilot report.

### 8.2 Baseline metrics and definitions

| Metric | Calculation |
|---|---|
| Agreement completeness | Trade attempts with evidenced counterparty, quantity, unit, total price, currency and terms ÷ attempts sampled. |
| Batch-field completeness | Attempts with a traceable batch/lot description, variety where known, unit, quantity and measurement basis ÷ attempts sampled. |
| Dispatch/receipt record coverage | Attempts with a retained record or separately corroborated statement for each applicable stage ÷ attempts where that stage occurred. |
| Payment reconciliation time | Elapsed time from the participant-reported payment evidence becoming available to the business confirming the amount/reference; use evidenced timestamps only and record unknowns. |
| Reconciliation effort | Person-minutes spent locating, comparing and confirming records for one trade attempt. |
| Discrepancy incidence | Attempts with quantity, grade, price, delivery or payment mismatch ÷ attempts sampled; list mismatch categories separately. |
| Reconciliation hand-offs | Number of distinct people/organisations required to resolve the trade record, not including the research interviewer. |
| Record retrieval effort | Time required to locate the agreed order, dispatch/collection, receipt and payment records. |
| Known stock allocation exception | Any documented instance where the same physical quantity was offered to more than one buyer or could not be reconciled to available stock. |

Report numerator, denominator, missing values and evidence confidence. Do not turn five observations into a population estimate or a claim of impact. With such a small sample, the output is a workflow baseline and a product decision aid.

### 8.3 Evidence confidence

- **A — Document checked:** contemporaneous business record inspected with permission and relevant field matched.
- **B — Two-party corroborated:** seller and buyer separately give matching accounts, but no contemporaneous record was inspected.
- **C — Single-party recall:** one participant remembers the event; no independent corroboration.
- **U — Unknown/conflicting:** missing field or contradictory accounts.

Do not combine C/U values with A values in one “verified” total. Show evidence confidence by metric.

## 9. Data minimisation and privacy controls

The discovery pack is not a place to store participant contact details, identity copies, financial credentials or raw transaction documents.

- Before personal data processing, identify the controller/processor roles, purpose, lawful basis for each processing activity, recipients, security, retention and data-subject rights contact.
- The ODPC's 2026 guidance states that the lawful basis for each processing activity should be established before processing; it describes consent as specific, informed and freely given where consent is relied upon. Do not assume that a signature or a general pilot agreement resolves every lawful-basis question.
- Complete and document DPIA screening before fieldwork. The ODPC says a DPIA is required before processing where the operation is likely to create high risk to data-subject rights and freedoms. Obtain qualified privacy advice where the result is unclear.
- Use participant codes (for example `SELLER-01`, `BUYER-01`) in the templates. Keep any re-identification key separately under the data controller's approved access and retention process; never commit it to Git.
- Do not collect or store national ID copies/numbers, personal phone numbers, exact home coordinates, mobile-money PINs, bank account details, full payment references, screenshots, private chat exports or participant contact lists in the repository or baseline CSV.
- Record only the payment channel category and whether the participant says evidence was checked; never ask for credentials or take control of an account.
- Audio/video recording is off by default. If a recording is genuinely necessary, obtain separate purpose-specific approval and consent, document retention/access, and provide a non-recorded participation path.
- Participation must not be tied to access to extension services, credit, input support, market access or any programme benefit.
- If a participant withdraws, follow the approved data-subject procedure; do not promise deletion where legally required transaction records must be retained without first checking the controller's obligations.

## 10. Field-ready templates and decision record

- `templates/m025-a-participant-screening.csv` — one de-identified row per prospective participant/route.
- `templates/m025-a-baseline-trades.csv` — one de-identified row per consecutive historical trade attempt.
- The facilitator keeps a separate, access-controlled contact/outreach log outside GitHub if necessary. Do not place phone numbers, email addresses, names or re-identification keys in repository files.

At the end of discovery, produce a short decision record with:

1. Catchment and reason selected, with evidence date and source.
2. Seller authority and batch-control check.
3. Two qualified buyers and the actual existing relationship to the seller.
4. Five baseline trade attempts, the 60/90-day sampling window and missing/conflicting fields.
5. Baseline metrics with denominators and confidence levels.
6. Score per participant and per seller–buyer route; all mandatory gates pass/fail.
7. Device, language, connectivity and assisted-use observations.
8. Data controller/processor, lawful-basis, notice, retention and DPIA-screening approvals.
9. Unresolved risks, cost estimate and operational owner.
10. Written go/no-go decision before live data or transactions are introduced.

## 11. M025-A acceptance checklist

- [x] Desk-research referral channels documented with dates and limitations.
- [x] Geographic shortlist framed as discovery areas, not a prematurely chosen catchment.
- [x] Mandatory participant gates and a weighted qualification scorecard defined.
- [x] Separate seller and buyer interview guides prepared.
- [x] Five-consecutive-trade baseline sampling method defined.
- [x] Baseline metrics, evidence confidence and de-identification rules defined.
- [x] Blank participant-screening and transaction-baseline templates added.
- [ ] County/NPCK/other referral introductions obtained.
- [ ] Seller and two real buyers interviewed and qualified.
- [ ] Five baseline trade attempts reviewed and recorded.
- [ ] Catchment, privacy review and go/no-go decision approved.

**Current result:** discovery instruments are ready for controlled use. Actual participant qualification and baseline collection have not started.

## 12. Public sources

1. Nakuru County Government, “County, CARE Kenya Review Farmer Field and Business School Model Progress Under CASCADE Project,” 16 July 2026. Reports FFBS activity in Kuresoi South, Kuresoi North, Molo and Nakuru Town West, with potato-growing groups. https://www.nakuru.go.ke/nakuru-county-care-kenya-review-farmer-field-and-business-school-model-progress-under-cascade-project/
2. National Potato Council of Kenya, “NPCK 2026 Field Days reach potato farmers in Nakuru, Laikipia and Nandi,” 5 September 2026. Reports the 5 August Nakuru farmer field day with more than 440 farmers and value-chain stakeholders. https://npck.org/npck-2026-field-days-reach-potato-farmers-in-nakuru-laikipia-and-nandi/
3. NPCK, “Nakuru County” profile and sector contacts. The page includes older programme and production information; use current contact details only after reconfirmation and do not treat old project durations as active. https://npck.org/nakuru-4/
4. FIPS Africa, “Midline evaluation: potato interventions in Meru, Nandi, and Nakuru counties,” 22 October 2026. Reports an August 2026 evaluation involving 673 respondents across the three counties; Nakuru results concern supported seed systems. https://fipsafrica.org/publication/midline-evaluation-potato-interventions-in-meru-nandi-and-nakuru-counties/
5. Office of the Data Protection Commissioner, *Guidance Notes for Data Protection Policies* (2026), especially lawful bases and DPIA sections. https://odpc.go.ke/wp-content/uploads/2026/07/ODPC-%E2%80%93-Guidance-Note-for-Data-Protection-Policies.pdf
6. Office of the Data Protection Commissioner, *Guidance Note on Data Protection Impact Assessment* (2024). https://www.odpc.go.ke/wp-content/uploads/2024/02/ODPC-Guidance-Note-on-Data-Protection-Impact-Assessment-1.pdf
7. Egerton University, Seed Potato CARP+ project pages on NASFA and cooperative/producer-organisation support. Treat as historical referral context pending direct confirmation of current status. https://seedpotato.egerton.ac.ke/index.php/partners/nasfa and https://seedpotato.egerton.ac.ke/index.php/about/carp-seed-potato-project
