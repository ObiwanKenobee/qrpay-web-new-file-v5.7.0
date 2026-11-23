# Platform Security Policy — QRPay and Sanctum Scan

**Document:** `SECURITY.md`
**Version:** 2.0
**Status:** Active
**Effective:** 2026-11-19
**Supersedes:** 1.0 (Sanctum Scan disclosure policy, 2026-11-17)
**Owner:** Repository maintainers
**Applies to:** Every component, environment, contribution, deployment and integration in this repository
**Review:** Quarterly, and after any material architectural, dependency, deployment or security-incident change

This file is the single platform-wide security policy. It defines the scope of the
policy, how to report a vulnerability, the security requirements every part of the
platform must meet, the controls currently enforced, the known deficiencies, and the
process for incidents, exceptions and disclosure. It applies to the inherited QRPay
application and to all Sanctum Scan components; neither surface is exempt because the
other exists.

The policy is deliberately evidence-based. A control is described as **enforced** only
when a command, test or configuration in this repository demonstrates it. A requirement
that is defined but not yet implemented is recorded as a gap, not presented as a
control. See [ENGINEERING_STANDARDS.md](ENGINEERING_STANDARDS.md) for the engineering
implementation rules and [docs/engineering/RISK_REGISTER.md](docs/engineering/RISK_REGISTER.md)
for the authoritative risk status.

---

## 1. Scope and Applicability

### 1.1 In scope

This policy applies to:

| Surface | Components |
|---|---|
| Operator interface | Admin panel routes, admin authentication, role permissions, 2FA, settlement and identity-review actions |
| Participant interfaces | User (buyer), merchant and agent web panels and their authentication flows |
| Public interfaces | Public scan/presentation pages, payment-link pages, public API resolution endpoints |
| APIs | User API, merchant API and agent API (Passport-guarded, versioned under `/api`, `/merchant-api`, `/agent-api`) |
| Payment integrations | Gateway callbacks, webhooks, virtual-card webhooks, payment-gateway QR flow, wallet and transaction services |
| Sanctum Scan services | Exchange lifecycle, disputes, evidence, attestations, verifier governance, settlement status, provenance export |
| Background processing | Queues, scheduled commands, console commands, reconciliation jobs |
| Repository and build | Source code, migrations, seeders, CI workflows, verification gates, tracked documentation |
| Deployment | Application servers, web servers, database, object storage, secrets distribution, backups and release procedures |

### 1.2 Environments

- **Local development:** may use disposable databases and test data. Production data,
  production credentials and production signing keys must not be copied into local or
  personal environments.
- **Shared, staging and production:** every requirement in this policy applies. There is
  no relaxation of authentication, authorization or secret handling because an
  environment is described as internal.
- **Third-party services:** covered only at the integration boundary. The platform is
  responsible for how it authenticates to, verifies responses from and stores data
  received through external providers; provider-internal security is the provider's
  responsibility and must not be assumed.

### 1.3 Out of scope

- Security of third-party providers, payment networks, cloud infrastructure and
  researcher devices.
- Legal conclusions about ownership, licensing or redistribution of inherited code.
  These are tracked separately in [docs/security/source-provenance.md](docs/security/source-provenance.md).
- Regulatory authorization. The platform is software; operating a financial service
  requires separate authorization described in section 7.15.

---

## 2. Supported Versions

Security fixes are developed against the latest maintained version of the platform.

| Version | Security support |
|---|---|
| Latest stable release | Supported |
| `main` (latest development version) | Supported |
| Previous releases | Not guaranteed |
| Unmaintained branches and forks | Unsupported |

For supported versions, maintainers commit to investigating confirmed vulnerabilities,
prioritizing critical and high-severity issues, releasing verified fixes, documenting
security-relevant changes and adding regression tests where practical. Support does not
imply that every component, dependency or deployment has been independently audited.

---

## 3. Reporting a Vulnerability

**Do not disclose vulnerabilities through public GitHub issues, pull requests,
discussions, social media or public chat before coordinating with the maintainers.**
Public disclosure before remediation exposes users, merchants, administrators and live
deployments to exploitation.

### 3.1 Preferred channel

Use GitHub's private vulnerability reporting:

1. Open the repository's **Security** tab.
2. Select **Report a vulnerability**, if private vulnerability reporting is enabled.
3. Submit the details privately.

Repository: `https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0`

If private reporting is unavailable, contact the repository maintainers through an
established private channel. Do not open a public issue as an alternative.

### 3.2 Information to include

