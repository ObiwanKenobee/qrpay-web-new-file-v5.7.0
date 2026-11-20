# M028 — Architecture Review and Acceptance Blockers

**Review date:** 2026-11-19  
**Scope:** ADR 0023 and `M028-orione-atlas-care-domain-foundation.md` as merged by PR #40  
**Review outcome:** **Revise before acceptance**  
**Implementation authority:** None. No runtime work or database migration is authorized by this review.

## 1. Repository and review facts

- PR #40, `M028: Define Orione-inspired Atlas Care architecture`, is closed and merged into `main`.
- The merged commit is `40f20247ec6628c2b2e3d0ed1fec0f32d14d29f8`.
- GitHub reports no submitted PR reviews and no inline review threads for PR #40.
- ADR 0023 remains **Proposed**. Merge status is not an architecture acceptance decision.
- The merge commit's two GitHub Actions checks, `G1 G2 G3 G7 G8 — static gates and full suite` and `G4 G5 — MySQL migrations, route registry, concurrency`, both report `failure`. They completed almost immediately; the connector returned no step detail or usable job logs. The cause is **unresolved**; do not describe this as a PHP/test failure or a pass.
- The Vercel status on the pre-merge head commit also reported deployment failure. This is a preview/deployment status, not evidence that the Laravel application tests passed or failed.

## 2. Review findings

### R1 — Aggregate boundary is overstated

**Severity:** High  
**Finding:** The original six-item list called the append-only Care Event an aggregate, although it is a cross-cutting audit record. Treating an event log as a normal aggregate risks unclear transaction ownership and an unbounded object model.  
**Resolution:** Define five proposed aggregate roots—Need, Partner, Capability, Intervention and Commitment—and a cross-cutting append-only event log. The event log records committed state changes; it does not own domain state.

### R2 — Participant identity and case ownership are underspecified

**Severity:** Critical before live personal data  
**Finding:** The original design says identity should be separated and a partner-held reference is preferred, but it does not define the subject reference, identity mapping owner or conditions under which Atlas may retain identity data.  
**Resolution:** Use a pseudonymous subject reference in operational Care records. Prefer the originating partner to retain identity/contact data when that partner can safely manage participant continuity and rights requests. If Atlas must hold identity data, require a distinct, separately authorized identity boundary with field-level access, audit and retention. Do not add direct identifiers to general Care records.

### R3 — Consent is too easily conflated with processing authority

**Severity:** Critical before live personal data  
**Finding:** The initial ADR described a “consented” need while the specification separately deferred lawful-basis review. These ideas must not be collapsed.  
**Resolution:** Record purpose, controller/processor roles, the processing basis determined by qualified review, and notice separately from consent. Record consent details only when consent is the selected basis or a distinct required choice. No live data collection before this is approved for the specific pilot.

### R4 — Delegated partner authority lacks a lifecycle

**Severity:** High before routes or partner access  
**Finding:** “Representative and scope of authority” is correct but does not describe grant issuance, permitted actions, expiry, revocation, re-checking or historical attribution.  
**Resolution:** Require an explicit, time-bounded grant bound to an authenticated account, partner, action scope, resource/intervention scope, issuer, evidence basis, expiry and revocation state. Re-check current authority on every request; revocation/expiry blocks new access immediately. Preserve the authority snapshot associated with historical actions.

### R5 — Safeguarding, complaints and challenge need an operating owner

**Severity:** Critical before live participant operations  
**Finding:** Privacy requirements mention incidents and safeguarding, but no accountable human role or independent complaint/challenge path is specified. The app must not imply that it provides emergency response, clinical care or statutory case management.  
**Resolution:** The pilot must appoint a trained safeguarding lead, define safe escalation/referral procedures and provide accessible complaint, challenge, correction and withdrawal routes that do not send complaints solely back to the subject of the complaint. Child/adult-at-risk procedures must be pilot-specific and defined by a qualified safeguarding owner.

