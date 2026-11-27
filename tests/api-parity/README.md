# API parity (Pterodactyl v1 / v2 / FeatherPanel)

CI boots three live panels and fails unless this plugin matches **1:1**:

1. **Pterodactyl v1** (`ghcr.io/pterodactyl/panel:v1.15.2`) — full Application API catalog + live probes
2. **Pterodactyl v2** (`ghcr.io/pterodactyl/panel:v2.0.0-beta.3`) — shared Application surface (users / locations / nodes / servers)
3. **FeatherPanel** (`ghcr.io/mythicalltd/featherpanel-backend`) with **this repo** mounted as `pterodactylpanelapi`

## What is compared

- Application route catalog vs official Pterodactyl v1 routes (exact 1:1)
- Shared v1/v2 routes must exist on the plugin
- Live `GET`/`POST` probes for users + locations (status, fractal shape, attribute keys)
- Live list probes for nodes, servers, nests (nests: v1 + Feather only)
- `401` error envelope keys on Feather vs v1

## Run locally

```bash
cd tests/api-parity
chmod +x scripts/*.sh
./scripts/run-all.sh
```

Requires Docker, Docker Compose, Node.js 22+, rsync, and curl.

## Layout

| Path | Purpose |
| --- | --- |
| `docker-compose.yml` | Three-panel stack |
| `fixtures/ptero-*-application-routes.json` | Official Application routes |
| `scripts/prepare-plugin-mount.sh` | Stage plugin tree for bind-mount |
| `scripts/bootstrap-*.sh` | Migrate / admin / API keys |
| `scripts/run-parity.mjs` | Live 1:1 comparisons |
| `scripts/run-all.sh` | Full orchestration |

Credentials and the JSON report land in `.credentials/` (gitignored).
