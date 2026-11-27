#!/usr/bin/env bash
# Migrate a Pterodactyl panel, create admin, mint Application API key.
# Usage: bootstrap-pterodactyl.sh <compose-file> <base-url> <out-env-file> <major>
set -euo pipefail

COMPOSE_FILE="${1:?compose file required}"
BASE_URL="${2:?base url required}"
OUT_FILE="${3:?output env file required}"
MAJOR="${4:-1}"

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE=(docker compose -f "${COMPOSE_FILE}")

echo "==> Bootstrapping Pterodactyl v${MAJOR} (${BASE_URL})"

# Entrypoint migrates on v1; v2 leaves migrate explicit. Re-run is idempotent.
"${COMPOSE[@]}" exec -T panel php artisan migrate --seed --force --no-interaction

"${COMPOSE[@]}" exec -T panel php artisan p:user:make \
  --email=admin@parity.local \
  --username=parityadmin \
  --name-first=Parity \
  --name-last=Admin \
  --password='ParityAdmin!123' \
  --admin=1 \
  --no-interaction || true

# Copy mint script in (compose only mounts the entrypoint wrapper).
"${COMPOSE[@]}" cp "${DIR}/mint-ptero-app-key.php" panel:/tmp/mint-ptero-app-key.php

TOKEN="$("${COMPOSE[@]}" exec -T panel php /tmp/mint-ptero-app-key.php "${MAJOR}" \
  | tr -d '\r' | tail -n 1 | tr -d '[:space:]')"

if [[ -z "${TOKEN}" || "${TOKEN}" != ptla_* ]]; then
  echo "Failed to mint Application API key: '${TOKEN}'" >&2
  "${COMPOSE[@]}" logs --tail=80 panel >&2 || true
  exit 1
fi

code="$(curl -sS -o /tmp/ptero-smoke.json -w '%{http_code}' \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/vnd.pterodactyl.v1+json" \
  "${BASE_URL}/api/application/users")"
if [[ "${code}" != "200" ]]; then
  echo "Application API smoke failed: HTTP ${code}" >&2
  cat /tmp/ptero-smoke.json >&2 || true
  exit 1
fi

{
  echo "BASE_URL=${BASE_URL}"
  echo "API_KEY=${TOKEN}"
  echo "PANEL=ptero-v${MAJOR}"
  echo "MAJOR=${MAJOR}"
} > "${OUT_FILE}"

echo "Wrote credentials to ${OUT_FILE}"
