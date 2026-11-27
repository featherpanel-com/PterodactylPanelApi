# API parity (Application + Client)

CI boots **Pterodactyl v1**, **Pterodactyl v2**, and **FeatherPanel + this plugin** in parallel, then:

1. Asserts Application route catalog 1:1 vs Pterodactyl v1  
2. Reports Client route coverage vs upstream  
3. Runs an **exhaustive probe suite** for both **admin (Application / `ptla_`)** and **client (`ptlc_`)** APIs — lists, filters, includes, CRUD, nested resources, auth failures  
4. Compares status + Fractal shapes + attribute keys 1:1  
5. Exports an **OpenAPI 3** schema artifact (`openapi.parity.json`) with live request/response examples from all three panels  

## Jobs

| Job | What it does |
| --- | --- |
| `Route catalog` | Fast offline Application (+ Client coverage) check |
| `Pterodactyl v1` | Boot, mint admin+client keys, full probe snapshot |
| `Pterodactyl v2` | Same for v2 |
| `FeatherPanel + plugin` | Same with this repo mounted as the addon |
| `Compare 1:1` | Fail on shape/status drift; upload OpenAPI schema |

## Local

```bash
cd tests/api-parity
./scripts/run-all.sh
node scripts/compare-snapshots.mjs .artifacts/ptero-v1.json .artifacts/ptero-v2.json .artifacts/feather.json
# OpenAPI written to .artifacts/openapi.parity.json
```

## Artifacts

| File | Purpose |
| --- | --- |
| `*.json` snapshots | Every probe: request, status, shape, sample body |
| `parity-report.json` | Pass/fail details + coverage |
| `openapi.parity.json` | Exported API schema (Application + Client) |

## Keys

Bootstraps mint both:

- `API_KEY` — Application admin key (`ptla_…`)  
- `CLIENT_API_KEY` — Client key (`ptlc_…`)  
