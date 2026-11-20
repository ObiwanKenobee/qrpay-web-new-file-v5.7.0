# M029 — Atlas Care Implementation Contract

**Status:** Draft — gated; not approved for implementation  
**Prepared:** 2026-11-20  
**Depends on:** M028 architecture acceptance (ADR 0023) and resolution of CI runner incident #42  
**Target repository:** `ObiwanKenobee/qrpay-web-new-file-v5.7.0`  
**Proposed first implementation:** One partner-led, non-cash Care coordination workflow inside the existing Laravel modular monolith

> **Authority notice:** This document is an implementation contract proposal, not permission to write migrations or runtime code. ADR 0023 remains Proposed. No Care schema, model, controller, route, API, authentication change, dependency, payment integration or production data collection is authorized until the gates in Section 2 are satisfied and this contract is explicitly approved.

## 1. Purpose and outcome

M029 translates an accepted M028 architecture into the smallest testable Care workflow that can demonstrate a real operational outcome without expanding the platform prematurely.

The system should help an accountable partner coordinate a bounded need with an available non-cash capability, record a human-approved intervention and commitment, preserve attributable evidence of delivery, and evaluate what can actually be claimed about the result.

The success criterion is not the number of tables, screens, partners or AI features. It is a pilot in which an authorized person can safely complete the workflow, participants can understand and challenge decisions about them, and an independent reviewer can distinguish declarations from verified facts.

## 2. Mandatory authorization gates

All gates are conjunctive. A missing gate blocks schema work.

| Gate | Required evidence | Approving owner |
|---|---|---|
| G0 — M028 accepted | ADR 0023 status changed to Accepted with decision record; revised M028 specification approved | Accountable repository maintainers |
| G1 — Pilot selected | Named pilot operator, participating partner, bounded service, geography, participant group and accountable service owner | Pilot operator and maintainers |
| G2 — Privacy and data governance | Controller/processor roles, purpose, reviewed processing basis, participant notice, rights channel, data minimization, retention/erasure and any required assessment | Qualified privacy/data-protection reviewer and pilot operator |
| G3 — Safeguarding readiness | Named safeguarding lead, safe referral/escalation, complaint and challenge path, incident ownership, trained human capacity, and context-specific procedures | Qualified safeguarding owner and pilot operator |
| G4 — Authority and security | Approved account-to-partner mapping, representative-grant lifecycle, query scoping, exceptional-access controls and separation-of-duties rules | Maintainers and security reviewer |
| G5 — Operational evidence | Baseline, service-capacity assumptions, delivery evidence, outcome measurement, reviewer independence and failure/complaint handling | Pilot operator and measurement reviewer |
| G6 — CI recovery | Both required workflow jobs receive real runners, execute recorded steps and pass on the exact reviewed commit; no gate is waived | Repository maintainers |
| G7 — M029 contract approval | Domain invariants, state transitions, access matrix, data contract, event semantics, schema plan and test plan reviewed together | Accountable maintainers and relevant qualified reviewers |

**Current state:** G0–G7 are not evidenced as complete. The CI runner issue is open. This draft does not imply that a pilot partner, privacy approval or safeguarding capacity exists.

## 3. Proposed first-pilot boundary

### 3.1 Preferred narrow workflow

Subject to selection by a real pilot operator, the recommended first workflow is:

1. An authorized coordinator records a clearly scoped need using the approved assisted process.
2. A partner declares a capability it may make available, including its limits and availability.
3. The system presents feasible matches using published, understandable criteria and explicit missing information.
4. A named human reviews the proposal and records the decision and reason.
5. An accountable contributor and authorized coordinator record a specific non-cash commitment.
6. Delivery is declared by an attributable actor and supported by proportionate evidence.
7. A distinct reviewer records the review outcome where the pilot requires independent verification.
8. The operator records an outcome observation, its source and limitations, and any justified follow-on action.

A need is not an accepted case; a match is not a commitment; a commitment is not delivery; delivery is not proof of impact; and an observed change is not automatically proof of causation.

### 3.2 Pilot constraints

The actual pilot must be selected and documented before implementation. Until then:

- Prefer a bounded, partner-led workflow with non-cash goods, skills or services.
- Do not collect live personal or sensitive case data.
- Do not use children or adults at risk in a live digital workflow until the qualified safeguarding owner has approved context-specific procedures and operating capacity.
- Do not assume the commercial M025 value-chain test is an Atlas Care pilot.
- Do not expose individual needs or participants through public QR references.
- Do not represent the platform as an emergency-response, clinical, statutory case-management or service-delivery provider.

If the selected pilot cannot meet these constraints, return to architecture and operational review rather than silently expanding scope.

## 4. Domain contract

M028 proposes five aggregate roots and a cross-cutting event log. M029 must preserve this boundary unless an accepted ADR explicitly changes it.

| Proposed aggregate | Responsibility | Invariants to freeze before schema |
|---|---|---|
| Need | A purpose-bound, assessable request or opportunity | No public visibility by default; pseudonymous subject reference; purpose/source recorded; sensitive details remain outside ordinary records; state changes require authorized human action |
| Partner | An accountable organization or community entity | Verification status is evidence-qualified; organization identity does not imply authority of an individual representative |
| Capability | A partner-declared resource, service or skill | Declaration is not proof of ownership or availability; quantity, unit, window and constraints must be explicit where relevant |
| Intervention | A bounded, approved response to one or more needs | Named coordinator; explicit purpose and approval; linked needs and partner scope; status changes attributable |
| Commitment | A specific promise by an accountable contributor | Contributor and authority identified; contribution, quantity/unit and due window clear; fulfilment distinct from promise; withdrawal/dispute represented explicitly |
| Care event log | Cross-cutting append-only record of consequential events | Not a sixth aggregate; contains minimum non-identifying metadata; appended atomically with the state change it describes; supports correction, retention and erasure rules |

Do not introduce a generic polymorphic graph, universal ontology, generic workflow engine or extra aggregate merely to anticipate future product categories.

### 4.1 Aggregate ownership and transaction boundaries

Before coding, the detailed contract must state:

- Which application service owns each command and invariant.
- Which aggregate is authoritative for each state change.
- Which records must be created or updated in the same database transaction.
- Which event is appended for each consequential transition.
- What happens when validation, concurrency control or event persistence fails.
- Which external effects, if any, occur only after commit.
- Whether an outbox is genuinely required; do not add one without a concrete delivery/reliability requirement.

No consequential event may claim that a state change committed if the transaction rolled back. Event history must not become a second writable copy of domain state.

## 5. Identity, privacy and data contract

### 5.1 Subject and identity separation

- Operational Care records use a pseudonymous subject reference, not a beneficiary name or phone number.
- Prefer the originating partner to retain direct identity/contact data where that partner can safely support participant continuity and rights requests.
- If Atlas must retain an identity mapping, it requires a separate, explicitly authorized boundary, field-level access, access audit, retention and rights-handling design.
- Never embed direct identifiers, sensitive narratives, precise household coordinates or raw evidence contents in general Need, Intervention, Commitment or event records.
- A pseudonymous reference is not proof of consent, lawful basis or authority.

### 5.2 Purpose, notice and consent

The implementation contract must distinguish:

1. The specific service purpose.
2. The parties' controller/processor roles.
3. The processing basis determined by qualified review.
4. Participant notice and the rights channel.
5. Consent, only where it is relied upon or separately required, including what was explained, affirmative choice, time, actor, method, version and withdrawal.

Explicit, informed participant consent is required before collecting or sharing sensitive case details under the M028 safeguard. This does not replace determination of the processing basis or safe safeguarding procedures. Refusal or withdrawal from optional participation must not be used to deny unrelated entitlements.

### 5.3 Retention and erasure

Before migrations, define retention by data class for operational records, identity mappings, event metadata, evidence files, exports and backups. Specify deletion, anonymization or unlinking behaviour; exception and legal-hold authorization; review dates; and how the process is audited. “Append-only” must never become indefinite retention of personal data.

### 5.4 Public projection

Public QR publication is outside the first participant workflow unless a separate project-publication use case is approved. If included later, the contract must define publisher authority, explicit approval, a versioned allowlisted projection, unpublish/revocation, cache invalidation, safe contact channel, re-identification review and clear labelling of declared versus independently reviewed information.

No public beneficiary lookup, public need QR or public case search is permitted.

## 6. Authorization contract

