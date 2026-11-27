#!/usr/bin/env node
/**
 * Live API parity runner.
 *
 * 1) Ensures this plugin's Application routes match Pterodactyl v1 1:1.
 * 2) Hits Pterodactyl v1, Pterodactyl v2 (shared surface), and FeatherPanel+plugin.
 * 3) Fails unless status codes + response shapes match 1:1 for every probe.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const FIXTURES = path.resolve(__dirname, "../fixtures");
const ACCEPT = "application/vnd.pterodactyl.v1+json";

function loadEnvFile(file) {
  const out = {};
  for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
    if (!line || line.startsWith("#") || !line.includes("=")) continue;
    const i = line.indexOf("=");
    out[line.slice(0, i)] = line.slice(i + 1);
  }
  return out;
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, "utf8"));
}

function normalizePathParams(p) {
  return p
    .replace(/\{[^}]+\}/g, "{id}")
    .replace(/\/+/g, "/")
    .replace(/\/$/, "") || "/";
}

function routeKey(method, p) {
  return `${method.toUpperCase()} ${normalizePathParams(p)}`;
}

function extractPluginApplicationRoutes() {
  const routesDir = path.join(ROOT, "Routes/Application");
  const routes = [];
  for (const file of fs.readdirSync(routesDir)) {
    if (!file.endsWith(".php")) continue;
    const text = fs.readFileSync(path.join(routesDir, file), "utf8");
    const re = /new Route\(\s*'([^']+)'[\s\S]*?\[['"]([A-Z, ]+)['"]\]/g;
    let m;
    while ((m = re.exec(text))) {
      const routePath = m[1];
      const methods = m[2].split(",").map((x) => x.trim()).filter(Boolean);
      if (!routePath.startsWith("/api/application")) continue;
      for (const method of methods) {
        routes.push({ method, path: routePath });
      }
    }
  }
  const seen = new Set();
  return routes.filter((r) => {
    const k = routeKey(r.method, r.path);
    if (seen.has(k)) return false;
    seen.add(k);
    return true;
  });
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
  for (const key of Object.keys(value).sort()) {
    out[key] = shapeOf(value[key], depth + 1);
  }
  return out;
}

function stableStringify(value) {
  return JSON.stringify(value, null, 2);
}

async function request(panel, method, routePath, { body, expectJson = true } = {}) {
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
  if (expectJson && text) {
    try {
      json = JSON.parse(text);
    } catch {
      json = { _parse_error: true, _raw: text.slice(0, 500) };
    }
  }
  return { status: res.status, json, text, url, method };
}

function assertSame(label, a, b) {
  const left = stableStringify(a);
  const right = stableStringify(b);
  if (left !== right) {
    const err = new Error(`${label} mismatch`);
    err.details = { expected: a, actual: b };
    throw err;
  }
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

function fractalMetaShape(payload) {
  if (!payload?.meta?.pagination) return null;
  return Object.keys(payload.meta.pagination).sort();
}

async function probeUsersLifecycle(panels, suffix) {
  const email = `parity_${suffix}@example.com`;
  const username = `parity_${suffix}`.slice(0, 20);
  const createBody = {
    email,
    username,
    first_name: "Parity",
    last_name: "User",
    password: "ParityUser!12345",
  };

  const creates = {};
  for (const [name, panel] of Object.entries(panels)) {
    creates[name] = await request(panel, "POST", "/api/application/users", { body: createBody });
  }

  // All must succeed with the same status.
  const statuses = Object.fromEntries(Object.entries(creates).map(([k, v]) => [k, v.status]));
  const uniqueStatuses = [...new Set(Object.values(statuses))];
  if (uniqueStatuses.length !== 1 || ![200, 201].includes(uniqueStatuses[0])) {
    throw Object.assign(new Error("POST /api/application/users status mismatch"), {
      details: Object.fromEntries(
        Object.entries(creates).map(([k, v]) => [k, { status: v.status, body: v.json }]),
      ),
    });
  }

  const shapes = Object.fromEntries(
    Object.entries(creates).map(([k, v]) => [k, shapeOf(v.json)]),
  );
  const keys = Object.fromEntries(
    Object.entries(creates).map(([k, v]) => [k, attributeKeys(v.json)]),
  );

  // Feather must match Pterodactyl v1 exactly on shape + attribute keys.
  assertSame("user create shape feather vs ptero-v1", shapes["ptero-v1"], shapes.feather);
  assertSame("user create attribute keys feather vs ptero-v1", keys["ptero-v1"], keys.feather);

  // Shared surface with v2 (eggs/tags differ elsewhere; users stay shared).
  assertSame("user create shape feather vs ptero-v2", shapes["ptero-v2"], shapes.feather);
  assertSame("user create attribute keys feather vs ptero-v2", keys["ptero-v2"], keys.feather);

  // List endpoint shape.
  const lists = {};
  for (const [name, panel] of Object.entries(panels)) {
    lists[name] = await request(panel, "GET", "/api/application/users?per_page=5");
  }
  for (const [name, res] of Object.entries(lists)) {
    if (res.status !== 200) {
      throw Object.assign(new Error(`${name} GET /api/application/users failed`), {
        details: res,
      });
    }
  }
  assertSame(
    "users list shape feather vs ptero-v1",
    shapeOf(lists["ptero-v1"].json),
    shapeOf(lists.feather.json),
  );
  assertSame(
    "users list pagination keys feather vs ptero-v1",
    fractalMetaShape(lists["ptero-v1"].json),
    fractalMetaShape(lists.feather.json),
  );
  assertSame(
    "users list shape feather vs ptero-v2",
    shapeOf(lists["ptero-v2"].json),
    shapeOf(lists.feather.json),
  );

  return { creates, lists };
}

async function probeList(panels, routePath, { requireV2 = true } = {}) {
  const results = {};
  for (const [name, panel] of Object.entries(panels)) {
    if (!requireV2 && name === "ptero-v2") continue;
    results[name] = await request(panel, "GET", routePath);
  }
  for (const [name, res] of Object.entries(results)) {
    if (res.status !== 200) {
      throw Object.assign(new Error(`${name} GET ${routePath} expected 200`), {
        details: { status: res.status, body: res.json },
      });
    }
  }
  assertSame(
    `${routePath} shape feather vs ptero-v1`,
    shapeOf(results["ptero-v1"].json),
    shapeOf(results.feather.json),
  );
  if (requireV2 && results["ptero-v2"]) {
    assertSame(
      `${routePath} shape feather vs ptero-v2`,
      shapeOf(results["ptero-v2"].json),
      shapeOf(results.feather.json),
    );
  }
  return results;
}

async function probeLocations(panels, suffix) {
  const body = {
    short: `p${suffix}`.slice(0, 8),
    long: `Parity Location ${suffix}`,
  };
  const creates = {};
  for (const [name, panel] of Object.entries(panels)) {
    creates[name] = await request(panel, "POST", "/api/application/locations", { body });
  }
  const statuses = [...new Set(Object.values(creates).map((r) => r.status))];
  if (statuses.length !== 1 || ![200, 201].includes(statuses[0])) {
    throw Object.assign(new Error("POST /api/application/locations status mismatch"), {
      details: Object.fromEntries(
        Object.entries(creates).map(([k, v]) => [k, { status: v.status, body: v.json }]),
      ),
    });
  }
  assertSame(
    "location create shape feather vs ptero-v1",
    shapeOf(creates["ptero-v1"].json),
    shapeOf(creates.feather.json),
  );
  assertSame(
    "location create shape feather vs ptero-v2",
    shapeOf(creates["ptero-v2"].json),
    shapeOf(creates.feather.json),
  );
  assertSame(
    "location attribute keys feather vs ptero-v1",
    attributeKeys(creates["ptero-v1"].json),
    attributeKeys(creates.feather.json),
  );
  await probeList(panels, "/api/application/locations?per_page=5");
  return creates;
}

function compareRouteCatalog() {
  const plugin = extractPluginApplicationRoutes().map((r) => routeKey(r.method, r.path));
  const v1 = readJson(path.join(FIXTURES, "ptero-v1-application-routes.json")).map((r) =>
    routeKey(r.method, r.path),
  );
  const v2 = readJson(path.join(FIXTURES, "ptero-v2-application-routes.json")).map((r) =>
    routeKey(r.method, r.path),
  );

  const pluginSet = new Set(plugin);
  const v1Set = new Set(v1);
  const v2Set = new Set(v2);

  // Normalize plugin path param names to {id} already done in routeKey.
  // Also normalize known aliases: {serverId}->{id}, {locationId}->{id}, etc.
  const missingVsV1 = [...v1Set].filter((k) => !pluginSet.has(k)).sort();
  const extraVsV1 = [...pluginSet].filter((k) => !v1Set.has(k)).sort();

  if (missingVsV1.length || extraVsV1.length) {
    throw Object.assign(
      new Error("Application route catalog is not 1:1 with Pterodactyl v1"),
      { details: { missingVsV1, extraVsV1, pluginCount: pluginSet.size, v1Count: v1Set.size } },
    );
  }

  // Shared subset with v2 must exist on the plugin (exclude eggs/tags-only and nests-only).
  const v2Only = [...v2Set].filter((k) => !v1Set.has(k)).sort();
  const v1Only = [...v1Set].filter((k) => !v2Set.has(k)).sort();
  const shared = [...v1Set].filter((k) => v2Set.has(k)).sort();
  const missingShared = shared.filter((k) => !pluginSet.has(k));
  if (missingShared.length) {
    throw Object.assign(new Error("Missing shared v1/v2 Application routes on plugin"), {
      details: { missingShared, v2Only, v1Only },
    });
  }

  return {
    pluginCount: pluginSet.size,
    v1Count: v1Set.size,
    v2Count: v2Set.size,
    sharedCount: shared.length,
    v1Only,
    v2Only,
  };
}

async function main() {
  const credDir = process.env.PARITY_CRED_DIR || path.join(__dirname, "../.credentials");
  const panels = {
    "ptero-v1": loadEnvFile(path.join(credDir, "ptero-v1.env")),
    "ptero-v2": loadEnvFile(path.join(credDir, "ptero-v2.env")),
    feather: loadEnvFile(path.join(credDir, "feather.env")),
  };

  for (const [name, env] of Object.entries(panels)) {
    if (!env.BASE_URL || !env.API_KEY) {
      throw new Error(`Missing credentials for ${name} in ${credDir}`);
    }
  }

  const failures = [];
  const report = { ok: [], fail: [] };

  function run(label, fn) {
    return Promise.resolve()
      .then(fn)
      .then((result) => {
        report.ok.push(label);
        console.log(`PASS  ${label}`);
        return result;
      })
      .catch((err) => {
        report.fail.push({ label, message: err.message, details: err.details || null });
        failures.push(err);
        console.error(`FAIL  ${label}: ${err.message}`);
        if (err.details) console.error(stableStringify(err.details));
      });
  }

  let catalog;
  await run("route-catalog 1:1 vs pterodactyl v1", async () => {
    catalog = compareRouteCatalog();
    return catalog;
  });

  const suffix = Date.now().toString(36);

  await run("GET/POST users parity across v1, v2, feather", () =>
    probeUsersLifecycle(panels, suffix),
  );
  await run("GET/POST locations parity across v1, v2, feather", () =>
    probeLocations(panels, suffix),
  );
  await run("GET nodes list parity", () =>
    probeList(panels, "/api/application/nodes?per_page=5"),
  );
  await run("GET servers list parity", () =>
    probeList(panels, "/api/application/servers?per_page=5"),
  );
  await run("GET nests list parity (v1 + feather)", () =>
    probeList(panels, "/api/application/nests?per_page=5", { requireV2: false }),
  );

  // Unauthenticated / wrong accept must match error envelope style on feather vs v1.
  await run("401 unauthenticated shape feather vs ptero-v1", async () => {
    const bare = { BASE_URL: panels["ptero-v1"].BASE_URL, API_KEY: "ptla_invalid" };
    const featherBare = { BASE_URL: panels.feather.BASE_URL, API_KEY: "ptla_invalid" };
    const a = await request(bare, "GET", "/api/application/users");
    const b = await request(featherBare, "GET", "/api/application/users");
    if (a.status !== 401 || b.status !== 401) {
      throw Object.assign(new Error("expected 401 from both"), {
        details: { ptero: a.status, feather: b.status, bodies: { a: a.json, b: b.json } },
      });
    }
    assertSame("401 error envelope keys", Object.keys(a.json || {}).sort(), Object.keys(b.json || {}).sort());
  });

  const summaryPath = path.join(__dirname, "../.credentials/parity-report.json");
  fs.mkdirSync(path.dirname(summaryPath), { recursive: true });
  fs.writeFileSync(
    summaryPath,
    stableStringify({
      catalog,
      passed: report.ok,
      failed: report.fail,
      panels: Object.fromEntries(
        Object.entries(panels).map(([k, v]) => [k, { BASE_URL: v.BASE_URL }]),
      ),
    }) + "\n",
  );

  console.log("\n=== Parity summary ===");
  console.log(`Passed: ${report.ok.length}`);
  console.log(`Failed: ${report.fail.length}`);
  if (catalog) {
    console.log(
      `Routes: plugin=${catalog.pluginCount} v1=${catalog.v1Count} shared-with-v2=${catalog.sharedCount}`,
    );
    if (catalog.v1Only?.length) console.log(`v1-only (expected nests): ${catalog.v1Only.join(", ")}`);
    if (catalog.v2Only?.length) console.log(`v2-only (eggs/tags): ${catalog.v2Only.join(", ")}`);
  }

  if (failures.length) {
    process.exitCode = 1;
  } else {
    console.log("All panels match 1:1 on probed Application API surface.");
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