- **Title:** concise description of the vulnerability.
- **Affected component:** module, endpoint, controller, service, middleware, migration, integration or deployment component.
- **Vulnerability class:** for example broken access control, authentication bypass, injection, insecure direct object reference, request forgery or information disclosure.
- **Severity:** your assessment of potential impact.
- **Reproduction steps:** minimal steps to reproduce, including the account role and environment required.
- **Proof of concept:** request/response pairs, payloads or a test case where appropriate.
- **Impact:** what an attacker could read, modify, impersonate or disrupt.
- **Suggested remediation:** if available.
- **Environment:** versions and configuration relevant to the finding.

Redact credentials, tokens, personal data, KYC material, payment references and dispute
evidence from submitted screenshots and logs. Never include a real user's data.

### 3.3 What to expect

| Stage | Target |
|---|---|
| Initial acknowledgement | Within 3 business days |
| Initial triage | Within 7 business days |
| Investigation and severity assessment | As soon as reasonably possible |
| Remediation planning | Based on severity and complexity |
| Reporter updates | At least every 14 calendar days while actively investigating |

These are response targets, not guaranteed service levels.

---

## 4. Roles and Responsibilities

| Role | Security responsibilities |
|---|---|
| **Maintainers** | Own this policy, triage reports, approve exceptions, maintain the risk register, ensure gates remain green and evidence stays accurate |
| **Security owner (maintainer function)** | Coordinates incident response and disclosures; may be one maintainer or a small group; records decisions |
| **Change author** | Applies the requirements in section 7, adds tests, runs verification gates, discloses security-relevant side effects in the change description |
| **Reviewer** | Checks the security review triggers, rejects unsupported security claims, verifies that tests and gates were actually executed |
| **Deploying operator** | Applies deployment hardening, protects secrets and backups, monitors the deployment and reports suspected incidents |
| **Researcher / reporter** | Follows responsible-disclosure rules in section 12 and the good-faith expectations in section 12.6 |

No single person is assumed to hold all responsibilities. Where the project is
maintained by one operator, the same person performs multiple roles, but the recorded
decisions and evidence requirements still apply.

---

## 5. Policy Principles

These principles govern every decision where this policy is silent. They may not be
weakened without an architectural decision record (ADR) and, where relevant, a risk
registration.

1. **Fail closed.** When authentication or authorization cannot be established reliably, deny access.
2. **Least privilege.** Users, merchants, agents, administrators, services, tokens and database accounts receive only the permissions their function requires.
3. **Defense in depth.** Security must not depend on a single check; middleware, controller authorization, validation, database constraints and infrastructure controls work together.
4. **Participant scoping is mandatory.** A buyer or merchant may read or mutate only records tied to its own account or exchange. Administrative access requires admin authentication, role permission and 2FA.
5. **Sanctum Scan does not move funds.** No Sanctum Scan service or controller may write to `transactions`, `user_wallets`, `merchant_wallets`, `agent_wallets` or `payment_order_requests`.
6. **History is append-only.** Corrections, withdrawals, reviews and decisions create new records; evidence, attestations, reviews and event rows are never silently rewritten or deleted.
7. **A scan reference is not authorization, money or settlement evidence.** It resolves to a presentation document only.
8. **Data minimization.** Collect and expose only what a function requires. QR payloads contain opaque references and no personal data.
9. **Explicit state transitions.** Exchange and settlement operations follow defined transition rules; unauthorized actors and invalid transitions are rejected and recorded.
10. **Evidence integrity.** Integrity-relevant files are referenced by digest and stored privately; provenance is verifiable.
11. **No unsupported claims.** Documentation must not describe a control as implemented, a system as audited or an operation as proven unless executed evidence exists.
12. **Transparency.** Security decisions, accepted risks, deviations and remediation work are documented in this repository.

---

## 6. Data Classification and Handling

Every contributor and operator must be able to name the class of data they are handling.

| Class | Examples | Handling rules |
|---|---|---|
| **Restricted** | `APP_KEY`; database credentials; payment-gateway API secrets; Passport private keys (`storage/oauth-*.key`); webhook signing secrets; mail and SMS credentials; infrastructure access keys; virtual-card PAN/CVV data as currently persisted by the legacy card flow (R-31) | Never commit, log, screenshot, paste into issues or share. Store in environment/secret management. Encrypt at rest and in transit. Limit to the services that need them. Rotate on exposure or staff change. |
| **Confidential** | KYC and identity-review records; buyer identity references; wallet and transaction history; dispute messages and evidence; provenance manifests; support tickets; personal contact details; admin audit events | Need-to-know access. Participant-scoped queries. Private storage with application-generated names. No public URLs. Do not log record bodies. |
| **Internal** | QR references without personal data; aggregate dashboard statistics; operational logs containing identifiers; error traces; deployment configuration without secrets | Internal use only. Redact before sharing. Retain per operational need. |
| **Public** | Marketing pages; published documentation; public scan presentation; this policy | Approved for publication. Must not be derived from confidential data without review. |

