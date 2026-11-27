#!/usr/bin/env bash
# Boot + bootstrap + capture one panel.
# Usage: run-panel.sh <ptero-v1|ptero-v2|feather>
set -euo pipefail

PANEL="${1:?panel id required}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_ROOT="$(cd "${DIR}/.." && pwd)"
OUT_DIR="${PARITY_ROOT}/.artifacts"
CRED_DIR="${PARITY_ROOT}/.credentials"
mkdir -p "${OUT_DIR}" "${CRED_DIR}"

case "${PANEL}" in
  ptero-v1)
    COMPOSE_FILE="${PARITY_ROOT}/compose/ptero-v1.yml"
    BASE_URL="http://127.0.0.1:8081"
    HEALTH_URL="${BASE_URL}"
    MAJOR=1
    ;;
  ptero-v2)
    COMPOSE_FILE="${PARITY_ROOT}/compose/ptero-v2.yml"
    BASE_URL="http://127.0.0.1:8082"
    HEALTH_URL="${BASE_URL}"
    MAJOR=2
    ;;
  feather)
    COMPOSE_FILE="${PARITY_ROOT}/compose/feather.yml"
    BASE_URL="http://127.0.0.1:8083"
    HEALTH_URL="${BASE_URL}/api/system/settings"
    MAJOR=feather
    bash "${DIR}/prepare-plugin-mount.sh"
    ;;
  *)
    echo "Unknown panel: ${PANEL}" >&2
    exit 2
    ;;
esac

COMPOSE=(docker compose -f "${COMPOSE_FILE}")
chmod +x "${DIR}"/*.sh

echo "==> [${PANEL}] pull + up"
"${COMPOSE[@]}" pull
"${COMPOSE[@]}" up -d

cleanup() {
  "${COMPOSE[@]}" logs --no-color --tail=120 > "${OUT_DIR}/${PANEL}-compose.log" 2>/dev/null || true
}
trap cleanup EXIT

echo "==> [${PANEL}] wait for ${HEALTH_URL}"
if ! bash "${DIR}/wait-http.sh" "${HEALTH_URL}" 420 "${PANEL}"; then
  echo "==> [${PANEL}] failed readiness; dumping logs" >&2
  "${COMPOSE[@]}" ps >&2 || true
  "${COMPOSE[@]}" logs --tail=120 >&2 || true
  exit 1
fi

echo "==> [${PANEL}] bootstrap"
if [[ "${PANEL}" == "feather" ]]; then
  bash "${DIR}/bootstrap-featherpanel.sh" "${COMPOSE_FILE}" "${BASE_URL}" "${CRED_DIR}/${PANEL}.env"
else
  bash "${DIR}/bootstrap-pterodactyl.sh" "${COMPOSE_FILE}" "${BASE_URL}" "${CRED_DIR}/${PANEL}.env" "${MAJOR}"
fi

echo "==> [${PANEL}] capture probes"
node "${DIR}/capture-panel.mjs" "${PANEL}" "${CRED_DIR}/${PANEL}.env" "${OUT_DIR}/${PANEL}.json"

echo "==> [${PANEL}] done"
