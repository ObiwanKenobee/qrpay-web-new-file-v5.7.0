# ADR 0023 — Atlas Care Domain Boundary and Human-Dignity Guardrails

**Status:** Proposed — review required before acceptance  
**Date:** 2026-11-19  
**Owners:** Atlas Sanctum maintainers  
**Depends on:** ADR 0001 (incremental adoption), ADR 0004 (public scan and resource registry), ADR 0005 (economic exchange and transaction evidence), ADR 0007 (private dispute evidence and platform review), ADR 0008 (independent verifier mandates and attestations), ADR 0016 (deterministic verification gates), ADR 0017 (settlement slice and evidence-based milestone status)

## Context

The user-supplied research brief, “The Orione Principle: Engineering a Civilization of Charity,” proposes **Atlas Care — The Human Flourishing & Regenerative Action Network**. It translates seven principles associated with the charitable witness of St. Luigi Orione into a systems philosophy: human flourishing as a design objective; attention to underserved people; trust-centered institutions; participation by people experiencing hardship; distributed contribution; long-term stewardship; and human dignity as a non-negotiable constraint.

The brief identifies six capabilities:

1. Human needs intelligence.
2. Regenerative intelligence.
3. Community connectivity.
4. Regenerative resource allocation.
5. Accountability and verification.
6. Regenerative development.

It proposes the operational cycle:

`DISCOVER → UNDERSTAND → CONNECT → MOBILIZE → VERIFY → REGENERATE`

It also states that AI should recommend and assist, not autonomously decide who deserves assistance, and that sensitive information, explicit consent and protection against publicly searchable beneficiary records are essential.

### Existing repository evidence

The repository is an established Laravel modular monolith. The architecture baseline describes Laravel 12, MySQL as the deployment database, Laravel Passport API guards, session guards for the web panels, and Laravel Sanctum installed but unused for route protection. Existing QRPay user, merchant, agent, wallet and payment capabilities must be preserved rather than duplicated.

Sanctum Scan already provides reusable foundations:

- Opaque, hashed scan references with expiry and revocation in `app/Services/SanctumScan/ScanReferenceIssuer.php`.
- A presentation-safe public resolver in `app/Services/SanctumScan/ScanReferenceResolver.php`.
- Merchant-owned product and agricultural-batch resource records in `app/Models/SanctumScanResource.php`.
- Bilateral exchange records and append-only events in `app/Models/SanctumScanExchange.php` and related services.
- Private evidence, platform review and independent-verification capabilities governed by existing ADRs.

There is an important implementation distinction: `ScanReferenceIssuer::RESOURCE_TYPES` includes `community_project`, but the current `ScanReferenceResolver` has specialized loading and projection branches for merchants, products and agricultural batches. The presence of a resource-type constant does **not** establish a complete community-project registry or a safe public project lifecycle.

No Atlas Care model, service namespace or Care migration exists on the examined `main` branch. M028 must therefore define a new domain boundary rather than describe a Care feature as already implemented.

## Decision

### 1. Atlas Care is a bounded domain within the existing Laravel application

Atlas Care will begin as a bounded domain within the existing Sanctum Scan modular monolith. It will not start as a separate application, microservice, wallet or database.

The proposed service boundary is:

`app/Services/SanctumScan/Care/`

The proposed model naming convention is:

`App\Models\SanctumScanCare*`

These paths are architectural conventions for a later implementation milestone, not files created or authorized by M028.

Care services must depend on explicit contracts and selected existing Sanctum Scan capabilities. They must not reach into unrelated legacy controllers, helper functions, wallet models or payment-provider implementation details.

### 2. The Care domain has six logical aggregates

The initial domain model consists of the following logical aggregates. This decision does not prescribe final SQL columns or migration order.

| Aggregate | Responsibility | Principal relationships |
|---|---|---|
| Care Need | A consented, assessable description of a need or opportunity requiring a response | May be addressed by one or more interventions |
| Care Partner | An organization or accountable community entity participating in the network | Owns or administers capabilities; may sponsor or deliver interventions |
| Care Capability | A declared resource a partner can make available | Belongs to a partner; may be proposed for an intervention |
| Care Intervention | A bounded, human-approved plan responding to one or more needs | Links needs, partners, commitments and outcome observations |
| Care Commitment | A specific promise to contribute a good, skill, service, time, equipment, transport or other support | Belongs to an intervention and an accountable contributor |
| Care Event | An append-only record of a consequential action, declaration, review or outcome observation | References its subject and authenticated actor |

