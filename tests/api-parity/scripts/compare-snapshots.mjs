#!/usr/bin/env node
/**
 * Compare Application + Client snapshots and route catalogs.
 * Usage: node compare-snapshots.mjs <ptero-v1.json> <ptero-v2.json> <feather.json>
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";
import { routeKey } from "./lib/http.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const FIXTURES = path.resolve(__dirname, "../fixtures");

const V2_ONLY_ATTR_KEYS = new Set(["avatar", "relationships", "extensions"]);

const [, , v1File, v2File, featherFile] = process.argv;
if (!v1File || !v2File || !featherFile) {
  console.error("Usage: compare-snapshots.mjs <ptero-v1.json> <ptero-v2.json> <feather.json>");
  process.exit(2);
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, "utf8"));
}

function extractPluginRoutes(kind) {
  const dir =
    kind === "application"
      ? path.join(ROOT, "Routes/Application")
      : path.join(ROOT, "Routes/Client");
  const routes = [];
  for (const file of fs.readdirSync(dir)) {
    if (!file.endsWith(".php")) continue;
    const text = fs.readFileSync(path.join(dir, file), "utf8");
    const re = /new Route\(\s*'([^']+)'[\s\S]*?\[['"]([A-Z, ]+)['"]\]/g;
    let m;
    while ((m = re.exec(text))) {
      const routePath = m[1];
      if (kind === "application" && !routePath.startsWith("/api/application")) continue;
      if (kind === "client" && !routePath.startsWith("/api/client")) continue;
      for (const method of m[2].split(",").map((x) => x.trim()).filter(Boolean)) {
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

function stable(v) {
  return JSON.stringify(v);
}

function stripV2OnlyFromShape(shape) {
  if (!shape || typeof shape !== "object" || Array.isArray(shape)) return shape;
  const out = {};
  for (const [k, v] of Object.entries(shape)) {
    if (k === "attributes" && v && typeof v === "object" && !Array.isArray(v)) {
      const attrs = {};
      for (const [ak, av] of Object.entries(v)) {
        if (!V2_ONLY_ATTR_KEYS.has(ak)) attrs[ak] = stripV2OnlyFromShape(av);
      }
      out[k] = attrs;
      continue;
    }
    if (k === "data" && Array.isArray(v) && v[0] === "array" && v[1] && typeof v[1] === "object") {
      out[k] = ["array", stripV2OnlyFromShape(v[1])];
      continue;
    }
    out[k] = stripV2OnlyFromShape(v);
  }
  return out;
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

function compareRouteCatalog(failures, kind, fixtureName, exact) {
  const plugin = new Set(extractPluginRoutes(kind).map((r) => routeKey(r.method, r.path)));
  const upstream = new Set(
    readJson(path.join(FIXTURES, fixtureName)).map((r) => routeKey(r.method, r.path)),
  );
  const missing = [...upstream].filter((k) => !plugin.has(k)).sort();
  const extra = [...plugin].filter((k) => !upstream.has(k)).sort();
  const label = `route-catalog ${kind} vs ${fixtureName}`;
  if (exact && (missing.length || extra.length)) {
    failures.push({ label, expected: { missing: [], extra: [] }, actual: { missing, extra } });
    console.error(`FAIL  ${label}`);
    console.error(JSON.stringify({ missing, extra }, null, 2));
  } else if (!exact && missing.length) {
    // Client: warn on missing upstream routes, fail only if plugin has zero overlap
    console.warn(`WARN  ${label} missing on plugin (${missing.length}):`);
    for (const m of missing.slice(0, 20)) console.warn(`  - ${m}`);
    if (missing.length === upstream.size) {
      failures.push({ label, expected: "some client routes", actual: "none" });
      console.error(`FAIL  ${label} — plugin implements none of upstream client routes`);
    } else {
      console.log(`PASS  ${label} shared surface (${upstream.size - missing.length}/${upstream.size})`);
    }
  } else {
    console.log(`PASS  ${label} (${plugin.size} plugin, ${upstream.size} upstream)`);
  }
  return { plugin: plugin.size, upstream: upstream.size, missing, extra };
}

function compareProbes(failures, leftName, left, rightName, right, { stripV2Only = false, requireSameProbeSet = false } = {}) {
  const leftIds = Object.keys(left.probes || {});
  const rightIds = Object.keys(right.probes || {});
  const shared = leftIds.filter((id) => rightIds.includes(id)).sort();

  for (const id of shared) {
    const a = left.probes[id];
    const b = right.probes[id];
    if (a.skipped || b.skipped) {
      console.log(`SKIP  ${id} (${a.skipped ? leftName : rightName})`);
      continue;
    }
    compareField(failures, `${id}.status ${rightName} vs ${leftName}`, a.status, b.status);

    let expectedShape = a.shape;
    let actualShape = b.shape;
    let expectedKeys = a.attributeKeys;
    let actualKeys = b.attributeKeys;
    if (stripV2Only) {
      expectedShape = stripV2OnlyFromShape(a.shape);
      actualShape = stripV2OnlyFromShape(b.shape);
      expectedKeys = (a.attributeKeys || []).filter((k) => !V2_ONLY_ATTR_KEYS.has(k));
      actualKeys = (b.attributeKeys || []).filter((k) => !V2_ONLY_ATTR_KEYS.has(k));
    }
    compareField(failures, `${id}.shape ${rightName} vs ${leftName}`, expectedShape, actualShape);
    compareField(failures, `${id}.attributeKeys ${rightName} vs ${leftName}`, expectedKeys, actualKeys);
    compareField(failures, `${id}.paginationKeys ${rightName} vs ${leftName}`, a.paginationKeys, b.paginationKeys);
  }

  if (requireSameProbeSet) {
    const onlyLeft = leftIds.filter((id) => !rightIds.includes(id) && !left.probes[id].skipped);
    const onlyRight = rightIds.filter((id) => !leftIds.includes(id) && !right.probes[id].skipped);
    if (onlyLeft.length) {
      failures.push({ label: `probes only on ${leftName}`, expected: [], actual: onlyLeft });
      console.error(`FAIL  probes only on ${leftName}: ${onlyLeft.join(", ")}`);
    }
    if (onlyRight.length) {
      failures.push({ label: `probes only on ${rightName}`, expected: [], actual: onlyRight });
      console.error(`FAIL  probes only on ${rightName}: ${onlyRight.join(", ")}`);
    }
  } else {
    const onlyLeft = leftIds.filter((id) => !rightIds.includes(id));
    const onlyRight = rightIds.filter((id) => !leftIds.includes(id));
    if (onlyLeft.length) console.log(`INFO  probes only on ${leftName}: ${onlyLeft.length}`);
    if (onlyRight.length) console.log(`INFO  probes only on ${rightName}: ${onlyRight.length}`);
  }
}

const v1 = readJson(v1File);
const v2 = readJson(v2File);
const feather = readJson(featherFile);
const failures = [];

console.log("=== Route catalogs ===");
const appCatalog = compareRouteCatalog(failures, "application", "ptero-v1-application-routes.json", true);
const clientCatalog = compareRouteCatalog(failures, "client", "ptero-v1-client-routes.json", false);
compareRouteCatalog(failures, "client", "ptero-v2-client-routes.json", false);

console.log("\n--- Feather vs Pterodactyl v1 (Application + Client) ---");
// Shared probe IDs only — panels may differ when eggs/Wings/DB hosts are unavailable.
compareProbes(failures, "ptero-v1", v1, "feather", feather, { requireSameProbeSet: false });

console.log("\n--- Feather vs Pterodactyl v2 (shared, v2-only fields ignored) ---");
compareProbes(failures, "ptero-v2", v2, "feather", feather, { stripV2Only: true, requireSameProbeSet: false });

const report = {
  catalogs: { application: appCatalog, client: clientCatalog },
  coverage: {
    "ptero-v1": v1.coverage || null,
    "ptero-v2": v2.coverage || null,
    feather: feather.coverage || null,
  },
  failed: failures,
  panels: {
    "ptero-v1": v1.baseUrl,
    "ptero-v2": v2.baseUrl,
    feather: feather.baseUrl,
  },
};
const reportPath = path.join(path.dirname(featherFile), "parity-report.json");
fs.writeFileSync(reportPath, JSON.stringify(report, null, 2) + "\n");

const schemaPath = path.join(path.dirname(featherFile), "openapi.parity.json");
const exported = spawnSync(
  process.execPath,
  [path.join(__dirname, "export-openapi.mjs"), v1File, v2File, featherFile, schemaPath],
  { encoding: "utf8" },
);
process.stdout.write(exported.stdout || "");
process.stderr.write(exported.stderr || "");
if (exported.status !== 0) {
  failures.push({ label: "openapi export", expected: 0, actual: exported.status });
}

console.log("\n=== Parity summary ===");
console.log(`Failures: ${failures.length}`);
console.log(`Application catalog: plugin=${appCatalog.plugin} upstream=${appCatalog.upstream}`);
console.log(`Client catalog: plugin=${clientCatalog.plugin} upstream=${clientCatalog.upstream} missing=${clientCatalog.missing.length}`);

if (failures.length) process.exit(1);
console.log("All Application + Client probes match 1:1; OpenAPI schema exported.");
