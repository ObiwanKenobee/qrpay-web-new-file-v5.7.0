# Security Policy

## Sanctum Scan — Security & Responsible Disclosure

Sanctum Scan takes the security of its users, merchants, contributors, integrations, and institutional partners seriously.

Security is a foundational engineering requirement, not an optional feature. We aim to build an exchange infrastructure that protects identities, preserves transaction integrity, maintains trustworthy evidence, and ensures that sensitive operations are accessible only to authorized actors.

We welcome responsible security research and encourage security researchers, developers, and members of the community to report vulnerabilities so they can be investigated and addressed.

This policy explains which versions receive security updates, how to report vulnerabilities, what information to provide, how reports are handled, and the principles governing coordinated disclosure.

---

## 1. Supported Versions

Security updates are prioritized for the actively maintained version of Sanctum Scan.

Unless otherwise explicitly stated, security fixes are developed against the latest maintained version on the default branch.

| Version | Security Support |
|---|---|
| Latest stable release | Supported |
| `main` (latest development version) | Supported |
| Previous releases | Not guaranteed |
| Unmaintained branches and forks | Unsupported |

### Security maintenance commitments

For supported versions, we aim to:

- Investigate confirmed security vulnerabilities.
- Prioritize critical and high-severity vulnerabilities.
- Release security patches when fixes have been implemented and verified.
- Document security-relevant changes.
- Communicate breaking security changes and necessary upgrade actions.
- Maintain regression tests for vulnerabilities where practical.

Security support does not imply that every component, dependency, integration, deployment, or third-party service has been independently audited.

Specific release support periods may be announced as stable releases and maintenance commitments mature.

---

## 2. Reporting a Vulnerability

**Please do not disclose security vulnerabilities through public GitHub issues, pull requests, discussions, or social media before coordinating with the maintainers.**

Public disclosure before remediation may expose users, merchants, integrations, and deployments to exploitation.

### Preferred reporting channel

Use GitHub's private vulnerability reporting mechanism:

1. Navigate to the Sanctum Scan repository.
2. Open the repository's **Security** tab.
3. Select **Report a vulnerability**, if private vulnerability reporting is enabled.
4. Submit the vulnerability details privately.

Repository:

https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0

If private vulnerability reporting is unavailable, contact the repository maintainers through an established private communication channel. Do not publish exploit details in a public issue as an alternative.

### Information to include

A useful vulnerability report should include:

- **Title:** A concise description of the vulnerability.
- **Affected component:** The relevant module, endpoint, controller, service, model, middleware, migration, or integration.
- **Vulnerability type:** For example, broken access control, authentication bypass, SQL injection, insecure direct object reference, or sensitive information disclosure.
- **Severity:** Your assessment of the potential impact.
- **Description:** A clear explanation of the vulnerability and its underlying cause.
- **Reproduction steps:** Minimal steps necessary to reproduce the issue.
- **Proof of concept:** A minimal demonstration, request, response, or test case where appropriate.
- **Impact:** What an attacker could potentially access, modify, impersonate, or disrupt.
- **Suggested remediation:** If you have a proposed fix.
- **Environment:** Relevant software versions, configuration, and deployment conditions.

Please redact credentials, access tokens, personally identifiable information, payment references, and other sensitive data from submitted evidence.

---

## 3. Vulnerability Response Process

Every security report should receive a structured assessment.

Our intended response process is:

| Stage | Target |
|---|---|
| Initial acknowledgement | Within 3 business days |
| Initial triage | Within 7 business days |
| Investigation and severity assessment | As soon as reasonably possible |
| Remediation planning | Based on severity and complexity |
| Reporter updates | At least every 14 calendar days while actively investigating |

These are response targets, not guaranteed service-level agreements.

### 3.1 Acknowledgement

We will acknowledge receipt of a report and, where possible, provide a reference for tracking the investigation.

### 3.2 Triage

The maintainers will assess:

- Whether the report describes a reproducible vulnerability.
- Which versions and components are affected.
- Whether exploitation requires authentication or privileged access.
- Whether the vulnerability can be exploited remotely.
- The confidentiality, integrity, and availability impact.
- Whether user data, financial records, identity information, or exchange evidence are affected.