A sensitive beneficiary identity or case file is **not** a public property of a Care Need. The implementation design must separate identifying/contact information from broadly accessible need, intervention and reporting records. Where feasible for an initial partner-led pilot, a partner-held case reference should be used instead of collecting a second copy of the person's identity data.

Do not add a generic polymorphic graph or a large universal ontology merely to make every possible participant and resource type fit this first model.

### 3. The six-stage operational cycle is the domain workflow

The product workflow must preserve the research brief's sequence:

| Stage | Meaning | Minimum output |
|---|---|---|
| Discover | Understand community needs and existing capabilities | A submitted need or declared capability with source and recording context |
| Understand | Assess urgency, context, constraints and available resources | A human-reviewed assessment with reasons and unknowns |
| Connect | Identify relevant people, institutions, skills and resources | An explainable match or referral proposal |
| Mobilize | Coordinate interventions and accountable commitments | An approved intervention and explicit participant commitments |
| Verify | Establish what was delivered and what outcomes are supported | Attributed declarations, source evidence and appropriately scoped reviews |
| Regenerate | Strengthen sustainable local capabilities and repeat the cycle | An outcome review, learning record and any justified follow-on action |

A submitted need is not automatically accepted, a match is not a commitment, a commitment is not delivery, and delivery is not proof of a sustained outcome. Each distinction must be visible in both the domain model and the user interface.

### 4. Public QR references are for approved public project information, not people in need

Sanctum Scan's opaque reference pattern may be reused for a public community-project page after a separate resolver contract and publication policy are reviewed.

A public QR scan may show only an approved project-level summary, such as the project purpose, accountable publishing organization, broad location, public progress summary and public contact channel.

A public QR reference must not expose:

- A named or individually identifiable beneficiary.
- A household-level need or case narrative.
- Phone numbers, private contact details or identity documents.
- Exact household coordinates or other precise location data that could expose a person.
- Medical, disability, family-welfare or other sensitive case details.
- Private commitments, payment references, uploaded evidence or reviewer notes.

A need, beneficiary or private case reference must not be made public merely because the scan-reference issuer can issue opaque tokens. Public resolution remains a presentation boundary, not an authorization mechanism.

### 5. Existing authentication and authority boundaries remain authoritative

M028 does not replace Passport with Laravel Sanctum and does not add an `auth:sanctum` route group.

The implementation must use the existing authenticated user, merchant and administrator surfaces where they are appropriate. It must not invent a new authentication guard or treat an organization name, email address or submitted actor ID as proof of authority.

Partner participation requires an explicit representative and scope of authority. A partner representative may act only for the organization and intervention to which that representative has been granted access. Existing merchants may participate as partners only where the actual organization and representative relationship is established; a merchant account is not automatically an NGO, church, public authority or beneficiary case worker.

For the first prototype, a coordinator-assisted process may be used where the current account model cannot represent a partner role safely. Do not bypass existing account verification, KYC, PIN, 2FA or authorization controls to make the pilot appear operational.

Administrator access remains least-privilege and subject to the existing admin role and two-factor controls. A general administrator role must not become a reason to expose every private case field.

### 6. The existing financial boundary is unchanged

M028 does not authorize cash collection, charitable disbursement, escrow, lending, deposits, pooled balances, wallet movement, refunds, payment-provider calls or new financial ledger entries.

Sanctum Scan's existing rule remains in force: Care services must not write to legacy `transactions`, `user_wallets`, `merchant_wallets`, `agent_wallets` or `payment_order_requests`.

The first Care prototype should coordinate non-cash capabilities and commitments. Any later financial-contribution flow requires a separate decision defining the responsible legal entity, authorized payment route, reconciliation evidence, custody of funds, refunds, fees, accounting and applicable regulatory review. A pledge or commitment must never be represented as a completed transfer.

### 7. Care evidence is attributed and classified; it is not collapsed into a universal trust score

Care events must distinguish at least:

- Participant-reported statement.
- Partner-declared action or delivery.
- Source document or record observed.
- Platform/operator review.
- Independent attestation within a defined mandate.
- Outcome observation or evaluation.

