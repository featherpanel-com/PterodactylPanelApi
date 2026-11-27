#!/usr/bin/env node
/**
 * Build an OpenAPI 3 document from live parity snapshots (request + response shapes).
 * Usage: node export-openapi.mjs <ptero-v1.json> <ptero-v2.json> <feather.json> <out-openapi.json>
 */
import fs from "node:fs";
import path from "node:path";
import { normalizeRoutePath } from "./lib/http.mjs";

const [, , v1File, v2File, featherFile, outFile] = process.argv;
if (!v1File || !v2File || !featherFile || !outFile) {
  console.error("Usage: export-openapi.mjs <ptero-v1.json> <ptero-v2.json> <feather.json> <out-openapi.json>");
  process.exit(2);
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, "utf8"));
}

function shapeToSchema(shape) {
  if (shape === "null") return { type: "null" };
  if (shape === "string" || shape === "number" || shape === "boolean") return { type: shape };
  if (shape === "deep") return {};
  if (Array.isArray(shape)) {
    if (shape[0] === "array") {
      if (shape[1] === "empty") return { type: "array", items: {}, maxItems: 0 };
      return { type: "array", items: shapeToSchema(shape[1]) };
    }
  }
  if (shape && typeof shape === "object") {
    const properties = {};
    for (const [k, v] of Object.entries(shape)) properties[k] = shapeToSchema(v);
    return { type: "object", properties };
  }
  return {};
}

function mergeProbeIntoPaths(paths, panelName, probe) {
  if (probe.skipped || !probe.request?.path) return;
  const method = probe.request.method.toLowerCase();
  const rawPath = probe.request.path;
  const openapiPath = rawPath.replace(/\{[^}]+\}/g, (m) => m); // keep names
  const normalized = normalizeRoutePath(rawPath);
  const pathItem = (paths[openapiPath] ||= {});
  const op = (pathItem[method] ||= {
    tags: [probe.api === "client" ? "Client API" : "Application API"],
    summary: probe.id,
    operationId: probe.id.replace(/\./g, "_"),
    parameters: [],
    responses: {},
    "x-parity": { probes: [], panels: {} },
  });

  if (probe.request.query) {
    for (const [name, value] of Object.entries(probe.request.query)) {
      if (!op.parameters.some((p) => p.in === "query" && p.name === name)) {
        op.parameters.push({
          name,
          in: "query",
          schema: { type: typeof value === "number" ? "number" : "string" },
          example: value,
        });
      }
    }
  }

  for (const m of rawPath.matchAll(/\{([^}]+)\}/g)) {
    const name = m[1];
    if (!op.parameters.some((p) => p.in === "path" && p.name === name)) {
      op.parameters.push({ name, in: "path", required: true, schema: { type: "string" } });
    }
  }

  if (probe.request.body && ["post", "put", "patch"].includes(method)) {
    op.requestBody = {
      required: true,
      content: {
        "application/json": {
          schema: shapeToSchema(shapeOfSafe(probe.request.body)),
          example: probe.request.body,
        },
      },
    };
  }

  const status = String(probe.status);
  const response = (op.responses[status] ||= {
    description: `${probe.api} ${status}`,
    content: {
      "application/json": {
        schema: shapeToSchema(probe.shape),
        examples: {},
      },
    },
  });
  response.content["application/json"].examples[panelName] = {
    summary: panelName,
    value: probe.sample,
  };

  op["x-parity"].probes.push(probe.id);
  op["x-parity"].panels[panelName] = {
    status: probe.status,
    attributeKeys: probe.attributeKeys,
    shape: probe.shape,
  };
  op["x-parity"].normalizedPath = normalized;
}

function shapeOfSafe(value) {
  if (value === null) return "null";
  if (Array.isArray(value)) {
    if (!value.length) return ["array", "empty"];
    return ["array", shapeOfSafe(value[0])];
  }
  const t = typeof value;
  if (t !== "object") return t;
  const out = {};
  for (const k of Object.keys(value).sort()) out[k] = shapeOfSafe(value[k]);
  return out;
}

const v1 = readJson(v1File);
const v2 = readJson(v2File);
const feather = readJson(featherFile);
const paths = {};

for (const [name, snap] of [
  ["ptero-v1", v1],
  ["ptero-v2", v2],
  ["feather", feather],
]) {
  for (const probe of Object.values(snap.probes || {})) {
    mergeProbeIntoPaths(paths, name, probe);
  }
}

const doc = {
  openapi: "3.0.3",
  info: {
    title: "PterodactylPanelApi parity schema",
    description:
      "Live-captured Application (admin) + Client API request/response contracts from Pterodactyl v1, v2, and FeatherPanel+plugin.",
    version: "1.0.0",
  },
  servers: [
    { url: v1.baseUrl, description: "Pterodactyl v1" },
    { url: v2.baseUrl, description: "Pterodactyl v2" },
    { url: feather.baseUrl, description: "FeatherPanel + plugin" },
  ],
  tags: [
    { name: "Application API", description: "Admin Application API (ptla_ keys, Accept: application/vnd.pterodactyl.v1+json)" },
    { name: "Client API", description: "Client API (ptlc_ keys)" },
  ],
  paths,
  "x-parity-coverage": {
    "ptero-v1": v1.coverage || null,
    "ptero-v2": v2.coverage || null,
    feather: feather.coverage || null,
  },
};

fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, JSON.stringify(doc, null, 2) + "\n");
console.log(`Wrote OpenAPI schema ${outFile} with ${Object.keys(paths).length} paths`);