Rules that apply across classes:

- Do not place personal data, credentials, payment references or evidence contents in
  QR payloads, URLs, filenames, analytics events or public documents.
- Log identifiers and outcomes, not payload bodies. Never log authorization headers,
  cookies, API tokens, passwords, full card data or identity documents.
- Treat provenance manifests as Confidential even though they are participant-exportable;
  they are scoped to the authenticated participant and may contain participant-authored
  content that includes sensitive statements.
- Redact evidence when attaching it to reports, chats, tickets or CI output.

---

## 7. Platform-Wide Control Requirements

The terms **MUST**, **MUST NOT**, **SHOULD** and **MAY** are used in their normal
engineering sense. A **MUST** that cannot be met requires a recorded exception under
section 10.3. A **SHOULD** that is skipped must be justified in review.

### 7.1 Identity, credentials and sessions

- Passwords MUST be stored only through the framework hasher (bcrypt or Argon2), never reversibly or in logs.
- The admin surface MUST require admin-guard authentication, role permission and the admin 2FA middleware. A pending-2FA admin MUST be redirected to verification, not granted panel access.
- API access MUST use the guard-specific Passport tokens (`api`, `merchant_api`, `agent_api`). Token issuance MUST require valid credentials; refresh and revocation behavior MUST follow Passport's model.
- Session cookies MUST be encrypted by the framework, set `HttpOnly` and `SameSite=Lax` (or stricter), and set `Secure` in production. Session identifiers MUST be regenerated on login and invalidated on logout.
- State-changing web requests MUST be protected by CSRF verification. A missing or stale token MUST fail closed and MUST NOT grant access.
- Authentication, registration, password-reset and token endpoints SHOULD be rate-limited.
- Caller-supplied identifiers MUST NOT be trusted for authentication or token issuance. Services must derive the principal from the authenticated guard (open gap: R-05).

### 7.2 Authorization and participant scoping

- Authorization MUST default to deny for administrative and participant-scoped resources.
- Every object read or mutation MUST verify that the authenticated actor is entitled to that specific object (object-level authorization), not merely that the actor is logged in.
- Merchant-facing responses MUST NOT reveal buyer identity-review state.
- Middleware bypasses (`withoutMiddleware`) MUST be individually justified in the change description, limited to the specific middleware required, and covered by a test proving the remaining stack still protects the route (open gap: R-25).
- Admin route permission coverage MUST default to deny for routes with unknown names; the current fail-open helper is a registered gap (R-06).
- Route registration MUST preserve the production middleware stack. Tests that disable security middleware MUST NOT be cited as evidence that a route is protected in production (R-27 resolved; keep the production-stack test).

### 7.3 Input validation and output encoding

- All external input MUST be validated at the boundary (request validation or explicit validation service) before use.
- Database access MUST use parameter binding or the query builder/Eloquent. String interpolation of user input into SQL is prohibited.
- Output rendered into HTML MUST be escaped. Blade `{{ }}` is the default; `{!! !!}` MUST NOT be used with user-controlled content. JavaScript, JSON, URL and attribute contexts require context-appropriate encoding.
- Mass assignment MUST be controlled through explicit `$fillable`, or a documented reason for `$guarded`, with the understanding that `$guarded` is not a security boundary.
- Uploads MUST validate size, MIME type and extension; stored filenames MUST be application-generated; executable content MUST NOT be served from the application origin.
- Server-side requests to caller-supplied URLs (SSRF) MUST be avoided or restricted to an allowlist of schemes and hosts.
- Deserialization of untrusted data and evaluation of untrusted code are prohibited.

### 7.4 Financial integrity and payment integrations

