#!/usr/bin/env bash
#
# Single local entry point for the ADR 0016 gates.
#
# Usage:
#   composer check                  # full suite plus gates
#   SKIP_TESTS=1 composer check     # fast gates only
#
# CI calls this script as well, so a green local run and a green CI run execute
# the same commands. The MySQL migration/concurrency job is separate because it
# needs a disposable MySQL service.

set -euo pipefail

cd "$(dirname "$0")/.."

step() {
    printf '\n==> %s\n' "$1"
}

step "G1 conflict markers"
php tools/check-conflict-markers.php

step "G2 PHP syntax"
php tools/check-php-syntax.php

step "G7 composer validation"
composer validate --strict --no-check-publish

step "G7 dependency audit"
php tools/check-dependency-audit.php

if [[ "${SKIP_TESTS:-0}" != "1" ]]; then
    step "G3 full test suite"
    vendor/bin/phpunit --colors=never
else
    echo "G3 skipped (SKIP_TESTS=1)."
fi

step "G8 Pint on changed files"

if [[ "${EVENT_NAME:-}" == "pull_request" && -n "${BASE_REF:-}" ]]; then
    base="origin/${BASE_REF}"
elif [[ -n "${BASE_SHA:-}" && ! "${BASE_SHA}" =~ ^0+$ ]] && git cat-file -e "${BASE_SHA}^{commit}" 2>/dev/null; then
    base="${BASE_SHA}"
else
    base=""
    for candidate in origin/main main; do
        if git rev-parse --verify "${candidate}" >/dev/null 2>&1; then
            base="${candidate}"
            break
        fi
    done
fi

if [[ -n "${base}" ]]; then
    merge_base="$(git merge-base HEAD "${base}" 2>/dev/null || true)"
    range="${merge_base:-${base}}"
elif git rev-parse --verify HEAD~1 >/dev/null 2>&1; then
    range="$(git rev-parse HEAD~1)"
    echo "No base ref found; checking the last commit instead."
else
    range=""
    echo "No diff range available; checking the Sanctum Scan slice and tooling instead."
fi

style_fallback_paths=(
    app/Services/SanctumScan
    app/Http/Controllers/Admin/SanctumScanExchangeSettlementController.php
    app/Http/Controllers/Api/User/SanctumScanExchangeController.php
    app/Http/Controllers/Api/Merchant/SanctumScanExchangeMerchantController.php
    tests/Feature/SanctumScan
    tests/Feature/RouteRegistryTest.php
    tests/Unit/SanctumScan
    tools
)

pint_targets=()

if [[ -n "${range}" ]]; then
    while IFS= read -r file; do
        [[ -n "${file}" ]] && pint_targets+=("${file}")
    done < <(git diff --diff-filter=ACMRT --name-only "${range}" -- '*.php' | grep -v '\.blade\.php$' || true)
    while IFS= read -r file; do
        [[ -n "${file}" ]] && pint_targets+=("${file}")
    done < <(git ls-files --others --exclude-standard -- '*.php' | grep -v '\.blade\.php$' || true)
fi

if [[ ${#pint_targets[@]} -eq 0 ]]; then
    existing_fallback=()
    for path in "${style_fallback_paths[@]}"; do
        [[ -e "${path}" ]] && existing_fallback+=("${path}")
    done
    pint_targets=("${existing_fallback[@]}")
fi

if [[ ${#pint_targets[@]} -eq 0 ]]; then
    echo "G8 skipped: no PHP files to check."
else
    printf 'Pint targets: %s\n' "${pint_targets[*]}"
    vendor/bin/pint --test "${pint_targets[@]}"
fi

step "Verification gates passed"
