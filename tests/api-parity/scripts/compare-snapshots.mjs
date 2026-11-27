#!/usr/bin/env node
/**
 * Compare panel snapshots + Application route catalog.
 * Usage: node compare-snapshots.mjs <ptero-v1.json> <ptero-v2.json> <feather.json>
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const FIXTURES = path.resolve(__dirname, "../fixtures");

const [, , v1File, v2File, featherFile] = process.argv;
if (!v1File || !v2File || !featherFile) {
  console.error("Usage: compare-snapshots.mjs <ptero-v1.json> <ptero-v2.json> <feather.json>");
  process.exit(2);
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, "utf8"));
}

function normalizePathParams(p) {
  return p.replace(/\{[^}]+\}/g, "{id}").replace(/\/+/g, "/").replace(/\/$/, "") || "/";
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
      for (const method of methods) routes.push({ method, path: routePath });
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

function stable(v) {
  return JSON.stringify(v);
}

function compareField(failures, label, a, b) {
  if (stable(a) !== stable(b)) {
    failures.push({ label, expected: a, actual: b });
    console.error(`FAIL  ${label}`);
    console.error(`  expected: ${stable(a)}`);
    console.error(`  actual:   ${stable(b)}`);
  } else {
    console.log(`PASS  ${label}`);
  }
}

function compareRouteCatalog(failures) {
  const plugin = new Set(extractPluginApplicationRoutes().map((r) => routeKey(r.method, r.path)));
  const v1 = new Set(
    readJson(path.join(FIXTURES, "ptero-v1-application-routes.json")).map((r) =>
      routeKey(r.method, r.path),
    ),
  );
  const v2 = new Set(
    readJson(path.join(FIXTURES, "ptero-v2-application-routes.json")).map((r) =>
      routeKey(r.method, r.path),
    ),
  );

  const missingVsV1 = [...v1].filter((k) => !plugin.has(k)).sort();
  const extraVsV1 = [...plugin].filter((k) => !v1.has(k)).sort();
  if (missingVsV1.length || extraVsV1.length) {
    failures.push({
      label: "route-catalog 1:1 vs pterodactyl v1",
      expected: { missingVsV1: [], extraVsV1: [] },
      actual: { missingVsV1, extraVsV1, pluginCount: plugin.size, v1Count: v1.size },
    });
    console.error("FAIL  route-catalog 1:1 vs pterodactyl v1");
    console.error(JSON.stringify({ missingVsV1, extraVsV1 }, null, 2));
  } else {
    console.log(`PASS  route-catalog 1:1 vs pterodactyl v1 (${plugin.size} routes)`);
  }

  const shared = [...v1].filter((k) => v2.has(k));
  const missingShared = shared.filter((k) => !plugin.has(k));
  if (missingShared.length) {
    failures.push({
      label: "shared v1/v2 routes present on plugin",
      expected: [],
      actual: missingShared,
    });
    console.error("FAIL  shared v1/v2 routes present on plugin");
  } else {
    console.log(`PASS  shared v1/v2 routes present on plugin (${shared.length})`);
  }

  return {
    pluginCount: plugin.size,
    v1Count: v1.size,
    v2Count: v2.size,
    sharedCount: shared.length,
    v1Only: [...v1].filter((k) => !v2.has(k)).sort(),
    v2Only: [...v2].filter((k) => !v1.has(k)).sort(),
  };
}

function compareProbes(failures, leftName, left, rightName, right, probeNames) {
  for (const name of probeNames) {
    const a = left.probes[name];
    const b = right.probes[name];
    if (!a || !b) {
      failures.push({
        label: `${name} missing on ${!a ? leftName : rightName}`,
        expected: "present",
        actual: "missing",
      });
      console.error(`FAIL  ${name} missing on ${!a ? leftName : rightName}`);
      continue;
    }
    compareField(failures, `${name}.status ${rightName} vs ${leftName}`, a.status, b.status);
    compareField(failures, `${name}.shape ${rightName} vs ${leftName}`, a.shape, b.shape);
    compareField(
      failures,
      `${name}.attributeKeys ${rightName} vs ${leftName}`,
      a.attributeKeys,
      b.attributeKeys,
    );
    compareField(
      failures,
      `${name}.paginationKeys ${rightName} vs ${leftName}`,
      a.paginationKeys,
      b.paginationKeys,
    );
  }
}

const v1 = readJson(v1File);
const v2 = readJson(v2File);
const feather = readJson(featherFile);
const failures = [];

const catalog = compareRouteCatalog(failures);

const sharedProbes = [
  "users_list",
  "users_create",
  "locations_list",
  "locations_create",
  "nodes_list",
  "servers_list",
  "unauth_users",
];
const v1OnlyProbes = ["nests_list"];

console.log("\n--- Feather vs Pterodactyl v1 ---");
compareProbes(failures, "ptero-v1", v1, "feather", feather, [...sharedProbes, ...v1OnlyProbes]);

console.log("\n--- Feather vs Pterodactyl v2 (shared surface) ---");
compareProbes(failures, "ptero-v2", v2, "feather", feather, sharedProbes);

const report = {
  catalog,
  failed: failures,
  panels: {
    "ptero-v1": v1.baseUrl,
    "ptero-v2": v2.baseUrl,
    feather: feather.baseUrl,
  },
};
const reportPath = path.join(path.dirname(featherFile), "parity-report.json");
fs.writeFileSync(reportPath, JSON.stringify(report, null, 2) + "\n");

console.log("\n=== Parity summary ===");
console.log(`Failures: ${failures.length}`);
if (catalog?.v1Only?.length) console.log(`v1-only: ${catalog.v1Only.join(", ")}`);
if (catalog?.v2Only?.length) console.log(`v2-only: ${catalog.v2Only.join(", ")}`);

if (failures.length) process.exit(1);
console.log("All panels match 1:1 on probed Application API surface.");