- Sanctum Scan MUST NOT move funds. Any change that writes to wallet or transaction tables from this surface is prohibited without a separate financial-invariant ADR and review.
- Payment provider callbacks MUST verify the provider's signature where the provider supports it, and MUST bind the event to a stored transaction before mutating state.
- Where a provider cannot sign, the platform MUST use an authenticated reconciliation path instead of trusting the payload. Callback routes that remove the `web` group MUST implement their own verification.
- Callback handling MUST be idempotent and replay-resistant; duplicate deliveries MUST NOT double-credit or double-debit.
- Amounts, fees, rates and currencies MUST be derived server-side from stored records, never accepted from the client.
- Wallet mutations MUST execute inside a database transaction with row locking and a documented lock order. Read-modify-write outside a lock is prohibited for money-moving paths (open gap: R-08).
- Administrative settlement actions MUST record truth and audit events; they MUST NOT be treated as provider settlement or move funds (R-09 remains open for the legacy QR flow).
- Public cache/config/route clearing endpoints MUST NOT exist; operational cache actions require authentication and operational authorization (open gap: R-04).

### 7.5 QR references and exchange integrity

- QR payloads MUST contain opaque references only and MUST NOT encode personal data, amounts, credentials or direct file paths.
- QR resolution MUST NOT be treated as proof of payment, identity or authorization.
- Reference lifecycle operations (issue, rotate, revoke) MUST be authorized to the owning account and SHOULD be rate-limited against enumeration.
- Exchange offer, acceptance, evidence, dispute and settlement transitions MUST follow the documented state machine and reject invalid or unauthorized transitions.

### 7.6 Evidence, audit and provenance integrity

- Evidence, attestations, reviews, decisions and exchange events MUST be append-only. Corrections and resubmissions create new versions; prior versions remain readable for authorized participants and auditors.
- Integrity-relevant files MUST be referenced by a cryptographic digest (SHA-256) and verified on download.
- Participant provenance export MUST be scoped to the authenticated buyer or merchant. It MUST NOT expose account IDs, internal actor IDs, raw storage paths, uploaded bytes or payment-reference fields.
- Provenance manifests MUST be deterministically serialized so that identical data produces the same digest, and MUST be verifiable offline per [ADR 0021](docs/decisions/0021-offline-provenance-manifest-verification.md).
- Administrative decisions MUST record the authenticated actor, timestamp and the exact evidence version they rely on. System actions MUST use an explicit system actor.

### 7.7 Files, media and uploads

- Dispute and verification evidence MUST use the private disk, application-generated names and participant-scoped authorization on read.
- Public media MUST be limited to content approved for public presentation; storage paths and directory listings MUST NOT be exposed.
- Downloads MUST authorize the actor before streaming the file, set `X-Content-Type-Options: nosniff`, and MUST NOT reflect caller-supplied paths.
- Legacy shared file handling is a registered gap (R-16); new functionality MUST NOT extend it.

### 7.8 Secrets and key management

- Secrets MUST NOT be committed. `.env` is untracked, `.env.example` contains no usable key (`APP_KEY` is blank), and `storage/*.key` is ignored.
- Every environment MUST generate its own `APP_KEY`. A published or shared example key MUST be treated as compromised.
- Application code MUST read configuration through `config()` rather than `env()` so that `config:cache` is safe. `env()` is permitted only inside `config/*.php`.
- Passport keys MUST be generated once per environment (`passport:install`) and protected like database credentials. They MUST NOT be copied between environments.
- Gateway and webhook secrets MUST be provided through environment configuration and consumed through config values, never hardcoded.
- `APP_DEBUG` MUST be `false` outside local development.
- Rotating `APP_KEY` invalidates sessions and may affect encrypted data. Rotation MUST be planned with a backup, a notification window and a verification step. A rotated key explains otherwise valid users receiving session or CSRF failures; that is expected behavior, not a defect.
- On suspected exposure: revoke or rotate the secret, assess what it protected, invalidate dependent sessions/tokens where applicable, and record the incident.

### 7.9 Transport, headers and browser hardening

- Production traffic MUST use HTTPS. The application forces HTTPS URL generation in production; TLS termination and HTTP-to-HTTPS redirection are deployment responsibilities documented in the [deployment runbook](docs/operations/aws-ec2-deployment.md).
- Reverse proxies MUST be configured through `TrustProxies` so scheme, host and client IP are correct. Trusted-host validation is currently disabled in the HTTP kernel; enabling it is a required hardening task for deployments with a fixed hostname set.
- Baseline response headers SHOULD be sent for HTML responses: `X-Content-Type-Options: nosniff`, `Referrer-Policy`, frame protection appropriate to the product, and `Strict-Transport-Security` once HTTPS is guaranteed. Central middleware for these headers is currently absent; only evidence downloads set `nosniff`.
- Content-Security-Policy SHOULD be introduced incrementally, starting in report-only mode, because the legacy UI relies on inline scripts and styles.
- Cookies MUST NOT carry sensitive data beyond the session identifier; authorization decisions must be made server-side.
- CORS MUST be restricted to known origins for any credentialed endpoint.

