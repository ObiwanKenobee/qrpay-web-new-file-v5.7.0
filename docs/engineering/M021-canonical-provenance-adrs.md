# M021 — Canonical Provenance ADR Reconciliation

**Status:** In progress  
**Date:** 2026-11-17  
**Depends on:** M012 participant export, M013 offline verifier, M014 verifier hardening, ADR 0017

## Goal

Give the already-implemented provenance capabilities unique canonical ADR
identifiers without changing the accepted identity and quantity decisions or
breaking existing links to the historical design files.

## Identifier decision

| Historical path | Historical heading / collision | Canonical record |
|---|---|---|
| `docs/architecture/decisions/0012-participant-provenance-export.md` | Reused 0012, already assigned to buyer identity verification | [ADR 0020 — Participant-scoped provenance export](../decisions/0020-participant-scoped-provenance-export.md) |
| `docs/architecture/decisions/0013-offline-provenance-verification.md` | Reused 0013, already assigned to exchange quantity/unit visibility | [ADR 0021 — Offline provenance manifest verification](../decisions/0021-offline-provenance-manifest-verification.md) |
| `docs/architecture/decisions/0014-provenance-verifier-consistency-hardening.md` | Used reserved 0014; no canonical ADR 0014 was accepted | [ADR 0022 — Provenance verifier consistency hardening](../decisions/0022-provenance-verifier-consistency-hardening.md) |

ADR 0012 and ADR 0013 remain unchanged and canonical at their accepted
identity and quantity paths. ADR 0014 remains intentionally unassigned.
Numbers 0020–0022 are used for the three implemented provenance decisions;
the next available number is 0023.

## Delivered in this branch

- Added canonical ADRs 0020, 0021 and 0022 under `docs/decisions/`.
- Updated the canonical ADR index and numbering guidance.
- Replaced the three conflicting legacy documents with compatibility pointers
  that retain their original file paths and link to the correct canonical ADR.
- Updated the feature map and roadmap so M012–M014 point to the canonical
  records and no longer report the identifier collision as unresolved.

## Compatibility rules

1. Do not delete or rename the three legacy paths in this milestone.
2. Do not renumber, rewrite or change the meaning of accepted ADR 0012 or 0013.
3. Do not assign ADR 0014 to the old proposal merely because a file exists with
   that number in its name.
4. New references to the implemented provenance decisions must use ADRs
   0020–0022. Legacy path files are pointers, not decision authorities.
5. Preserve the existing export schema, verifier behavior, API routes and
   database schema. M021 is documentation and decision-registry work only.

## Known engineering follow-up

M012's controller obtains the provenance context and evidence list in
separate service reads. The export therefore does not promise one database
snapshot under concurrent writes. This is not hidden by the ADR reconciliation;
a consistent-snapshot export is tracked separately as M026.

## Acceptance criteria

- [ ] The canonical ADR index has one unique entry per assigned identifier.
- [ ] ADR 0012 still identifies buyer identity verification.
- [ ] ADR 0013 still identifies exchange quantity and unit visibility.
- [ ] ADR 0014 remains unassigned.
- [ ] ADRs 0020–0022 accurately describe the merged M012–M014 behavior and
      explicitly state their trust boundaries.
- [ ] All three historical paths remain present as compatibility pointers.
- [ ] Feature map and roadmap use the canonical ADR references.
- [ ] No application code, routes, migrations or API payloads change.
- [ ] Markdown link/path review is complete. GitHub CI remains required for
      the normal repository verification gates, but no code test pass is
      claimed by this documentation-only milestone.

## Validation

The identifiers and paths were checked against the accepted ADR 0012 and 0013
files, the canonical ADR index, the M012 export controller/service, the M013
verifier/command and the M014 verifier implementation and unit tests.

The hosted-runner allocation issue remains tracked in
[repository issue #21](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/issues/21).
