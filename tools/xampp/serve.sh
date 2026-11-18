#!/usr/bin/env bash
#
# Local XAMPP/MySQL launcher for Sanctum Scan.
#
# Usage:
#   bash tools/xampp/serve.sh
#   PHP_BIN=/path/to/php SANCTUM_PORT=8010 bash tools/xampp/serve.sh
#
# This script never creates, drops, migrates, or seeds a database.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${PROJECT_ROOT}"

PHP_BIN="${PHP_BIN:-php}"
HOST="${SANCTUM_HOST:-127.0.0.1}"
PORT="${SANCTUM_PORT:-8000}"

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

if ! command -v "${PHP_BIN}" >/dev/null 2>&1; then
    fail "PHP CLI was not found. Add PHP to PATH or set PHP_BIN to the XAMPP PHP executable."
fi

if ! "${PHP_BIN}" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'; then
    "${PHP_BIN}" -v >&2 || true
    fail "Sanctum Scan requires PHP 8.2 or newer."
fi

"${PHP_BIN}" -r '
$required = ["pdo_mysql", "mbstring", "openssl", "fileinfo", "ctype", "json", "tokenizer", "xml"];
$missing = array_values(array_filter($required, fn ($extension) => ! extension_loaded($extension)));
if ($missing !== []) {
    fwrite(STDERR, "Missing PHP extensions: ".implode(", ", $missing).PHP_EOL);
    exit(1);
}
' || fail "Enable the required extensions in the PHP CLI used by this script."

if [[ ! -f "vendor/autoload.php" ]]; then
    fail "Composer dependencies are missing. Run 'composer install' from the project root first."
fi

if [[ ! -f ".env" ]]; then
    cp ".env.xampp.example" ".env"
    printf '%s\n' \
        "Created .env from .env.xampp.example." \
        "Set DB_DATABASE, DB_USERNAME, and DB_PASSWORD for your local XAMPP MySQL instance," \
        "then run this command again."
    exit 2
fi

read_env_value() {
    local key="$1"
    local line
    local value

    line="$(grep -E "^${key}=" .env | tail -n 1 || true)"
    value="${line#*=}"

    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"

    printf '%s' "${value}"
}

APP_ENV_VALUE="$(read_env_value APP_ENV)"
DB_CONNECTION_VALUE="$(read_env_value DB_CONNECTION)"
DB_DATABASE_VALUE="$(read_env_value DB_DATABASE)"
APP_KEY_VALUE="$(read_env_value APP_KEY)"

[[ "${APP_ENV_VALUE}" == "local" ]] \
    || fail "APP_ENV must be 'local'. This launcher refuses non-local environments."

[[ "${DB_CONNECTION_VALUE}" == "mysql" ]] \
    || fail "DB_CONNECTION must be 'mysql' for the XAMPP launcher."

[[ -n "${DB_DATABASE_VALUE}" && "${DB_DATABASE_VALUE}" != "database_name" ]] \
    || fail "Set DB_DATABASE in .env to your local database name (for example sanctum_scan_local)."

if [[ -z "${APP_KEY_VALUE//[[:space:]]/}" ]]; then
    printf '%s\n' "APP_KEY is empty; generating a key for this local environment."
    "${PHP_BIN}" artisan key:generate --force --no-ansi
fi

printf '\n%s\n' "Checking Laravel configuration and database connectivity..."
"${PHP_BIN}" artisan config:clear --no-ansi
"${PHP_BIN}" artisan db:show --no-ansi

printf '\n%s\n' "Starting Sanctum Scan at http://${HOST}:${PORT}"
printf '%s\n' "Keep this terminal open. Press Ctrl+C to stop the development server."

exec "${PHP_BIN}" artisan serve --host="${HOST}" --port="${PORT}"
