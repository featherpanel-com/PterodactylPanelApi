#!/usr/bin/env bash
# Local helper: run all three panels in parallel, then compare.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_ROOT="$(cd "${DIR}/.." && pwd)"
OUT_DIR="${PARITY_ROOT}/.artifacts"
mkdir -p "${OUT_DIR}"
chmod +x "${DIR}"/*.sh

pids=()
fail=0

run_one() {
  local panel="$1"
  local log="${OUT_DIR}/${panel}-run.log"
  echo "==> starting ${panel} (log: ${log})"
  (
    bash "${DIR}/run-panel.sh" "${panel}"
  ) >"${log}" 2>&1 &
  pids+=("$!")
}

run_one ptero-v1
run_one ptero-v2
run_one feather

for pid in "${pids[@]}"; do
  if ! wait "${pid}"; then
    fail=1
  fi
done

echo "==> panel logs"
for panel in ptero-v1 ptero-v2 feather; do
  echo "----- ${panel} -----"
  tail -n 40 "${OUT_DIR}/${panel}-run.log" || true
done

if [[ "${fail}" -ne 0 ]]; then
  echo "One or more panels failed" >&2
  exit 1
fi

echo "==> compare"
node "${DIR}/compare-snapshots.mjs" \
  "${OUT_DIR}/ptero-v1.json" \
  "${OUT_DIR}/ptero-v2.json" \
  "${OUT_DIR}/feather.json"
