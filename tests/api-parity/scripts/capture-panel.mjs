#!/usr/bin/env node
/**
 * Hit one panel with the shared probe set and write a normalized snapshot JSON.
 * Usage: node capture-panel.mjs <panel-id> <cred-env-file> <out-json>
 */
import fs from "node:fs";
import path from "node:path";

const ACCEPT = "application/vnd.pterodactyl.v1+json";
const SEED = "parityseed";

const [, , panelId, credFile, outFile] = process.argv;
if (!panelId || !credFile || !outFile) {
  console.error("Usage: capture-panel.mjs <panel-id> <cred-env-file> <out-json>");
  process.exit(2);
}

function loadEnvFile(file) {
  const out = {};
  for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
    if (!line || line.startsWith("#") || !line.includes("=")) continue;
    const i = line.indexOf("=");
    out[line.slice(0, i)] = line.slice(i + 1);
  }
  return out;
}

function shapeOf(value, depth = 0) {
  if (depth > 8) return "deep";
  if (value === null) return "null";
  if (Array.isArray(value)) {
    if (value.length === 0) return ["array", "empty"];
    return ["array", shapeOf(value[0], depth + 1)];
  }
  const t = typeof value;
  if (t !== "object") return t;
  const out = {};
  for (const key of Object.keys(value).sort()) out[key] = shapeOf(value[key], depth + 1);
  return out;
}

function attributeKeys(payload) {
  if (!payload || typeof payload !== "object") return [];
  if (payload.object === "list" && Array.isArray(payload.data) && payload.data[0]?.attributes) {
    return Object.keys(payload.data[0].attributes).sort();
  }
  if (payload.attributes && typeof payload.attributes === "object") {
    return Object.keys(payload.attributes).sort();
  }
  return Object.keys(payload).sort();
}

function paginationKeys(payload) {
  if (!payload?.meta?.pagination) return null;
  return Object.keys(payload.meta.pagination).sort();
}

async function request(panel, method, routePath, body) {
  const url = panel.BASE_URL.replace(/\/$/, "") + routePath;
  const headers = {
    Accept: ACCEPT,
    Authorization: `Bearer ${panel.API_KEY}`,
  };
  const init = { method, headers };
  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    init.body = JSON.stringify(body);
  }
  const res = await fetch(url, init);
  const text = await res.text();
  let json = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    json = { _parse_error: true, _raw: text.slice(0, 400) };
  }
  return { status: res.status, json };
}

async function main() {
  const panel = loadEnvFile(credFile);
  if (!panel.BASE_URL || !panel.API_KEY) throw new Error(`Bad credentials file: ${credFile}`);

  const probes = {};
  const major = String(panel.MAJOR || panelId);

  probes.users_list = await request(panel, "GET", "/api/application/users?per_page=5");
  probes.users_create = await request(panel, "POST", "/api/application/users", {
    email: `${SEED}_${panelId}@example.com`,
    username: `${SEED}${panelId}`.replace(/[^a-z0-9]/gi, "").slice(0, 20),
    first_name: "Parity",
    last_name: "User",
    password: "ParityUser!12345",
  });
  probes.locations_list = await request(panel, "GET", "/api/application/locations?per_page=5");
  probes.locations_create = await request(panel, "POST", "/api/application/locations", {
    short: `p${SEED}`.slice(0, 8),
    long: `Parity Location ${SEED}`,
  });
  probes.nodes_list = await request(panel, "GET", "/api/application/nodes?per_page=5");
  probes.servers_list = await request(panel, "GET", "/api/application/servers?per_page=5");

  if (major === "1" || major === "feather" || panelId === "feather" || panelId === "ptero-v1") {
    probes.nests_list = await request(panel, "GET", "/api/application/nests?per_page=5");
  }

  const unauth = { BASE_URL: panel.BASE_URL, API_KEY: "ptla_invalid" };
  probes.unauth_users = await request(unauth, "GET", "/api/application/users");

  const snapshot = {
    panel: panelId,
    major,
    baseUrl: panel.BASE_URL,
    probes: Object.fromEntries(
      Object.entries(probes).map(([name, res]) => [
        name,
        {
          status: res.status,
          shape: shapeOf(res.json),
          attributeKeys: attributeKeys(res.json),
          paginationKeys: paginationKeys(res.json),
          topKeys: Object.keys(res.json || {}).sort(),
        },
      ]),
    ),
  };

  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  fs.writeFileSync(outFile, JSON.stringify(snapshot, null, 2) + "\n");
  console.log(`Wrote snapshot ${outFile}`);

  const required = ["users_list", "locations_list", "nodes_list", "servers_list"];
  for (const name of required) {
    if (snapshot.probes[name].status !== 200) {
      console.error(`Probe ${name} failed with status ${snapshot.probes[name].status}`);
      process.exit(1);
    }
  }
  if (![200, 201].includes(snapshot.probes.users_create.status)) {
    console.error(`users_create failed with status ${snapshot.probes.users_create.status}`);
    process.exit(1);
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
