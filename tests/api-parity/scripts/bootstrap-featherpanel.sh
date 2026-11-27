#!/usr/bin/env bash
# Register FeatherPanel admin, apply plugin SQL, insert Application API key.
# Usage: bootstrap-featherpanel.sh <compose-file> <base-url> <out-env-file>
set -euo pipefail

COMPOSE_FILE="${1:?compose file required}"
BASE_URL="${2:-http://127.0.0.1:8083}"
OUT_FILE="${3:?output env file required}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
COMPOSE=(docker compose -f "${COMPOSE_FILE}")

EMAIL="admin@parity.local"
USERNAME="parityadmin"
PASSWORD="ParityAdmin!123"
API_KEY="ptla_parity_ci_featherpanel_admin_key_001"

echo "==> Bootstrapping FeatherPanel at ${BASE_URL}"

register_code="$(curl -sS -o /tmp/fp-register.json -w '%{http_code}' \
  -X PUT "${BASE_URL}/api/user/auth/register" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d "{\"username\":\"${USERNAME}\",\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\",\"first_name\":\"Parity\",\"last_name\":\"Admin\"}")"

if [[ "${register_code}" != "200" && "${register_code}" != "201" ]]; then
  if ! grep -q 'USERNAME_ALREADY_EXISTS\|EMAIL_ALREADY_EXISTS\|already' /tmp/fp-register.json 2>/dev/null; then
    echo "FeatherPanel register failed: HTTP ${register_code}" >&2
    cat /tmp/fp-register.json >&2 || true
    exit 1
  fi
fi

USER_ID="$("${COMPOSE[@]}" exec -T db mariadb -N -ufeatherpanel -pfeatherpanel_password featherpanel \
  -e "SELECT id FROM featherpanel_users WHERE email='${EMAIL}' LIMIT 1;" | tr -d '\r[:space:]')"

if [[ -z "${USER_ID}" ]]; then
  echo "Could not resolve FeatherPanel user id for ${EMAIL}" >&2
  exit 1
fi

echo "FeatherPanel admin user id=${USER_ID}"

for sql in \
  "${ROOT}/Migrations/2025-09-26-15.14-pterodactyl-api-key.sql" \
  "${ROOT}/Migrations/2025-11-11-09.00-pterodactyl-api-key-type.sql"
do
  echo "Applying $(basename "${sql}")"
  "${COMPOSE[@]}" exec -T db mariadb -ufeatherpanel -pfeatherpanel_password featherpanel < "${sql}" || true
done

"${COMPOSE[@]}" exec -T db mariadb -ufeatherpanel -pfeatherpanel_password featherpanel -e \
  "INSERT INTO featherpanel_addons (name, enabled, deleted, locked)
   SELECT 'pterodactylpanelapi', 'true', 'false', 'false'
   FROM DUAL
   WHERE NOT EXISTS (
     SELECT 1 FROM featherpanel_addons WHERE name='pterodactylpanelapi' AND deleted='false'
  );" 2>/dev/null || true

"${COMPOSE[@]}" exec -T db mariadb -ufeatherpanel -pfeatherpanel_password featherpanel -e \
  "DELETE FROM featherpanel_pterodactylpanelapi_pterodactyl_api_key WHERE \`key\`='${API_KEY}';
   INSERT INTO featherpanel_pterodactylpanelapi_pterodactyl_api_key
     (name, \`key\`, type, created_by, deleted)
   VALUES ('parity-ci', '${API_KEY}', 'admin', ${USER_ID}, 'false');"

# Seed realms (Pterodactyl nests) so Application /nests returns a non-empty list like upstream v1.
"${COMPOSE[@]}" exec -T db mariadb -ufeatherpanel -pfeatherpanel_password featherpanel -e \
  "INSERT INTO featherpanel_realms (name, description)
   SELECT 'Minecraft', 'Minecraft related eggs and services.' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM featherpanel_realms WHERE name='Minecraft');
   INSERT INTO featherpanel_realms (name, description)
   SELECT 'Source Engine', 'Includes SourceFlds and other Source engine games.' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM featherpanel_realms WHERE name='Source Engine');
   INSERT INTO featherpanel_realms (name, description)
   SELECT 'Voice Servers', 'Voice servers such as Mumble and Teamspeak 3.' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM featherpanel_realms WHERE name='Voice Servers');
   INSERT INTO featherpanel_realms (name, description)
   SELECT 'Rust', 'Rust eggs related to game hosting.' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM featherpanel_realms WHERE name='Rust');"

"${COMPOSE[@]}" restart backend
sleep 5
"$(dirname "${BASH_SOURCE[0]}")/wait-http.sh" "${BASE_URL}/api/system/settings" 180 "feather-backend (post-restart)"

code="$(curl -sS -o /tmp/fp-smoke.json -w '%{http_code}' \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Accept: application/vnd.pterodactyl.v1+json" \
  "${BASE_URL}/api/application/users")"
if [[ "${code}" != "200" ]]; then
  echo "FeatherPanel plugin Application API smoke failed: HTTP ${code}" >&2
  cat /tmp/fp-smoke.json >&2 || true
  "${COMPOSE[@]}" logs --tail=80 backend >&2 || true
  exit 1
fi

{
  echo "BASE_URL=${BASE_URL}"
  echo "API_KEY=${API_KEY}"
  echo "PANEL=feather"
  echo "MAJOR=feather"
} > "${OUT_FILE}"

echo "Wrote credentials to ${OUT_FILE}"