### 7.10 Logging, monitoring and privacy

- Logs MUST NOT contain passwords, tokens, cookies, authorization headers, full card data, identity documents, evidence bodies or complete request payloads.
- Log identifiers (user/merchant/exchange/transaction IDs), outcomes, state transitions and verification results instead.
- Authentication failures, authorization denials, webhook verification failures, admin decisions and suspicious file access SHOULD be recorded for operational detection.
- Log access MUST be restricted to operators; log retention MUST be defined and applied.
- Structured logging, correlation identifiers and metrics export are registered gaps (R-20).

### 7.11 Dependencies and supply chain

- `composer.lock` MUST remain committed. The advisory gate MUST run on every change; an advisory may be allowlisted only with a documented risk acceptance, a shrink-only assertion and a mitigation (R-28 is the current accepted case).
- New dependencies MUST be justified, reviewed for maintenance status and license compatibility, and recorded in the change description. Custom Composer repositories require explicit provenance review.
- Abandoned packages MUST be tracked. The current abandoned package is non-blocking and recorded in the audit output.
- A frontend lockfile is currently absent (R-15); frontend build reproducibility must not be claimed until it exists.
- CI actions and build tooling SHOULD be pinned to reviewed versions.

### 7.12 Secure development and change management

- Every change MUST pass the verification gates in ADR 0016: conflict markers, PHP syntax, the full test suite, composer validation and advisory audit, and Pint on changed files. The MySQL job adds migration and concurrency verification.
- Security-relevant behavior MUST have regression tests. A bug fix for a vulnerability SHOULD include a test that fails without the fix.
- Static analysis is currently absent; it MUST NOT be cited as a control (R-10).
- Security claims in documentation and pull requests MUST correspond to executed evidence. Local success MUST NOT be presented as CI success (R-02).
- A security-relevant change MUST update the risk register when it introduces, changes or resolves a risk.

### 7.13 Deployment and infrastructure hardening

- Deployments MUST follow the [AWS EC2 runbook](docs/operations/aws-ec2-deployment.md) or an equivalent documented procedure covering seeding, Passport keys, storage links, cache ordering, queue workers, scheduler and rollback.
- The web root MUST be `public/`; `.env`, `composer.json`, `composer.lock` and `artisan` MUST NOT be web-accessible.
- The database account MUST use least privilege; application and migration credentials SHOULD be separated where the deployment supports it.
- Installer and reset assets MUST NOT be present in a deployed tree. The current repository no longer contains them; deployments restored from older artifacts must verify their absence.
- Administrative access SHOULD be restricted by network policy or a protected access path where the deployment topology allows it.
- Operators MUST apply OS, PHP, web server and database security updates within a documented maintenance window.

### 7.14 Backup, recovery and data lifecycle

- A database and storage backup MUST be taken before migrations or destructive maintenance. The deployment runbook requires this.
- Rollback MUST use reversible migrations; irreversible destructive migrations require an explicit decision and backup evidence.
- Backups MUST be encrypted at rest, access-controlled and protected from public exposure. Restore MUST be tested periodically.
- Evidence and audit history MUST remain intact across backup and restore.
- Data retention MUST be defined per class in section 6; expired confidential data MUST be deleted through an authorized, logged process.

### 7.15 Privacy, retention and regulatory boundaries

- Collect only data required for a function. Do not store government identifiers, document images or biometrics unless a defined function requires them and their protection is reviewed.
- Participant data MUST be used only for the purpose it was collected for; sharing requires a documented basis.
- Users and participants SHOULD have a route to request correction or deletion of data that is not subject to financial-record or audit retention.
- The platform is software infrastructure, not a licensed financial institution. Operators of live deployments are responsible for applicable payment-service authorization, data-protection, consumer-protection, anti-money-laundering and virtual-asset obligations.
- Regulatory claims MUST be reviewed before publication and MUST NOT be inferred from the presence of a feature.

---

## 8. Current Enforcement Status

Status as of 2026-11-19. A control is **Enforced** only when the cited evidence exists
in this repository.