A hash can help detect changes to a stored or exported payload; it does not prove the underlying claim is true. A participant's confirmation, a partner's dispatch declaration, an administrator's review and an independent attestation are different evidence classes and must remain distinguishable.

Corrections and reassessments should be represented through traceable new records rather than silent edits to consequential history. The implementation must also define how data-subject rights, retention and deletion are honored; append-only event design must not become an excuse to retain unnecessary personal data indefinitely.

### 8. AI is decision support, never an authority over human worth

AI may later assist with:

- Summarizing submitted needs and source material.
- Suggesting categories and identifying missing information.
- Finding capabilities that may be relevant to a need.
- Identifying geographic or operational resource gaps.
- Summarizing intervention progress and outcome evidence.

AI must not autonomously decide who deserves assistance, deny a person access to support, infer human worth, or authorize a commitment or disbursement.

Any future recommendation must expose its source records, material assumptions, limitations and model/version context. A named human decision-maker must accept, reject or modify consequential recommendations, with the decision and reason recorded. Sensitive personal attributes must not be used to construct an individual deservingness or universal trust score.

### 9. Accessibility, low-bandwidth use and offline operation are design constraints

The Care interface must be mobile-first, accessible and usable with constrained connectivity. It should support plain-language content, assisted workflows and a path for users who cannot independently operate a smartphone.

Offline capture and later synchronization are future capabilities, not something created by M028. An offline record must be visibly pending synchronization and must not be represented as a server-confirmed commitment, verified delivery or completed payment. Conflict handling, device authorization, consent capture and replay protection require a separate implementation design.

### 10. Keep the current persistence and deployment stack until evidence requires a change

The initial Care domain must use the repository's existing Laravel and MySQL deployment architecture. M028 does not authorize PostgreSQL, PostGIS, Redis, a vector database, a new queue service, a new frontend framework or microservices.

Coarse location may be sufficient for initial partner matching. Exact household coordinates are not a default requirement. A geospatial extension should be considered only after a validated use case, privacy assessment and query requirements justify it.

## Consequences and limitations

### Positive

- Atlas Care gains a bounded place in the existing product architecture without a rewrite.
- The Orione research brief becomes a set of explicit engineering and governance constraints.
- Existing scan, evidence, verification and audit foundations can be reused selectively.
- The distinction between a need, a match, a commitment, a delivery and an outcome is preserved.
- The design prohibits public beneficiary discovery and autonomous AI allocation.

### Trade-offs and limitations

- This ADR creates no working Care workflow, schema, route, dashboard, model, integration or partner relationship.
- The proposed aggregates and naming conventions must be validated during detailed design.
- The current public resolver does not yet implement a community-project lifecycle.
- The current authentication model does not include a fully specified Care-partner role.
- The first pilot still requires a real implementation partner, participant notice, access and retention procedures, operational support and privacy review.
- No legal determination about data-controller roles, lawful basis or DPIA obligations is made by this ADR.

### Alternatives considered

1. **Create a separate Atlas Care application or microservice now.** Rejected because the use case and operational boundary are not yet validated, and it would duplicate identity, access, evidence and deployment capabilities.
2. **Put all Care records into the existing generic resource registry.** Rejected because private needs, partner authority, commitments and intervention lifecycles have distinct access and governance rules.
3. **Make individual beneficiary QR profiles publicly resolvable.** Rejected because opaque identifiers do not prevent disclosure or guarantee that publication is safe.
4. **Introduce AI beneficiary ranking or autonomous resource allocation.** Rejected because it conflicts with the human-dignity and human-decision guardrails in the source brief.
5. **Replace the API authentication stack with Laravel Sanctum.** Deferred; the existing repository uses Passport API guards. The product name is not a reason to change authentication.

## Review conditions before acceptance

This ADR remains **Proposed** until reviewers approve:

1. The Care boundary and six logical aggregates.
2. The separation of private need/case data from public project information.
3. The partner representative and delegated-authority model.
4. The evidence classes and the distinction between declarations, reviews, attestations and outcomes.
5. The consent, lawful-basis, retention, correction and deletion design for the selected pilot.
6. The human-approval boundary for matching and AI assistance.
7. The unchanged financial boundary.
8. The accessibility and low-bandwidth assumptions for the initial use case.

No database migration or runtime implementation may be started on the authority of this Proposed ADR alone. Acceptance requires a reviewed M028 engineering specification and a recorded approval decision.
