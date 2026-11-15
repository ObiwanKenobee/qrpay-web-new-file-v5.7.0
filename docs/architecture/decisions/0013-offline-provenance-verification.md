# ADR 0013: Offline provenance manifest verification

- **Status:** Proposed implementation
- **Date:** 2026-11-13
- **Scope:** Local verification of a saved Sanctum Scan participant provenance export

## Context

Milestone 012 provides participants with a point-in-time JSON export and a SHA-256 digest over its `data` object. A digest is useful only when recipients can independently recompute it and inspect whether the exported evidence relationships remain internally consistent.

The verifier should work on a downloaded file without an active Sanctum Scan session, network connection, database access or trust in a later server response.

## Decision

Add a small application service and an Artisan command:

```bash
php artisan sanctum-scan:verify-provenance path/to/provenance-export.json
```

The verifier:

1. Rejects unreadable, malformed and over-25-MiB inputs.
2. Requires the supported format `sanctum-scan-participant-provenance` and schema version `1.0.0`.
3. Requires SHA-256 with scope `data`, then recomputes the digest using the same JSON serialization flags as M012.
4. Checks the exchange/dispute envelope and the evidence/event/message collection structure.
5. Confirms that evidence review and institutional attestation digests match the evidence item.
6. Checks that revision versions are sequential, predecessor links are intact, every revision is bound to the evidence digest, and withdrawal is terminal.
7. Checks that reassessment links point to a visible assessment and that the source-side and reassessment-side records agree on their relationship and exact prior version/status.

The command prints a concise result and digest. It returns exit code 0 only for a valid manifest; invalid, unsupported or unreadable inputs return a non-zero exit code.

## Consequences

### Positive

- Recipients can validate a saved export without contacting the platform.
- A changed data object fails digest verification.
- Internally inconsistent evidence, revision or reassessment relationships are reported even when someone recomputes the outer digest after altering the data.
- The verifier can be reused by future SDKs and integration tooling.

### Trust boundaries and non-goals

- The manifest digest is not a digital signature, trusted timestamp, proof of exporter identity or non-repudiation mechanism.
- A successful result means the saved object matches its declared digest and passes the supported structural checks. It does not establish that a participant statement, platform review or verifier conclusion is true.
- The verifier does not inspect uploaded file bytes. Those remain accessible only through the existing private download flow; the export contains their recorded SHA-256 digests.
- The verifier does not contact a payment provider, resolve disputes, alter an exchange, write a verification event or update any database.
- Only schema version `1.0.0` is accepted. A later breaking schema requires an intentional verifier update rather than silently accepting unknown semantics.