| Control | State | Evidence |
|---|---|---|
| Conflict-marker rejection | Enforced | `tools/check-conflict-markers.php` (G1) |
| PHP syntax | Enforced | `tools/check-php-syntax.php` (G2) |
| Full automated test suite | Enforced locally; CI wired | `composer check` → G3; the workflow runs it on push/PR, first GitHub execution pending (R-02) |
| Composer validation and advisory audit | Enforced | G7 with reviewed allowlist and shrink-only assertion |
| Code style on changed files | Enforced | Pint `--test` (G8) |
| Route-name uniqueness | Enforced | `RouteRegistryTest` with empty duplicate allowlist |
| MySQL migration and concurrency job | Wired; GitHub execution pending | Workflow `mysql` job; local MySQL pass recorded in the risk register |
| Static analysis | Absent | No PHPStan/Larastan configuration (R-10) |
| Secret template hygiene | Enforced in template | `.env` untracked; `.env.example` has blank `APP_KEY`; `storage/*.key` ignored |
| Runtime secret access | Enforced by repository convention | No `env()` calls outside `config/`; all runtime reads go through `config()` |
| Admin 2FA middleware | Enforced on the authenticated admin group | `admin.google.two.factor` in route registration |
| Admin role enforcement | Enforced with a fail-open gap | `admin.role.guard`; unknown route names are permitted (R-06) |
| CSRF protection | Enforced on web panel routes | `VerifyCsrfToken` in the `web` group |
| Session cookie protection | Enforced | Encrypted cookies, `SameSite=Lax`, configurable `Secure`, regeneration on login/logout |
| API bearer authentication | Enforced | Passport guards plus guard-specific authenticator middleware |
| Webhook signature verification | Partial | CardyFie virtual-card webhook verifies HMAC-SHA256; legacy reloadly/flutterwave callbacks do not (R-03) |
| Request rate limiting | Enforced | `RateLimiter` in the `web` group; API throttle middleware |
| HTTPS URL generation | Enforced in production | `URL::forceScheme('https')` when the environment is production |
| Baseline security response headers | Absent centrally | Only evidence downloads send `nosniff` |
| Frontend dependency lockfile | Absent | No `package-lock.json` (R-15) |
| Cardholder data handling | Legacy deviation | The legacy virtual-card flow persists PAN/CVV in plain string columns and transaction messages (R-31) |
| Installer routes | Absent from the current tree | No installer directory or registration in the repository; verify deployed artifacts |
| Structured observability | Absent | No correlation IDs, metrics or trace export (R-20) |
| CI execution on GitHub and branch protection | Unverified | Gates exist; first GitHub run and branch protection remain pending (R-02) |

---

## 9. Known Deficiencies and Accepted Risks

The authoritative status, evidence and mitigation plan for each item is in
[docs/engineering/RISK_REGISTER.md](docs/engineering/RISK_REGISTER.md). The following
material items are known to this policy and MUST NOT be represented as solved:

| Risk | Summary | Policy impact |
|---|---|---|
| R-02 | CI execution and branch protection unverified | Deterministic gates are defined but GitHub enforcement is not proven |
| R-03 | Legacy top-up and withdrawal callbacks trust unsigned payloads | Violates the payment-callback requirement in 7.4 until signatures or reconciliation exist |
| R-04 | Public cache/config clearing endpoints exist | Violates the operational-endpoint requirement in 7.4 |
| R-05 | Push-token issuance trusts a caller-supplied user ID | Violates the identity requirement in 7.1 |
| R-06 | Admin RBAC permits routes with unknown names | Violates default-deny in 7.2 |
| R-08 | Legacy wallet paths use non-locking read-modify-write | Violates the money-moving concurrency requirement in 7.4 |
| R-09 | Legacy QR gateway charges before validating order state | Financial-integrity deficiency in the legacy surface |
| R-10 | Static analysis absent; middleware bypasses in tests | Limits assurance; no static-analysis claim permitted |
| R-14 | Route bootstrap depends on database rows | Availability and configuration-integrity risk |
| R-15 | No frontend lockfile; supply-chain review partial | Reproducibility requirement in 7.11 not met |
| R-16 | Legacy shared file handling lacks consistent authorization | Requirement in 7.7 not met for legacy paths |
| R-18 | Lock-order and uniqueness gaps in governance flows untested | Assurance limit for 7.4 and 7.6 |
| R-19 | Full migration set verified only locally | Release evidence gap |
| R-20 | No structured observability or audit export | Detection requirement in 7.10 not met |
| R-22 | Runtime version drift between development, CI and deployment | Deployment reproducibility risk |
| R-23 | Source provenance and licensing unresolved | Distribution and attribution boundaries |
| R-25 | Broad middleware bypass surface | Requires per-route justification under 7.2 |
| R-29 | Four routes reference missing controller methods | Availability debt; allowlisted shrink-only |
| R-30 | Route introspection depends on seeded settings | Gate fixture debt; constructor pattern is legacy debt |
| R-31 | Legacy virtual-card flow persists PAN/CVV in plaintext database columns and transaction messages | Violates the cardholder-data requirement in section 13 and expands PCI scope |

