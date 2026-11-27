#!/usr/bin/env bash
# Migrate a Pterodactyl panel, create admin, mint Application + Client API keys.
# Usage: bootstrap-pterodactyl.sh <compose-file> <base-url> <out-env-file> <major>
set -euo pipefail

COMPOSE_FILE="${1:?compose file required}"
BASE_URL="${2:?base url required}"
OUT_FILE="${3:?output env file required}"
MAJOR="${4:-1}"

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE=(docker compose -f "${COMPOSE_FILE}")

echo "==> Bootstrapping Pterodactyl v${MAJOR} (${BASE_URL})"

"${COMPOSE[@]}" exec -T panel php artisan migrate --seed --force --no-interaction

"${COMPOSE[@]}" exec -T panel php artisan p:user:make \
  --email=admin@parity.local \
  --username=parityadmin \
  --name-first=Parity \
  --name-last=Admin \
  --password='ParityAdmin!123' \
  --admin=1 \
  --no-interaction || true

"${COMPOSE[@]}" cp "${DIR}/mint-ptero-keys.php" panel:/tmp/mint-ptero-keys.php

KEYS_JSON="$("${COMPOSE[@]}" exec -T panel php /tmp/mint-ptero-keys.php "${MAJOR}" | tr -d '\r' | tail -n 1)"
APP_TOKEN="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["application"])' "${KEYS_JSON}")"
CLIENT_TOKEN="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["client"])' "${KEYS_JSON}")"

if [[ -z "${APP_TOKEN}" || "${APP_TOKEN}" != ptla_* ]]; then
  echo "Failed to mint Application API key: '${APP_TOKEN}' (${KEYS_JSON})" >&2
  exit 1
fi
if [[ -z "${CLIENT_TOKEN}" || "${CLIENT_TOKEN}" != ptlc_* ]]; then
  echo "Failed to mint Client API key: '${CLIENT_TOKEN}' (${KEYS_JSON})" >&2
  exit 1
fi

code="$(curl -sS -o /tmp/ptero-smoke.json -w '%{http_code}' \
  -H "Authorization: Bearer ${APP_TOKEN}" \
  -H "Accept: application/vnd.pterodactyl.v1+json" \
  "${BASE_URL}/api/application/users")"
if [[ "${code}" != "200" ]]; then
  echo "Application API smoke failed: HTTP ${code}" >&2
  cat /tmp/ptero-smoke.json >&2 || true
  exit 1
fi

ccode="$(curl -sS -o /tmp/ptero-client-smoke.json -w '%{http_code}' \
  -H "Authorization: Bearer ${CLIENT_TOKEN}" \
  -H "Accept: application/json" \
  "${BASE_URL}/api/client/account")"
if [[ "${ccode}" != "200" ]]; then
  echo "Client API smoke failed: HTTP ${ccode}" >&2
  cat /tmp/ptero-client-smoke.json >&2 || true
  exit 1
fi

{
  echo "BASE_URL=${BASE_URL}"
  echo "API_KEY=${APP_TOKEN}"
  echo "CLIENT_API_KEY=${CLIENT_TOKEN}"
  echo "PANEL=ptero-v${MAJOR}"
  echo "MAJOR=${MAJOR}"
} > "${OUT_FILE}"

echo "Wrote credentials to ${OUT_FILE}"
