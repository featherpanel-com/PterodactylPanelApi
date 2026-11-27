#!/usr/bin/env bash
# Migrate a Pterodactyl panel container, create an admin user, mint an Application API key.
# Usage: bootstrap-pterodactyl.sh <compose-service> <base-url> <out-env-file> [panel-major]
set -euo pipefail

SERVICE="${1:?compose service required}"
BASE_URL="${2:?base url required}"
OUT_FILE="${3:?output env file required}"
MAJOR="${4:-1}"

COMPOSE_FILE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/docker-compose.yml"
COMPOSE=(docker compose -f "${COMPOSE_FILE}")

echo "==> Bootstrapping ${SERVICE} (${BASE_URL})"

"${COMPOSE[@]}" exec -T "${SERVICE}" php artisan migrate --seed --force --no-interaction

"${COMPOSE[@]}" exec -T "${SERVICE}" php artisan p:user:make \
  --email=admin@parity.local \
  --username=parityadmin \
  --name-first=Parity \
  --name-last=Admin \
  --password='ParityAdmin!123' \
  --admin=1 \
  --no-interaction || true

# Mint an Application API key with full ACL via artisan tinker.
# Prints only the bearer token on the last line.
TOKEN="$("${COMPOSE[@]}" exec -T "${SERVICE}" php artisan tinker --execute="
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
" | tr -d '\r' | tail -n 1 | tr -d '[:space:]")"

if [[ -z "${TOKEN}" || "${TOKEN}" != ptla_* ]]; then
  echo "Failed to mint Application API key for ${SERVICE}: '${TOKEN}'" >&2
  exit 1
fi

# Smoke check
code="$(curl -sS -o /tmp/ptero-smoke.json -w '%{http_code}' \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/vnd.pterodactyl.v1+json" \
  "${BASE_URL}/api/application/users")"
if [[ "${code}" != "200" ]]; then
  echo "Application API smoke failed for ${SERVICE}: HTTP ${code}" >&2
  cat /tmp/ptero-smoke.json >&2 || true
  exit 1
fi

{
  echo "BASE_URL=${BASE_URL}"
  echo "API_KEY=${TOKEN}"
  echo "PANEL=${SERVICE}"
  echo "MAJOR=${MAJOR}"
} > "${OUT_FILE}"

echo "Wrote credentials to ${OUT_FILE}"
