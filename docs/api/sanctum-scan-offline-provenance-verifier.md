# Sanctum Scan offline provenance verifier

## Purpose

Verify a previously saved M012 participant provenance export without calling Sanctum Scan, accessing its database or requiring an authenticated platform session.

## Command

```bash
php artisan sanctum-scan:verify-provenance path/to/provenance-export.json
```

An absolute file path or a path relative to the Laravel project root may be supplied. The command reads a local file and does not modify it. Inputs larger than 25 MiB are rejected.

## What is checked

- Supported export format: `sanctum-scan-participant-provenance`.
- Supported schema version: `1.0.0`.
- Declared hash algorithm: `SHA-256`.
- Declared hash scope: `data`.
- Recomputed digest over the `data` object using the serialization flags documented in the M012 provenance API guide.
- Exchange/dispute envelope and evidence/event/message array structure.
- Evidence digest bindings for platform reviews and institutional attestations.
- Sequential attestation revisions, predecessor links, evidence digest binding and terminal withdrawal.
- Both directions of each reassessment link, including the exact prior revision, version and status.

## Exit behavior

- **Exit code 0:** all supported checks passed. The command prints the verified SHA-256 digest and the completed check names.
- **Non-zero exit code:** invalid or unsupported JSON, missing/unreadable file, file size limit exceeded, digest mismatch, or inconsistent provenance relationships. The command prints the validation errors.

## Example output

```text
Provenance manifest verified.
SHA-256: 0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef
Checks: manifest_format, schema_version, manifest_digest, evidence_hash_binding, attestation_revision_chains, reassessment_links, event_and_message_structure
```

The digest above is illustrative only.

## Trust boundaries

A successful verification establishes that the saved `data` object matches the digest declared in the manifest and that supported internal relationships are consistent.

It does not prove who exported or signed the file, when it was exported, or whether an evidence item, participant statement, platform review or institutional attestation is factually correct. The manifest is not digitally signed and has no external trusted timestamp.

The verifier does not inspect uploaded file bytes and does not make the files public. It validates recorded evidence digests and leaves file access to the existing authenticated, participant-scoped download endpoints.

## Supported version

The initial verifier accepts schema version `1.0.0` only. Unsupported versions fail closed so that a consumer does not silently misinterpret a future export structure.