A risk being listed here does not authorize exploiting it. It records that the gap is
known, owned and tracked rather than silently accepted.

---

## 10. Security Review and Exceptions

### 10.1 Changes requiring an explicit security review

A change MUST obtain an explicit review against this policy when it:

- adds or changes authentication, authorization, session or token behavior;
- adds a public or unauthenticated route, or removes middleware;
- touches webhook, callback, reconciliation or any money-moving path;
- modifies wallet, transaction, settlement or evidence-integrity logic;
- changes file upload, storage, download or media-serving behavior;
- introduces a dependency, custom repository, CI action or build tool;
- changes deployment topology, secret distribution, backup or rollback;
- alters logging to include new data fields;
- changes data retention or participant visibility.

The review MUST record: the threat considered, the control applied, the tests executed
and any residual risk. A residual risk that is accepted becomes a risk-register entry.

### 10.2 Evidence required for security-relevant changes

- Executed verification gates with the summary (not a claim that they were run).
- Regression tests for the fixed or introduced behavior.
- Updated risk register where status changes.
- Updated documentation or ADR when a security boundary changes.

### 10.3 Exceptions and waivers

An exception to a **MUST** requirement is permitted only when:

1. It is recorded in the risk register with an owner, rationale, compensating controls and an expiry or review date.
2. It is time-bound; an expired exception is a defect.
3. It is visible in the change description and review.
4. It does not remove auditability or grant broader access than the underlying function requires.

Undocumented exceptions are policy violations. This policy does not silently waive any
requirement because of legacy age, deployment urgency or convenience.

---

## 11. Incident Response

### 11.1 Severity

| Severity | Description | Examples |
|---|---|---|
| **Critical** | Active compromise or imminent risk to funds, credentials or broad participant data | Admin account takeover, wallet manipulation, leaked production `APP_KEY` or database credentials |
| **High** | Confirmed unauthorized access to sensitive records or an exploitable authorization bypass | Cross-tenant evidence access, unsigned callback mutating balances, broad privileged API exposure |
| **Medium** | Limited disclosure or disruption; exploitability constrained by conditions | Enumeration, partial metadata exposure, missing rate limit on an authenticated endpoint |
| **Low** | Defense-in-depth weakness without demonstrated exploit path | Missing hardening header, overly verbose error message without sensitive data |

### 11.2 Process

1. **Detect and record.** Capture what was observed, when, by whom and from which environment. Preserve logs before rotating or deleting anything.
2. **Triage.** Confirm scope, affected accounts, data and environments; assign a severity and an incident lead.
3. **Contain.** Revoke sessions and tokens, rotate exposed secrets, disable affected routes or accounts, and isolate the deployment if required. Containment must be reversible where possible.
4. **Eradicate.** Identify the root cause, deploy the fix through the normal gates, and verify that the exploit path is closed.
5. **Recover.** Restore normal operation, confirm data integrity, and monitor for recurrence.
6. **Notify.** Inform affected operators and participants as required by law, contract and risk. Maintainer-driven notification commitments are described in section 12; regulatory notification is the deploying operator's responsibility.
7. **Learn.** Record a post-incident note with timeline, root cause, controls added, tests added and risk-register updates.

### 11.3 Evidence preservation

Incident evidence MUST be preserved with restricted access: relevant logs, audit rows,
request metadata, deployment versions and configuration state. Do not paste raw
Confidential or Restricted data into public channels; use private storage and redacted
excerpts.

---

## 12. Vulnerability Handling and Coordinated Disclosure

### 12.1 Triage

Maintainers assess reproducibility, affected versions and components, required
privileges, remote exploitability, confidentiality/integrity/availability impact, and
whether participant data, financial records, identity material or evidence are involved.

### 12.2 Remediation

Remediation may include code changes, database constraints or migrations,
authentication/authorization improvements, validation changes, cryptographic or
signature-verification improvements, dependency upgrades, deployment hardening and
regression tests.

### 12.3 Severity classification

Severity uses a risk-based approach informed by CVSS:

| Severity | Examples | Response priority |
|---|---|---|
| Critical | Remote compromise, administrative authentication bypass, unauthorized financial-state manipulation | Immediate |
| High | Privilege escalation, unauthorized access to sensitive identity or exchange records, exploitable authorization failures | Urgent |
| Medium | Limited information disclosure, exploitable validation weaknesses, restricted disruption | Prioritized |
| Low | Limited-impact weaknesses or defense-in-depth improvements | Scheduled |

