#!/usr/bin/env bash
# Register the first FeatherPanel admin, apply this plugin's SQL migrations,
# and insert a Pterodactyl-compatible Application API key.
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8083}"
OUT_FILE="${2:?output env file required}"
COMPOSE_FILE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/docker-compose.yml"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
COMPOSE=(docker compose -f "${COMPOSE_FILE}")

EMAIL="admin@parity.local"
USERNAME="parityadmin"
PASSWORD="ParityAdmin!123"
API_KEY="ptla_parity_ci_featherpanel_admin_key_001"

echo "==> Bootstrapping FeatherPanel at ${BASE_URL}"

# First account becomes administrator.
register_code="$(curl -sS -o /tmp/fp-register.json -w '%{http_code}' \
  -X PUT "${BASE_URL}/api/user/auth/register" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d "{\"username\":\"${USERNAME}\",\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\",\"first_name\":\"Parity\",\"last_name\":\"Admin\"}")"

if [[ "${register_code}" != "200" && "${register_code}" != "201" ]]; then
  # Already registered from a previous attempt is OK.
  if ! grep -q 'USERNAME_ALREADY_EXISTS\|EMAIL_ALREADY_EXISTS\|already' /tmp/fp-register.json 2>/dev/null; then
    echo "FeatherPanel register failed: HTTP ${register_code}" >&2
    cat /tmp/fp-register.json >&2 || true
    exit 1
  fi
fi

USER_ID="$("${COMPOSE[@]}" exec -T feather-db mariadb -N -ufeatherpanel -pfeatherpanel_password featherpanel \
  -e "SELECT id FROM featherpanel_users WHERE email='${EMAIL}' LIMIT 1;" | tr -d '\r[:space:]')"

if [[ -z "${USER_ID}" ]]; then
  echo "Could not resolve FeatherPanel user id for ${EMAIL}" >&2
  exit 1
fi

echo "FeatherPanel admin user id=${USER_ID}"

# Apply plugin migrations (install path is skipped when bind-mounting source).
for sql in \
  "${ROOT}/Migrations/2025-09-26-15.14-pterodactyl-api-key.sql" \
  "${ROOT}/Migrations/2025-11-11-09.00-pterodactyl-api-key-type.sql"
do
  echo "Applying $(basename "${sql}")"
  "${COMPOSE[@]}" exec -T feather-db mariadb -ufeatherpanel -pfeatherpanel_password featherpanel < "${sql}" \
    || true
done

# Track addon as installed/enabled when the table exists.
"${COMPOSE[@]}" exec -T feather-db mariadb -ufeatherpanel -pfeatherpanel_password featherpanel -e \
  "INSERT INTO featherpanel_addons (name, enabled, deleted, locked)
   SELECT 'pterodactylpanelapi', 'true', 'false', 'false'
   FROM DUAL
   WHERE NOT EXISTS (
     SELECT 1 FROM featherpanel_addons WHERE name='pterodactylpanelapi' AND deleted='false'
  );" 2>/dev/null || true

# Insert Application (admin) API key used by the parity runner.
"${COMPOSE[@]}" exec -T feather-db mariadb -ufeatherpanel -pfeatherpanel_password featherpanel -e \
  "DELETE FROM featherpanel_pterodactylpanelapi_pterodactyl_api_key WHERE \`key\`='${API_KEY}';
   INSERT INTO featherpanel_pterodactylpanelapi_pterodactyl_api_key
     (name, \`key\`, type, created_by, deleted)
   VALUES ('parity-ci', '${API_KEY}', 'admin', ${USER_ID}, 'false');"

# Restart backend so FrankenPHP worker reloads addon routes after DB prep.
"${COMPOSE[@]}" restart feather-backend
sleep 5
"$(dirname "${BASH_SOURCE[0]}")/wait-http.sh" "${BASE_URL}/api/system/settings" 300 "feather-backend (post-restart)"

# Smoke check through the plugin Application API.
code="$(curl -sS -o /tmp/fp-smoke.json -w '%{http_code}' \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Accept: application/vnd.pterodactyl.v1+json" \
  "${BASE_URL}/api/application/users")"
if [[ "${code}" != "200" ]]; then
  echo "FeatherPanel plugin Application API smoke failed: HTTP ${code}" >&2
  cat /tmp/fp-smoke.json >&2 || true
  echo "--- backend logs (tail) ---" >&2
  "${COMPOSE[@]}" logs --tail=80 feather-backend >&2 || true
  exit 1
fi

{
  echo "BASE_URL=${BASE_URL}"
  echo "API_KEY=${API_KEY}"
  echo "PANEL=feather"
  echo "MAJOR=feather"
} > "${OUT_FILE}"

echo "Wrote credentials to ${OUT_FILE}"
