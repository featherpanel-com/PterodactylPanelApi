#!/usr/bin/env bash
# Copy this plugin into tests/api-parity/.plugin-mount/pterodactylpanelapi,
# honoring .featherexport exclusions so CI does not mount .git / node_modules / tests.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
DEST="${ROOT}/tests/api-parity/.plugin-mount/pterodactylpanelapi"
EXPORT_FILE="${ROOT}/.featherexport"

rm -rf "${DEST}"
mkdir -p "${DEST}"

RSYNC_EXCLUDES=(
  --exclude '.git/'
  --exclude 'tests/'
  --exclude '.github/'
  --exclude 'node_modules/'
  --exclude 'Frontend/App/node_modules/'
  --exclude 'Frontend/App/dist/'
  --exclude '.plugin-mount/'
)

if [[ -f "${EXPORT_FILE}" ]]; then
  while IFS= read -r line || [[ -n "${line}" ]]; do
    line="${line%%#*}"
    line="$(echo "${line}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    [[ -z "${line}" ]] && continue
    RSYNC_EXCLUDES+=(--exclude "${line}")
  done < "${EXPORT_FILE}"
fi

rsync -a "${RSYNC_EXCLUDES[@]}" "${ROOT}/" "${DEST}/"

# Folder name must match conf.yml identifier.
IDENTIFIER="$(grep -E '^\s*identifier:' "${DEST}/conf.yml" | head -1 | sed -E 's/^[[:space:]]*identifier:[[:space:]]*//; s/["'\'']//g')"
if [[ "${IDENTIFIER}" != "pterodactylpanelapi" ]]; then
  echo "Unexpected plugin identifier: ${IDENTIFIER}" >&2
  exit 1
fi

echo "Prepared plugin mount at ${DEST}"