### R6 — Matching must not become hidden prioritization

**Severity:** High before a matching workflow  
**Finding:** “Transparent human matching” is a good boundary but can still drift into an opaque score, hidden weights or automatic sorting.  
**Resolution:** Treat matching as feasibility assistance, not a deservingness score. Do not compute a universal scalar score or silently rank individuals by predicted worth. Show criteria, missing data and constraints. Any urgency-based triage must use published, pilot-approved criteria, a named human decision, reason and challenge route. Sensitive attributes must not be used as proxies.

### R7 — Append-only event history needs an explicit erasure design

**Severity:** Critical before schema approval  
**Finding:** The original documents note the tension between immutable history and data-subject rights but do not prescribe how the boundary is kept safe.  
**Resolution:** Event payloads contain minimum non-identifying metadata and no direct identifiers, sensitive narratives, exact household locations or raw files. Define retention by data class, deletion of evidence/files, subject-reference unlinking, backup expiry and explicit legal-hold handling before migrations. Append-only audit records are not a blanket retention exception.

### R8 — Public QR publication requires lifecycle controls

**Severity:** High before public project QR  
**Finding:** The public/private boundary is clear, but project publication, unpublication, archival, cache invalidation, publisher authority and withdrawal are not a complete lifecycle.  
**Resolution:** A later resolver design must require publisher authority, explicit publication approval, versioned public projection, revocation/unpublish, cache rules, a safe contact channel and a clear statement of what is declared versus independently reviewed. Public aggregate reporting needs a re-identification review before release.

### R9 — Pilot roles and claims remain unqualified

**Severity:** High before operational pilot  
**Finding:** No accountable pilot operator, partner, safeguarding owner or measurement reviewer is evidenced. The commercial M025 potato pilot is not Atlas Care evidence.  
**Resolution:** Keep pilot status unqualified until a real operating partner, accountable controller/operator roles, service capacity, safeguarding owner, participant notice, baseline and outcome measurement plan are established.

## 3. Architectural direction retained

The review retains these sound decisions:

- Atlas Care begins inside the existing Laravel modular monolith.
- Existing Passport API guards and MySQL remain; the Sanctum product name is not a reason to change auth.
- Public QR is limited to approved project-level information, never beneficiary discovery.
- No wallet, ledger, payment-provider or funds-movement integration is authorized.
- AI is assistive only and cannot decide deservingness, eligibility, commitment, delivery or disbursement.
- Mobile/low-bandwidth/assisted-accessibility requirements remain design constraints; offline consequential actions remain deferred.
- No migration, model, route, controller, dependency or runtime change belongs in M028.

## 4. Acceptance decision

This review is **not** an acceptance on behalf of maintainers, a privacy officer, a pilot operator or a safeguarding authority. It records technical amendments and blockers for those accountable reviewers.

ADR 0023 must remain **Proposed** until maintainers and the qualified pilot reviewers have considered the revised specification and recorded their decisions. A separate implementation milestone must then freeze the subject/identity contract, partner authorization contract, state machines, event semantics, retention/erasure, safeguarding, public-project publication and test plan before any migration is authored.

## 5. Migration gate

**Closed.** Do not write Care migrations until all of the following are true:

1. ADR 0023 is explicitly accepted by the accountable maintainers.
2. The revised M028 specification is approved and the review record names the approving roles.
3. One pilot use case and its accountable operating partner are evidenced.
4. Controller/processor roles, purpose and processing basis, notice, participant rights, retention/erasure and safeguarding are reviewed for that pilot.
5. Subject reference and identity separation are designed.
6. Partner grants, query scoping and revocation are specified.
7. Each state machine and event transaction boundary is defined.
8. Public QR exposure and reporting re-identification controls are specified if public publication is in scope.
9. A separately numbered implementation milestone has an approved schema, authorization matrix, migration up/down plan and tests.
10. The applicable repository CI gates are verified on the exact implementation commit.
