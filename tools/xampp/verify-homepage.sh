#!/usr/bin/env bash
#
# HTTP smoke check for the local Sanctum Scan homepage.
#
# Usage:
#   bash tools/xampp/verify-homepage.sh
#   SANCTUM_BASE_URL=http://127.0.0.1:8010 bash tools/xampp/verify-homepage.sh

set -euo pipefail

BASE_URL="${SANCTUM_BASE_URL:-http://127.0.0.1:8000}"
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "${RESPONSE_FILE}"' EXIT

if ! command -v curl >/dev/null 2>&1; then
    printf '%s\n' "ERROR: curl is required for the homepage HTTP check." >&2
    exit 1
fi

printf 'Checking %s/\n' "${BASE_URL%/}"

if ! HTTP_STATUS="$(curl --silent --show-error --max-time 15 \
    --output "${RESPONSE_FILE}" \
    --write-out '%{http_code}' \
    "${BASE_URL%/}/")"; then
    printf '%s\n' "ERROR: The local server could not be reached." >&2
    exit 1
fi

if [[ "${HTTP_STATUS}" != "200" ]]; then
    printf 'ERROR: Expected HTTP 200, received HTTP %s.\n' "${HTTP_STATUS}" >&2
    printf '%s\n' "Inspect the Laravel server output and storage/logs/laravel.log."
    exit 1
fi

if ! grep -Eiq '<!doctype[[:space:]]+html|<html([[:space:]>])' "${RESPONSE_FILE}"; then
    printf '%s\n' "ERROR: The homepage returned HTTP 200 but did not contain an HTML document." >&2
    exit 1
fi

printf '%s\n' "PASS: GET / returned HTTP 200 and an HTML document."
