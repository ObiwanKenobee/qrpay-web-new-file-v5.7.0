# ADR 0021 — Offline Provenance Manifest Verification

**Status:** Implemented  
**Date:** 2026-11-17  
**Depends on:** ADR 0020 (participant-scoped provenance export)

## Context

ADR 0020 gives participants a point-in-time JSON export with a SHA-256 digest
over its `data` object. Recipients need to recompute that digest and inspect
the exported structure without an active Sanctum Scan session, network
connection or database access.

## Decision

Provide an application service and a read-only Artisan command:

```bash
php artisan sanctum-scan:verify-provenance path/to/provenance-export.json
```

The command accepts an absolute path or a path relative to the Laravel project
root. It reads the saved file and does not modify it. The verifier rejects
inputs larger than 25 MiB, malformed or unreadable documents, unsupported
formats and unsupported schema versions.

The supported manifest is:

- format: `sanctum-scan-participant-provenance`;
- schema version: `1.0.0`;
- hash algorithm: `SHA-256`;
- hash scope: `data`.

The verifier recomputes the digest using the serialization contract in ADR
0020. It also validates the exchange/dispute envelope and evidence, event and
message collection structure; checks evidence digest bindings for platform
reviews and independent attestations; and validates attestation revision and
reassessment relationships.

The command reports a concise result, calculated digest and names of completed
checks. It returns exit code 0 only for a valid manifest. Unreadable, invalid,
oversized, unsupported or inconsistent documents return a non-zero exit code
and report validation errors.

## Consequences and trust boundaries

- A participant can verify a saved manifest locally without contacting
  Sanctum Scan or reading its database.
- A changed `data` object fails digest verification when the manifest digest
  is not correspondingly changed.
- A successful result means the document matches its declared digest and
  passes the supported structural checks. It does not prove who exported or
  signed the document, when it was exported, or whether participant statements,
  platform reviews or verifier conclusions are true.
- The manifest digest is not a digital signature, trusted timestamp,
  exporter-authentication mechanism or non-repudiation proof.
- The verifier does not inspect uploaded file bytes, contact payment
  providers, resolve disputes, mutate exchange state or write to a database.
- Only schema `1.0.0` is accepted. A future breaking schema requires an
  intentional verifier update; unknown semantics are not silently accepted.

## Implementation evidence

- Verifier: `app/Services/SanctumScan/ProvenanceManifestVerifier.php`
- Artisan command: `app/Console/Commands/VerifySanctumScanProvenance.php`
- Unit tests: `tests/Unit/SanctumScan/ProvenanceManifestVerifierTest.php`
- Command tests:
  `tests/Feature/SanctumScan/ProvenanceManifestVerifierCommandTest.php`
- User guide: `docs/api/sanctum-scan-offline-provenance-verifier.md`

The implementation is present on `main`. GitHub Actions verification remains
subject to the hosted-runner issue tracked in repository issue #21.