Authentication remains on the repository's existing Passport guards and account surfaces. The product name “Sanctum Scan” does not justify replacing Passport with Laravel Sanctum.

A partner representative grant must be explicit, time-bounded and bound to:

- The authenticated account.
- The partner.
- Permitted actions.
- Need/intervention/resource scope.
- Issuer and evidence basis.
- Effective period and expiry.
- Current status and revocation timestamp/reason.

Authorization is checked on every read and write against the current grant. Expiry or revocation blocks new access immediately; historical actions retain the actor and authority context that applied when they occurred. No authority is inferred from an email domain, merchant account, organization name or caller-supplied actor ID.

The final M029 access matrix must define participant, coordinator, partner representative, reviewer/verifier, administrator and automated-process permissions; list denied actions; constrain bulk queries; and specify separation of duties. Exceptional access must be justified, time-limited and audited. Admin status alone is not a reason to read restricted case data.

## 7. State-machine contract

M028 supplies candidate states, not an approved transition model. M029 must replace candidate vocabulary with explicit, reviewed transition tables before any model or endpoint is written.

For every transition, specify:

- Source and target state.
- Permitted initiating actor and required current authority.
- Preconditions and required evidence/acknowledgement.
- Human approval, where applicable.
- Whether the transition is reversible and how correction is represented.
- Concurrency and duplicate-request behaviour.
- Event type and required metadata.
- Participant notification and complaint/challenge path.
- Failure behaviour and resulting persisted state.

No generic endpoint may accept an arbitrary status value. Idempotency and concurrency rules must be explicit for commands that may be retried.

## 8. Evidence and decision contract

Evidence classes remain distinct:

- Participant-reported statement.
- Partner-declared action or delivery.
- Source record or document observed.
- Platform/operator review.
- Independent attestation under a defined mandate.
- Outcome observation or evaluation.

A hash establishes neither the truth of a claim nor the independence of a reviewer. Each evidence record must identify its source, actor, time, scope, version and review status, while avoiding direct personal data in general event payloads.

Matching is feasibility assistance, not a deservingness score. The first implementation must not create a universal scalar trust/eligibility score, hidden composite score, automatic denial or silent ranking of individuals. A proposal may expose relevant capability criteria, operational constraints, missing information and uncertainty. Any urgency triage uses published pilot-approved criteria and an accountable human decision with reasons and a challenge route.

AI is not required for the first workflow. If introduced in a later decision, it may summarize or suggest; it cannot decide eligibility, human worth, commitment, fulfilment or disbursement.

## 9. API and application boundary

After approval, implement only the smallest command/query surface required by the selected pilot. M029 approval must identify each intended operation, actor, authorization check, validation rule, response classification, audit event and error behaviour.

The preferred placement remains within the existing Laravel modular monolith, using repository conventions and existing integration boundaries. Do not create microservices, new authentication guards, new front-end frameworks, new databases, queues or geospatial extensions without a separate evidence-backed decision.

No API path, controller, model or service is authorized by this draft. Exact classes and route names follow repository inspection and the approved design, not this document's illustrative language.

## 10. Schema and migration design gate

The schema is deliberately **not specified here**. Table names, columns, indexes and migration order must be proposed only after G0–G7 are complete.

The later schema review must include:

- Aggregate ownership and foreign-key relationships.
- Tenant/partner/subject query boundaries.
- Unique constraints and idempotency keys where needed.
- State and concurrency representation.
- Pseudonymous subject-reference treatment.
- Explicit separation of restricted identity data.
- Event metadata minimization and append-only constraints.
- Evidence-file references and retention/erasure.
- Indexes justified by named pilot queries.
- MySQL compatibility with the deployed version.
- Forward migration, rollback/down plan, data-loss analysis and clean-install path.

Every migration must be reviewed as a standalone artifact before execution. Do not place unrelated legacy data repair or financial changes in the Care migration set.

## 11. Verification plan

The final approved test matrix must include at minimum:

