#!/usr/bin/env bash
# Migrate a Pterodactyl panel, create admin, mint Application API key.
# Usage: bootstrap-pterodactyl.sh <compose-file> <base-url> <out-env-file> <major>
set -euo pipefail

COMPOSE_FILE="${1:?compose file required}"
BASE_URL="${2:?base url required}"
OUT_FILE="${3:?output env file required}"
MAJOR="${4:-1}"

COMPOSE=(docker compose -f "${COMPOSE_FILE}")

echo "==> Bootstrapping Pterodactyl v${MAJOR} (${BASE_URL})"

# Entrypoint already migrates; re-run is idempotent and fails loudly if SSL still broken.
"${COMPOSE[@]}" exec -T panel php artisan migrate --seed --force --no-interaction

"${COMPOSE[@]}" exec -T panel php artisan p:user:make \
  --email=admin@parity.local \
  --username=parityadmin \
  --name-first=Parity \
  --name-last=Admin \
  --password='ParityAdmin!123' \
  --admin=1 \
  --no-interaction || true

TOKEN="$("${COMPOSE[@]}" exec -T panel php artisan tinker --execute="
\$user = \\Pterodactyl\\Models\\User::query()->where('email', 'admin@parity.local')->firstOrFail();
\$secret = \\Illuminate\\Support\\Str::random(\\Pterodactyl\\Models\\ApiKey::KEY_LENGTH);
\$identifier = \\Pterodactyl\\Models\\ApiKey::generateTokenIdentifier(\\Pterodactyl\\Models\\ApiKey::TYPE_APPLICATION);
\$attrs = [
  'user_id' => \$user->id,
  'key_type' => \\Pterodactyl\\Models\\ApiKey::TYPE_APPLICATION,
  'identifier' => \$identifier,
  'token' => encrypt(\$secret),
  'memo' => 'parity-ci',
  'allowed_ips' => null,
  'r_servers' => 3,
  'r_nodes' => 3,
  'r_allocations' => 3,
  'r_users' => 3,
  'r_locations' => 3,
  'r_eggs' => 3,
  'r_database_hosts' => 3,
  'r_server_databases' => 3,
];
if (${MAJOR} === 1) {
  \$attrs['r_nests'] = 3;
}
\\Pterodactyl\\Models\\ApiKey::query()->create(\$attrs);
echo \$identifier . \$secret;
" | tr -d '\r' | tail -n 1 | tr -d '[:space:]')"

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
