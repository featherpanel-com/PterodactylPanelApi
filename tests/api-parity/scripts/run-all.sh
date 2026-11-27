#!/usr/bin/env bash
# Full local/CI entrypoint: prepare plugin, boot panels, bootstrap, run parity.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_ROOT="$(cd "${DIR}/.." && pwd)"
COMPOSE_FILE="${PARITY_ROOT}/docker-compose.yml"
CRED_DIR="${PARITY_ROOT}/.credentials"
COMPOSE=(docker compose -f "${COMPOSE_FILE}")

mkdir -p "${CRED_DIR}"
export PARITY_CRED_DIR="${CRED_DIR}"

echo "==> Preparing plugin mount"
bash "${DIR}/prepare-plugin-mount.sh"

echo "==> Starting panels (Pterodactyl v1, v2, FeatherPanel)"
"${COMPOSE[@]}" pull
"${COMPOSE[@]}" up -d

echo "==> Waiting for HTTP readiness"
bash "${DIR}/wait-http.sh" "http://127.0.0.1:8081" 420 "pterodactyl-v1"
bash "${DIR}/wait-http.sh" "http://127.0.0.1:8082" 420 "pterodactyl-v2"
bash "${DIR}/wait-http.sh" "http://127.0.0.1:8083/api/system/settings" 480 "featherpanel"

echo "==> Bootstrapping Pterodactyl v1"
bash "${DIR}/bootstrap-pterodactyl.sh" ptero-v1 "http://127.0.0.1:8081" "${CRED_DIR}/ptero-v1.env" 1

echo "==> Bootstrapping Pterodactyl v2"
bash "${DIR}/bootstrap-pterodactyl.sh" ptero-v2 "http://127.0.0.1:8082" "${CRED_DIR}/ptero-v2.env" 2

echo "==> Bootstrapping FeatherPanel + plugin"
bash "${DIR}/bootstrap-featherpanel.sh" "http://127.0.0.1:8083" "${CRED_DIR}/feather.env"

echo "==> Running API parity suite"
node "${DIR}/run-parity.mjs"

echo "==> Done"