Severity may change as evidence becomes available. Classification is based on the
actual implementation and deployment conditions, not the reporter's label alone.

### 12.4 Areas of interest

- **Identity and authentication:** authentication bypass, session weaknesses, broken identity verification, MFA weaknesses, insecure identity-reference handling.
- **Authorization:** broken object-level authorization, privilege escalation, inadequate administrator authorization, merchant/buyer access-control violations, unauthorized state changes.
- **QR and scan security:** reference manipulation, unauthorized resolution, lifecycle bypass, enumeration, replay, unauthorized exchange access.
- **Exchange and settlement integrity:** unauthorized transitions, invalid settlement state, impersonation, payment-evidence manipulation, versioning bypass, race conditions, unauthorized administrative verification.
- **Evidence and audit integrity:** unauthorized modification or deletion, unauthorized access, verification bypass, forged attestations, audit manipulation, inconsistent histories.
- **Application security:** injection (SQL, command, template), XSS, CSRF, insecure deserialization, SSRF, mass assignment, insecure uploads, rate-limit bypass, sensitive disclosure.
- **Infrastructure and dependencies:** exposed secrets, insecure configuration, vulnerable dependencies, unprotected administrative endpoints, weak cryptography, CI/CD weaknesses.

### 12.5 Out-of-scope activities

The following are not authorized under this policy:

- Denial-of-service attacks against production systems.
- Destructive testing or database modification.
- Accessing, modifying or deleting another person's data.
- Testing with real payment credentials or unauthorized financial transactions.
- Social engineering, phishing or impersonation.
- Physical attacks.
- Automated scanning that disrupts availability.
- Public disclosure before coordinated remediation.

Stop immediately if you encounter real user information, credentials or production data,
and report the exposure without retaining copies.

### 12.6 Good-faith expectations

Researchers are asked to:

1. Act in good faith and avoid privacy violations.
2. Avoid disrupting services or degrading availability.
3. Never access or modify data beyond what is necessary to demonstrate the issue.
4. Preserve confidentiality during investigation.
5. Allow reasonable time for investigation and remediation.
6. Coordinate public disclosure with maintainers.
7. Avoid publishing exploit code before remediation.

Maintainers commit to treating good-faith reports respectfully, evaluating them on
technical evidence, communicating progress, avoiding unnecessary disclosure of the
reporter's identity and crediting researchers where permission is granted.

### 12.7 Disclosure after remediation

After remediation, maintainers may publish a security advisory covering affected
versions, classification, severity, impact, remediation details, upgrade instructions
and regression tests. There is currently no monetary bounty or guaranteed public
recognition.

---

## 13. Assurance and Compliance Boundaries

- Passing tests, gates or a review is **not** an independent security audit or certification. No ISO 27001, SOC 2, PCI DSS or similar certification is claimed.
- Card data MUST be handled by payment providers. The platform MUST NOT store card numbers, CVV values or equivalent cardholder data; a future integration that requires it needs a separately scoped review. The legacy virtual-card flow currently violates this requirement and is recorded as R-31; it must be remediated before any new card feature ships.
- The platform is not a licensed financial institution and does not itself transfer funds in Sanctum Scan flows. Deployment operators are responsible for regulatory authorization and reporting.
- Source provenance, licensing and redistribution boundaries are separate from operational security and are recorded in [docs/security/source-provenance.md](docs/security/source-provenance.md).
- Security statements in README, roadmap or marketing materials MUST defer to this policy and the risk register.

---

## 14. Policy Enforcement

- Contributors and operators MUST follow this policy as a condition of contributing to or deploying the platform.
- A knowingly undocumented exception, secret commit or bypass of the verification gates is a policy violation.
- Maintainers MAY revoke repository, deployment or integration access for violations, and SHOULD record the action.
- Verification gates are the minimum automated enforcement. A green gate does not replace review against sections 7 and 10.
- Repeated or material violations MUST be recorded as risks and addressed before further feature work.

---

## 15. Maintenance and Change Log

| Version | Date | Change |
|---|---|---|
| 1.0 | 2026-11-17 | Sanctum Scan security and responsible-disclosure policy |
| 2.0 | 2026-11-19 | Platform-wide policy: scope, roles, data classification, control requirements, enforcement status, known deficiencies, incident response, exceptions and enforcement |

Material changes to this policy MUST be reviewed by the maintainers and recorded in this
table. Policy requirements take effect on the effective date of the version that
introduced them. Open gaps remain governed by their risk-register entries until closed.

**Repository:** `https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0`

**Policy file:** `SECURITY.md`
