# Legacy provenance design note — canonical decision ADR 0022

**Status:** Compatibility pointer; not a canonical ADR  
**Original draft date:** 2026-11-14

This historical path was originally titled “ADR 0014: Provenance verifier
consistency hardening”. The canonical ADR index intentionally leaves 0014
unassigned; an old proposed design note does not consume or reserve a canonical
ADR number.

Do not cite this file as ADR 0014. The implemented verifier-hardening decision
is now recorded canonically in
[`ADR 0022 — Provenance verifier consistency hardening`](../../decisions/0022-provenance-verifier-consistency-hardening.md).
It depends on canonical ADR 0020 (the export contract) and ADR 0021 (the
offline verifier).

This pointer is intentionally retained so existing links to the historical
design note continue to resolve. The canonical record contains the implemented
structural checks, fail-closed behaviour, limitations and test evidence.
