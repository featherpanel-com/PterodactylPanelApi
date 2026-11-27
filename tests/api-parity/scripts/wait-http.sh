#!/usr/bin/env bash
set -euo pipefail

URL="${1:?url required}"
TIMEOUT_SEC="${2:-300}"
LABEL="${3:-${URL}}"

deadline=$((SECONDS + TIMEOUT_SEC))
while (( SECONDS < deadline )); do
  if curl -fsS -o /dev/null --max-time 5 "${URL}"; then
    echo "Ready: ${LABEL}"
    exit 0
  fi
  sleep 3
done

echo "Timed out waiting for ${LABEL} (${URL})" >&2
exit 1
