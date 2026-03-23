#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

read_env_value() {
    local key="$1"
    local file="$2"

    if [[ ! -f "$file" ]]; then
        return 0
    fi

    awk -v key="$key" '
        {
            line = $0
            sub(/\r$/, "", line)

            if (line ~ /^[[:space:]]*#/ || line !~ /=/) {
                next
            }

            current = substr(line, 1, index(line, "=") - 1)
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", current)
            if (current != key) {
                next
            }

            value = substr(line, index(line, "=") + 1)
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
            print value
            exit
        }
    ' "$file"
}

trim_quotes() {
    local value="$1"

    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"

    printf '%s' "$value"
}

resolve_value() {
    local explicit_value="$1"
    local primary_key="$2"
    local fallback_key="$3"
    local default_value="$4"
    local env_file="$ROOT_DIR/.env"
    local value=""

    if [[ -n "$explicit_value" ]]; then
        printf '%s' "$explicit_value"
        return 0
    fi

    value="$(read_env_value "$primary_key" "$env_file")"
    value="$(trim_quotes "$value")"
    if [[ -n "$value" ]]; then
        printf '%s' "$value"
        return 0
    fi

    value="$(read_env_value "$fallback_key" "$env_file")"
    value="$(trim_quotes "$value")"
    if [[ -n "$value" ]]; then
        printf '%s' "$value"
        return 0
    fi

    printf '%s' "$default_value"
}

TEST_HOSTNAME="$(resolve_value "${MYSQL_TEST_HOSTNAME:-}" "database.tests.hostname" "database.default.hostname" "127.0.0.1")"
TEST_DATABASE="$(resolve_value "${MYSQL_TEST_DATABASE:-}" "database.tests.database" "" "webschedulr_test")"
TEST_USERNAME="$(resolve_value "${MYSQL_TEST_USERNAME:-}" "database.tests.username" "database.default.username" "root")"
TEST_PASSWORD="$(resolve_value "${MYSQL_TEST_PASSWORD:-}" "database.tests.password" "database.default.password" "")"
TEST_DRIVER="$(resolve_value "${MYSQL_TEST_DRIVER:-}" "database.tests.DBDriver" "database.default.DBDriver" "MySQLi")"
TEST_PREFIX="$(resolve_value "${MYSQL_TEST_PREFIX:-}" "database.tests.DBPrefix" "database.default.DBPrefix" "xs_")"
TEST_PORT="$(resolve_value "${MYSQL_TEST_PORT:-}" "database.tests.port" "database.default.port" "3306")"

if [[ $# -eq 0 ]]; then
    set -- tests/integration
fi

echo "Running MySQL integration tests against ${TEST_USERNAME}@${TEST_HOSTNAME}:${TEST_PORT}/${TEST_DATABASE}" >&2

exec env \
    "database.tests.hostname=${TEST_HOSTNAME}" \
    "database.tests.database=${TEST_DATABASE}" \
    "database.tests.username=${TEST_USERNAME}" \
    "database.tests.password=${TEST_PASSWORD}" \
    "database.tests.DBDriver=${TEST_DRIVER}" \
    "database.tests.DBPrefix=${TEST_PREFIX}" \
    "database.tests.port=${TEST_PORT}" \
    php vendor/bin/phpunit --configuration phpunit.mysql.xml.dist "$@"