#!/usr/bin/env bash
set -euo pipefail

URL="${1:?url required}"
TIMEOUT_SEC="${2:-180}"
LABEL="${3:-${URL}}"
COMPOSE_FILE="${4:-}"
SERVICE="${5:-panel}"

deadline=$((SECONDS + TIMEOUT_SEC))
while (( SECONDS < deadline )); do
  if curl -fsS -o /dev/null --max-time 5 "${URL}"; then
    echo "Ready: ${LABEL}"
    exit 0
  fi

  # Fail fast if the panel container already exited.
  if [[ -n "${COMPOSE_FILE}" ]]; then
    status="$(docker compose -f "${COMPOSE_FILE}" ps -a --format json "${SERVICE}" 2>/dev/null \
      | head -n 1 \
      | sed -n 's/.*"State":"\([^"]*\)".*/\1/p' || true)"
    if [[ "${status}" == "exited" || "${status}" == "dead" ]]; then
      echo "Container ${SERVICE} is ${status} while waiting for ${LABEL}" >&2
      docker compose -f "${COMPOSE_FILE}" logs --tail=80 "${SERVICE}" >&2 || true
      exit 1
    fi
  fi
  sleep 3
done

echo "Timed out waiting for ${LABEL} (${URL})" >&2
exit 1