| Area | Required verification |
|---|---|
| Domain invariants | Invalid aggregate transitions and inconsistent relationships are rejected |
| Authorization | Cross-partner reads/writes denied; revoked and expired grants stop access immediately |
| Identity privacy | Direct identifiers do not appear in general Care records, public projections, logs or event payloads |
| Consent and notice | Purpose, notice, processing basis and consent are represented distinctly; withdrawal is handled correctly |
| State machines | Only permitted actor/state transitions succeed; concurrent or replayed commands are safe |
| Event integrity | Failed transactions do not leave success events; consequential changes and events commit atomically |
| Evidence provenance | Participant declaration, partner declaration, review and independent attestation remain distinguishable |
| Human challenge | Correction, appeal and complaint paths are accessible and attributable |
| Safeguarding | No live workflow opens without the named owner and approved referral/escalation process |
| Public QR | No beneficiary/case data is resolvable; if project publication is approved, unpublish and cache rules are tested |
| Retention and erasure | Data classes, files, references and backup lifecycle follow approved schedules |
| Database | Clean MySQL migration, rollback and re-migration pass |
| Regression | Existing QR scan, exchange, authentication, wallet and payment flows remain unchanged |
| CI | Both existing required workflow jobs execute on real runners and pass; failed/no-runner status is never waived |

Tests must use synthetic fixtures. No production participant data belongs in test logs, fixtures, screenshots or repository artifacts.

## 12. Non-goals for M029

Unless a separate accepted ADR explicitly changes scope, M029 excludes:

- Cash collection, donations, disbursement, escrow, lending, deposits or pooled balances.
- Writes to legacy transactions, wallets or payment-order tables.
- Payment-provider integrations or QR payment-flow changes.
- Public beneficiary discovery or beneficiary-specific QR references.
- AI-based deservingness, eligibility, trust or prioritization scores.
- Offline consequential actions or synchronization.
- New database platforms, microservices, generic ontology or universal graph.
- Unvalidated expansion to multiple pilot operators or sectors.
- Claims of verified impact based solely on registration, pledge, payment or self-reported delivery.

## 13. Operational measures

The pilot operator must define baseline and denominators before launch. Report only measures that can be responsibly collected and explained:

- Time from a valid submission to a human review.
- Share of accepted commitments fulfilled within the agreed window.
- Delivery records with proportionate supporting evidence.
- Corrections, challenges and complaints received and resolved.
- Unauthorized-access attempts and access-review findings.
- Participant understanding and voluntary feedback.
- Resource gaps identified and actions taken.
- Outcome observations, including limitations and alternative explanations.

Do not optimize for raw need volume, public beneficiary counts, high intervention throughput or AI recommendation acceptance. Safeguarding, privacy and challenge quality are not tradeable against throughput.

## 14. M029 deliverables

1. Approved pilot charter and operating responsibility map.
2. Frozen aggregate invariants and state-transition tables.
3. Approved authorization matrix and partner-grant lifecycle.
4. Approved data classification, identity separation, notice/consent and retention/erasure specification.
5. Event and transaction-boundary contract.
6. Evidence, human-decision and challenge contract.
7. Public project projection contract only if separately in scope.
8. Reviewed schema proposal and migration/rollback plan, created only after authorization gates pass.
9. Test matrix mapped to each invariant and risk.
10. Operational runbook, incident/safeguarding contacts and outcome measurement plan.

## 15. Definition of ready and definition of done

### Ready for implementation

M029 is ready only when G0–G7 are evidenced in the repository, each approving role and date is recorded, the chosen pilot is bounded, and maintainers explicitly authorize the first implementation pull request. The schema proposal is approved separately before migration files are created.

### Done

The first implementation is done only when all approved invariants are implemented, the test matrix passes on MySQL and the existing CI workflow, existing financial and authentication behaviour is unchanged, privacy and safeguarding procedures are operational, and the pilot operator signs off the end-to-end workflow.

A merged PR, green unit tests alone, populated database, QR scan or signed commitment is not proof of successful human or community outcomes.

## 16. Immediate next actions

1. Resolve Issue #42 by restoring GitHub Actions runner execution through repository/organization settings and confirming real runner assignment.
2. Obtain accountable maintainer, privacy/data-protection and safeguarding review of M028; keep ADR 0023 Proposed until recorded.
3. Identify and qualify the first pilot operator and use case; then complete and approve this M029 contract before any Care migration or runtime implementation.

---

**Current decision:** M029 contract prepared for review only. **Implementation gate: CLOSED.**