### 3.3 Investigation

The team may request additional technical information or clarification.

Investigations should prioritize reproducing the issue in an isolated environment and determining the underlying cause.

### 3.4 Remediation

Depending on the findings, remediation may involve:

- Application code changes.
- Database constraints or migration corrections.
- Authentication and authorization improvements.
- API validation and request-handling changes.
- Cryptographic or signature-verification improvements.
- Dependency upgrades.
- Configuration and deployment hardening.
- Additional regression and security tests.

### 3.5 Resolution and disclosure

Once a vulnerability has been addressed and a remediation is ready, the maintainers will coordinate disclosure with the reporter.

Where appropriate, we will publish:

- A security advisory.
- Affected versions.
- Vulnerability classification.
- Severity and impact.
- Remediation details.
- Upgrade or mitigation instructions.
- Security regression tests or relevant technical documentation.

We aim to credit researchers who responsibly disclose vulnerabilities, subject to their consent.

---

## 4. Vulnerability Severity Classification

We use a risk-based approach informed by the Common Vulnerability Scoring System (CVSS).

| Severity | Examples | Response Priority |
|---|---|---|
| Critical | Remote compromise, authentication bypass with administrative control, unauthorized financial-state manipulation | Immediate |
| High | Privilege escalation, unauthorized access to sensitive identity or exchange records, exploitable authorization failures | Urgent |
| Medium | Limited information disclosure, exploitable validation weaknesses, restricted service disruption | Prioritized |
| Low | Limited-impact security weaknesses or defense-in-depth improvements | Scheduled |

Severity is determined by actual exploitability, affected assets, required privileges, deployment conditions, and potential impact.

A vulnerability's classification may change as additional evidence becomes available.

---

## 5. Security Scope

Security reports concerning the Sanctum Scan repository and its associated application functionality are welcome.

We are particularly interested in vulnerabilities affecting the following areas.

### 5.1 Identity and Authentication

- Authentication bypass.
- Session management weaknesses.
- Broken identity verification.
- Unauthorized account access.
- Multi-factor authentication weaknesses.
- Insecure identity-reference handling.

### 5.2 Authorization and Access Control

- Broken object-level authorization.
- Privilege escalation.
- Inadequate administrator authorization.
- Merchant and buyer access-control violations.
- Role-based access-control failures.
- Unauthorized state-changing operations.

### 5.3 QR Code and Scan Security

- QR reference manipulation.
- Unauthorized scan resolution.
- QR lifecycle bypasses.
- Reference enumeration.
- Replay vulnerabilities.
- Unauthorized access to exchange resources.

### 5.4 Exchange and Settlement Integrity

- Unauthorized exchange-state transitions.
- Invalid settlement-state changes.
- Buyer or merchant impersonation.
- Payment-evidence manipulation.
- Evidence-versioning bypasses.
- Race conditions and concurrency vulnerabilities.
- Unauthorized administrative verification or settlement.

### 5.5 Evidence, Verification and Audit Integrity

- Modification or deletion of records intended to be immutable.
- Unauthorized evidence access.
- Verification bypasses.
- Forged or improperly attributed attestations.
- Audit-event manipulation.
- Inconsistent exchange and settlement histories.

### 5.6 API and Application Security

- SQL injection.
- Cross-site scripting (XSS).
- Cross-site request forgery (CSRF).
- Insecure deserialization.
- Server-side request forgery (SSRF).
- Mass assignment.
- Insecure file uploads.
- Rate-limit bypasses.
- Sensitive information disclosure.

### 5.7 Infrastructure and Dependencies

- Exposed credentials or secrets.
- Insecure application configuration.
- Vulnerable dependencies.
- Inadequately protected administrative endpoints.
- Insecure cryptographic implementations.
- CI/CD and deployment security weaknesses.

---

## 6. Out-of-Scope Activities

The following activities are not authorized as part of this policy:

- Denial-of-service attacks against production systems.
- Destructive testing or database modification.
- Accessing, modifying, or deleting another person's data.
- Testing with real payment credentials or unauthorized financial transactions.
- Social engineering, phishing, or impersonation of maintainers or users.
- Physical attacks against infrastructure or personnel.
- Automated vulnerability scanning that disrupts production availability.
- Public disclosure of exploitable vulnerabilities before coordinated remediation.

Researchers should use isolated development environments and test accounts wherever possible.

Stop testing immediately if you encounter real user information, credentials, or sensitive production data.

---

## 7. Responsible Disclosure Principles

We ask security researchers to:

1. Act in good faith and make reasonable efforts to avoid privacy violations.
2. Avoid disrupting services or degrading system availability.
3. Never access or modify data beyond what is necessary to demonstrate a vulnerability.
4. Preserve confidentiality during investigation.
5. Allow maintainers reasonable time to investigate and remediate reported issues.
6. Coordinate public disclosure with the maintainers.
7. Avoid publishing exploit code or operational instructions that could facilitate exploitation before remediation.

In return, maintainers commit to:

1. Treating good-faith reports respectfully.
2. Evaluating reports based on technical evidence.
3. Communicating investigation progress.
4. Avoiding unnecessary disclosure of the reporter's identity.
5. Acknowledging responsible researchers where permission is granted.
6. Providing remediation or mitigation guidance when appropriate.

---

## 8. Security Engineering Principles

Sanctum Scan follows a security-conscious engineering approach built around the following principles.

### Defense in Depth

Security must not depend on a single control. Authentication, authorization, validation, database constraints, application logic, and infrastructure protections should work together.

### Least Privilege

Every user, merchant, administrator, service, and integration should receive only the permissions necessary to perform its intended responsibilities.

### Explicit State Transitions

Exchange and settlement operations must follow defined state-transition rules. Unauthorized actors and invalid transitions must be rejected.

### Evidence Integrity

Payment evidence, verification decisions, disputes, and audit events should preserve their history. Corrections and resubmissions should not silently rewrite previous records.

### Fail-Closed Authorization

Access should be denied when authorization cannot be established reliably.

### Testable Security

Security assumptions should be represented through automated tests, database constraints, CI verification gates, and documented engineering decisions.

### Transparency and Accountability

Security-relevant decisions, accepted risks, limitations, and remediation work should be documented.

---

## 9. Security Architecture Boundaries

Sanctum Scan distinguishes between exchange coordination, evidence verification, administrative decisions, and external financial settlement.

Its exchange and settlement-verification services are not themselves a payment processor and do not independently transfer funds.

External payment-provider integrations, legacy wallet functionality, deployment infrastructure, and third-party services have separate security requirements and must not be assumed secure merely because Sanctum Scan's internal controls pass.

A successful application test suite is not equivalent to an independent security audit.

Security claims should reflect the controls actually implemented and independently verified.

---

## 10. Security Updates and Advisories

Security-relevant changes should be documented through:

- GitHub Security Advisories, where appropriate.
- Release notes and changelogs.
- Relevant architectural decision records.
- Regression tests.
- Dependency and configuration updates.

Users and deployment operators are encouraged to:

- Keep supported versions updated.
- Protect application secrets and environment files.
- Use HTTPS.
- Restrict administrative access.
- Apply database and infrastructure security updates.
- Monitor application and authentication logs.
- Review third-party integration permissions.
- Maintain secure backups and recovery procedures.

---

## 11. Recognition

We appreciate responsible security researchers and community members who help improve Sanctum Scan.

With the researcher's permission, we may acknowledge meaningful security contributions in security advisories or project documentation.

We do not currently promise monetary rewards, bug bounty payments, or guaranteed public recognition.

---

## 12. Policy Changes

This security policy may evolve as Sanctum Scan's architecture, deployment model, integrations, and security practices mature.

Material changes will be documented in the repository.

**Last reviewed:** 17 November 2026

**Repository:** https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0

**Policy:** `SECURITY.md`

**Maintained by:** Sanctum Scan Project Maintainers
