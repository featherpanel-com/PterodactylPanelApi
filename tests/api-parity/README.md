# API parity (Pterodactyl v1 / v2 / FeatherPanel)

CI runs **three panel jobs in parallel**, then a compare job:

| Job | What it does |
| --- | --- |
| `Route catalog` | Fast offline check: plugin Application routes vs official Pterodactyl v1 |
| `Pterodactyl v1` | Boot panel, mint API key, capture probe snapshot |
| `Pterodactyl v2` | Same for v2 |
| `FeatherPanel + plugin` | Boot FeatherPanel with this repo mounted as the addon |
| `Compare 1:1` | Fail unless Feather matches v1 (full) and v2 (shared surface) |

Any panel job can fail on its own without waiting for the others.

## Local

```bash
cd tests/api-parity
./scripts/run-all.sh          # parallel local run
./scripts/run-panel.sh ptero-v1
./scripts/run-panel.sh ptero-v2
./scripts/run-panel.sh feather
node scripts/compare-snapshots.mjs .artifacts/ptero-v1.json .artifacts/ptero-v2.json .artifacts/feather.json
```

## Layout

| Path | Purpose |
| --- | --- |
| `compose/ptero-v1.yml` | Pterodactyl 1.x stack |
| `compose/ptero-v2.yml` | Pterodactyl 2.x stack |
| `compose/feather.yml` | FeatherPanel + plugin mount |
| `scripts/run-panel.sh` | Boot → bootstrap → capture one panel |
| `scripts/capture-panel.mjs` | Live probes → snapshot JSON |
| `scripts/compare-snapshots.mjs` | 1:1 status/shape/key comparison |
| `fixtures/ptero-*-application-routes.json` | Official Application routes |
