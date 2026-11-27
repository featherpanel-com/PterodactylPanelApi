#!/usr/bin/env node
/**
 * Exhaustive Application (admin) + Client API probe suite.
 * Creates a resource graph, hits every plugin route with real params, records
 * request/response for OpenAPI export + 1:1 compare.
 *
 * Usage: node capture-panel.mjs <panel-id> <cred-env-file> <out-json>
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import {
  APP_ACCEPT,
  CLIENT_ACCEPT,
  attributeKeys,
  paginationKeys,
  request,
  routeKey,
  shapeOf,
} from "./lib/http.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "../../..");
const SEED = `parity${Date.now().toString(36).slice(-4)}`;

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

function record(probes, id, api, res, extra = {}) {
  probes[id] = {
    id,
    api,
    status: res.status,
    shape: shapeOf(res.json),
    attributeKeys: attributeKeys(res.json),
    paginationKeys: paginationKeys(res.json),
    topKeys: Object.keys(res.json || {}).sort(),
    request: res.request,
    sample: summarizeSample(res.json),
    ...extra,
  };
}

function summarizeSample(json) {
  if (!json || typeof json !== "object") return json;
  const clone = JSON.parse(JSON.stringify(json));
  // Keep structure but trim huge lists
  if (Array.isArray(clone.data) && clone.data.length > 2) {
    clone.data = clone.data.slice(0, 2);
    clone._truncated = true;
  }
  return clone;
}

function pickId(payload) {
  return payload?.attributes?.id ?? payload?.data?.attributes?.id ?? null;
}

function pickUuid(payload) {
  return payload?.attributes?.uuid ?? payload?.attributes?.identifier ?? null;
}

async function appReq(env, method, p, body, query) {
  return request(env.BASE_URL, env.API_KEY, method, p, {
    body,
    query,
    accept: APP_ACCEPT,
  });
}

async function clientReq(env, method, p, body, query) {
  return request(env.BASE_URL, env.CLIENT_API_KEY, method, p, {
    body,
    query,
    accept: CLIENT_ACCEPT,
  });
}

async function runApplicationSuite(env, probes, ctx) {
  const major = String(env.MAJOR || panelId);
  const supportsNests = major === "1" || major === "feather" || panelId === "feather" || panelId === "ptero-v1";

  // --- Lists with pagination + filter params ---
  record(probes, "app.users.list", "application", await appReq(env, "GET", "/api/application/users", undefined, { per_page: 5, page: 1 }));
  record(probes, "app.users.list.filter", "application", await appReq(env, "GET", "/api/application/users", undefined, { "filter[email]": "admin@parity.local", per_page: 5 }));
  record(probes, "app.users.list.include", "application", await appReq(env, "GET", "/api/application/users", undefined, { include: "servers", per_page: 5 }));
  record(probes, "app.locations.list", "application", await appReq(env, "GET", "/api/application/locations", undefined, { per_page: 5, page: 1 }));
  record(probes, "app.nodes.list", "application", await appReq(env, "GET", "/api/application/nodes", undefined, { per_page: 5 }));
  record(probes, "app.nodes.deployable", "application", await appReq(env, "GET", "/api/application/nodes/deployable", undefined, { memory: 128, disk: 128 }));
  record(probes, "app.servers.list", "application", await appReq(env, "GET", "/api/application/servers", undefined, { per_page: 5 }));
  if (supportsNests) {
    record(probes, "app.nests.list", "application", await appReq(env, "GET", "/api/application/nests", undefined, { per_page: 25 }));
  }

  // --- Unauth / wrong key ---
  record(
    probes,
    "app.users.unauth",
    "application",
    await request(env.BASE_URL, "ptla_invalid", "GET", "/api/application/users", { accept: APP_ACCEPT }),
  );
  record(
    probes,
    "app.users.wrong_accept",
    "application",
    await request(env.BASE_URL, env.API_KEY, "GET", "/api/application/users", { accept: "application/json" }),
  );

  // --- Users CRUD ---
  const userCreate = await appReq(env, "POST", "/api/application/users", {
    email: `${SEED}_user@example.com`,
    username: `${SEED}user`.slice(0, 20),
    first_name: "Parity",
    last_name: "User",
    password: "ParityUser!12345",
    external_id: `${SEED}-ext-user`,
  });
  record(probes, "app.users.create", "application", userCreate);
  ctx.userId = pickId(userCreate.json);
  if (ctx.userId) {
    record(probes, "app.users.show", "application", await appReq(env, "GET", `/api/application/users/${ctx.userId}`));
    record(probes, "app.users.show.include", "application", await appReq(env, "GET", `/api/application/users/${ctx.userId}`, undefined, { include: "servers" }));
    record(probes, "app.users.external", "application", await appReq(env, "GET", `/api/application/users/external/${SEED}-ext-user`));
    const userPatch = await appReq(env, "PATCH", `/api/application/users/${ctx.userId}`, {
      email: `${SEED}_user2@example.com`,
      username: `${SEED}user2`.slice(0, 20),
      first_name: "Parity",
      last_name: "Updated",
    });
    record(probes, "app.users.update", "application", userPatch);
  }

  // --- Locations CRUD ---
  const locCreate = await appReq(env, "POST", "/api/application/locations", {
    short: `${SEED}`.slice(0, 8),
    long: `Parity Location ${SEED}`,
  });
  record(probes, "app.locations.create", "application", locCreate);
  ctx.locationId = pickId(locCreate.json);
  if (ctx.locationId) {
    record(probes, "app.locations.show", "application", await appReq(env, "GET", `/api/application/locations/${ctx.locationId}`));
    record(probes, "app.locations.show.include", "application", await appReq(env, "GET", `/api/application/locations/${ctx.locationId}`, undefined, { include: "nodes,servers" }));
    record(
      probes,
      "app.locations.update",
      "application",
      await appReq(env, "PATCH", `/api/application/locations/${ctx.locationId}`, {
        short: `${SEED}`.slice(0, 8),
        long: `Parity Location Updated ${SEED}`,
      }),
    );
  }

  // --- Nodes CRUD + allocations ---
  if (ctx.locationId) {
    const nodeCreate = await appReq(env, "POST", "/api/application/nodes", {
      name: `parity-node-${SEED}`,
      location_id: ctx.locationId,
      fqdn: "127.0.0.1",
      scheme: "http",
      memory: 1024,
      memory_overallocate: 0,
      disk: 10240,
      disk_overallocate: 0,
      upload_size: 100,
      daemon_listen: 8080,
      daemon_sftp: 2022,
      daemon_base: "/var/lib/pterodactyl/volumes",
    });
    record(probes, "app.nodes.create", "application", nodeCreate);
    ctx.nodeId = pickId(nodeCreate.json);
  }

  if (ctx.nodeId) {
    record(probes, "app.nodes.show", "application", await appReq(env, "GET", `/api/application/nodes/${ctx.nodeId}`));
    record(probes, "app.nodes.show.include", "application", await appReq(env, "GET", `/api/application/nodes/${ctx.nodeId}`, undefined, { include: "allocations,location" }));
    record(probes, "app.nodes.configuration", "application", await appReq(env, "GET", `/api/application/nodes/${ctx.nodeId}/configuration`));
    // Partial PATCH: Feather accepts name/description; upstream Ptero requires the full
    // node schema and may also fail syncing Wings config without a daemon. Compared as
    // status-flexible in compare-snapshots.mjs.
    record(
      probes,
      "app.nodes.update",
      "application",
      await appReq(env, "PATCH", `/api/application/nodes/${ctx.nodeId}`, {
        name: `parity-node-${SEED}-u`,
        description: "parity",
      }),
    );
    record(probes, "app.nodes.allocations.list", "application", await appReq(env, "GET", `/api/application/nodes/${ctx.nodeId}/allocations`, undefined, { per_page: 10 }));
    const allocCreate = await appReq(env, "POST", `/api/application/nodes/${ctx.nodeId}/allocations`, {
      ip: "127.0.0.1",
      ports: ["25565", "25566"],
    });
    record(probes, "app.nodes.allocations.create", "application", allocCreate);
    // List again to capture allocation ids
    const allocList = await appReq(env, "GET", `/api/application/nodes/${ctx.nodeId}/allocations`, undefined, { per_page: 50 });
    record(probes, "app.nodes.allocations.list.after", "application", allocList);
    const allocs = allocList.json?.data || [];
    ctx.allocationId = allocs[0]?.attributes?.id || null;
    ctx.allocationId2 = allocs[1]?.attributes?.id || null;
  }

  // --- Nests / eggs ---
  if (supportsNests) {
    const nests = probes["app.nests.list"]?.sample?.data || [];
    ctx.nestId = nests[0]?.attributes?.id || null;
    if (ctx.nestId) {
      record(probes, "app.nests.show", "application", await appReq(env, "GET", `/api/application/nests/${ctx.nestId}`));
      record(probes, "app.nests.show.include", "application", await appReq(env, "GET", `/api/application/nests/${ctx.nestId}`, undefined, { include: "eggs,servers" }));
      const eggs = await appReq(env, "GET", `/api/application/nests/${ctx.nestId}/eggs`, undefined, { per_page: 25 });
      record(probes, "app.nests.eggs.list", "application", eggs);
      ctx.eggId = eggs.json?.data?.[0]?.attributes?.id || null;
      if (ctx.eggId) {
        record(probes, "app.nests.eggs.show", "application", await appReq(env, "GET", `/api/application/nests/${ctx.nestId}/eggs/${ctx.eggId}`));
        record(
          probes,
          "app.nests.eggs.show.include",
          "application",
          await appReq(env, "GET", `/api/application/nests/${ctx.nestId}/eggs/${ctx.eggId}`, undefined, {
            include: "nest,variables",
          }),
        );
      }
    }
  } else {
    // v2 removed /nests in favor of /eggs + /tags.
    const eggs = await appReq(env, "GET", "/api/application/eggs", undefined, { per_page: 25 });
    record(probes, "app.eggs.list", "application", eggs);
    ctx.eggId = eggs.json?.data?.[0]?.attributes?.id || null;
    if (ctx.eggId) {
      record(probes, "app.eggs.show", "application", await appReq(env, "GET", `/api/application/eggs/${ctx.eggId}`));
    }
    const tags = await appReq(env, "GET", "/api/application/tags", undefined, { per_page: 25 });
    record(probes, "app.tags.list", "application", tags);
  }

  // --- Servers ---
  // Happy-path POST /servers hard-requires Wings (daemon create). Without Wings, upstream
  // rolls back and returns 500. Always capture validation 422, then try create opportunistically.
  record(
    probes,
    "app.servers.create.validation",
    "application",
    await appReq(env, "POST", "/api/application/servers", { name: "x" }),
  );

  if (ctx.userId && ctx.allocationId) {
    let eggId = ctx.eggId;
    let nestId = ctx.nestId;
    if (eggId) {
      const serverBody = {
        name: `parity-srv-${SEED}`,
        user: ctx.userId,
        egg: eggId,
        docker_image: "ghcr.io/pterodactyl/yolks:java_17",
        startup: "java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}",
        environment: {
          SERVER_JARFILE: "server.jar",
          BUILD_NUMBER: "latest",
        },
        limits: { memory: 512, swap: 0, disk: 1024, io: 500, cpu: 100 },
        feature_limits: { databases: 1, allocations: 1, backups: 1 },
        allocation: { default: ctx.allocationId },
      };
      if (nestId) serverBody.nest = nestId;
      const serverCreate = await appReq(env, "POST", "/api/application/servers", serverBody);
      record(probes, "app.servers.create", "application", serverCreate, {
        wingsRequired: true,
        note: "201 only when Wings is reachable; 500/rollback is expected in CI without daemon",
      });
      if ([200, 201].includes(serverCreate.status)) {
        ctx.serverId = pickId(serverCreate.json);
        ctx.serverExternal = `${SEED}-srv`;
        ctx.serverUuid = pickUuid(serverCreate.json);
        ctx.serverIdentifier =
          serverCreate.json?.attributes?.identifier ||
          (ctx.serverUuid ? String(ctx.serverUuid).slice(0, 8) : null);
      }

      if (ctx.serverId) {
        // Set external id via details patch
        record(
          probes,
          "app.servers.update.details",
          "application",
          await appReq(env, "PATCH", `/api/application/servers/${ctx.serverId}/details`, {
            name: `parity-srv-${SEED}`,
            user: ctx.userId,
            external_id: ctx.serverExternal,
            description: "parity",
          }),
        );
        record(probes, "app.servers.show", "application", await appReq(env, "GET", `/api/application/servers/${ctx.serverId}`));
        record(probes, "app.servers.show.include", "application", await appReq(env, "GET", `/api/application/servers/${ctx.serverId}`, undefined, { include: "allocations,user,subusers,nest,egg,variables" }));
        record(probes, "app.servers.external", "application", await appReq(env, "GET", `/api/application/servers/external/${ctx.serverExternal}`));
        record(
          probes,
          "app.servers.update.build",
          "application",
          await appReq(env, "PATCH", `/api/application/servers/${ctx.serverId}/build`, {
            allocation: ctx.allocationId,
            memory: 512,
            swap: 0,
            disk: 1024,
            io: 500,
            cpu: 100,
            feature_limits: { databases: 1, allocations: 1, backups: 1 },
          }),
        );
        record(
          probes,
          "app.servers.update.startup",
          "application",
          await appReq(env, "PATCH", `/api/application/servers/${ctx.serverId}/startup`, {
            startup: "java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}",
            environment: { SERVER_JARFILE: "server.jar", BUILD_NUMBER: "latest" },
            egg: eggId,
            image: "ghcr.io/pterodactyl/yolks:java_17",
            skip_scripts: true,
          }),
        );
        record(probes, "app.servers.suspend", "application", await appReq(env, "POST", `/api/application/servers/${ctx.serverId}/suspend`));
        record(probes, "app.servers.unsuspend", "application", await appReq(env, "POST", `/api/application/servers/${ctx.serverId}/unsuspend`));
        record(probes, "app.servers.reinstall", "application", await appReq(env, "POST", `/api/application/servers/${ctx.serverId}/reinstall`));

        // Databases need a DB host (not creatable via Application API).
        record(probes, "app.servers.databases.list", "application", await appReq(env, "GET", `/api/application/servers/${ctx.serverId}/databases`));
        record(
          probes,
          "app.servers.databases.create.validation",
          "application",
          await appReq(env, "POST", `/api/application/servers/${ctx.serverId}/databases`, {
            database: `parity_${SEED}`.slice(0, 48),
            remote: "%",
          }),
        );
        const dbCreate = await appReq(env, "POST", `/api/application/servers/${ctx.serverId}/databases`, {
          database: `parity_${SEED}`.slice(0, 48),
          remote: "%",
          host: 1,
        });
        record(probes, "app.servers.databases.create", "application", dbCreate);
        ctx.databaseId = pickId(dbCreate.json);
        if (ctx.databaseId) {
          record(probes, "app.servers.databases.show", "application", await appReq(env, "GET", `/api/application/servers/${ctx.serverId}/databases/${ctx.databaseId}`));
          record(
            probes,
            "app.servers.databases.reset_password",
            "application",
            await appReq(env, "POST", `/api/application/servers/${ctx.serverId}/databases/${ctx.databaseId}/reset-password`),
          );
        }
      }
    } else {
      probes["app.servers.create"] = {
        id: "app.servers.create",
        api: "application",
        skipped: true,
        reason: "no egg id available to attempt server create",
        wingsRequired: true,
      };
    }
  } else {
    probes["app.servers.create"] = {
      id: "app.servers.create",
      api: "application",
      skipped: true,
      reason: "missing user/allocation deps",
      wingsRequired: true,
    };
  }
}

async function runClientSuite(env, probes, ctx) {
  if (!env.CLIENT_API_KEY) {
    probes["client.missing_key"] = {
      id: "client.missing_key",
      api: "client",
      skipped: true,
      reason: "CLIENT_API_KEY not in credentials",
    };
    return;
  }

  // Account / permissions / list
  record(probes, "client.index", "client", await clientReq(env, "GET", "/api/client", undefined, { per_page: 5 }));
  record(probes, "client.permissions", "client", await clientReq(env, "GET", "/api/client/permissions"));
  record(probes, "client.account.show", "client", await clientReq(env, "GET", "/api/client/account"));
  record(probes, "client.account.api_keys.list", "client", await clientReq(env, "GET", "/api/client/account/api-keys"));

  const keyCreate = await clientReq(env, "POST", "/api/client/account/api-keys", {
    description: `parity-${SEED}`,
    allowed_ips: [],
  });
  record(probes, "client.account.api_keys.create", "client", keyCreate);
  // Ptero returns identifier in meta.secret_token / attributes.identifier
  const keyIdentifier =
    keyCreate.json?.attributes?.identifier ||
    keyCreate.json?.meta?.identifier ||
    null;
  // Plugin delete uses numeric id — try attributes.id or identifier path
  const keyId = keyCreate.json?.attributes?.id || keyIdentifier;
  if (keyId) {
    record(probes, "client.account.api_keys.delete", "client", await clientReq(env, "DELETE", `/api/client/account/api-keys/${keyId}`));
  }

  // Email/password update — use current password; may 400/422 on validation — capture shape
  record(
    probes,
    "client.account.email",
    "client",
    await clientReq(env, "PUT", "/api/client/account/email", {
      email: "admin@parity.local",
      password: "ParityAdmin!123",
    }),
  );
  record(
    probes,
    "client.account.password",
    "client",
    await clientReq(env, "PUT", "/api/client/account/password", {
      current_password: "ParityAdmin!123",
      password: "ParityAdmin!123",
      password_confirmation: "ParityAdmin!123",
    }),
  );

  record(
    probes,
    "client.unauth",
    "client",
    await request(env.BASE_URL, "ptlc_invalid", "GET", "/api/client/account", { accept: CLIENT_ACCEPT }),
  );

  const serverKey = ctx.serverIdentifier || ctx.serverUuid;
  if (!serverKey) {
    probes["client.server.skipped"] = {
      id: "client.server.skipped",
      api: "client",
      skipped: true,
      reason: "No application server created; server-scoped client routes not exercised",
    };
    return;
  }

  const sid = serverKey;
  const base = `/api/client/servers/${sid}`;

  record(probes, "client.server.show", "client", await clientReq(env, "GET", base));
  record(probes, "client.server.websocket", "client", await clientReq(env, "GET", `${base}/websocket`));
  record(probes, "client.server.resources", "client", await clientReq(env, "GET", `${base}/resources`));
  record(probes, "client.server.activity", "client", await clientReq(env, "GET", `${base}/activity`));
  record(probes, "client.server.command", "client", await clientReq(env, "POST", `${base}/command`, { command: "help" }));
  record(probes, "client.server.power", "client", await clientReq(env, "POST", `${base}/power`, { signal: "start" }));
  record(probes, "client.server.rename", "client", await clientReq(env, "POST", `${base}/settings/rename`, { name: `parity-cli-${SEED}` }));
  record(probes, "client.server.reinstall", "client", await clientReq(env, "POST", `${base}/settings/reinstall`));
  record(
    probes,
    "client.server.docker_image",
    "client",
    await clientReq(env, "PUT", `${base}/settings/docker-image`, { docker_image: "ghcr.io/pterodactyl/yolks:java_17" }),
  );
  record(probes, "client.server.startup", "client", await clientReq(env, "GET", `${base}/startup`));
  record(
    probes,
    "client.server.startup.variable",
    "client",
    await clientReq(env, "PUT", `${base}/startup/variable`, { key: "SERVER_JARFILE", value: "server.jar" }),
  );

  // Files
  record(probes, "client.server.files.list", "client", await clientReq(env, "GET", `${base}/files/list`, undefined, { directory: "/" }));
  record(probes, "client.server.files.contents", "client", await clientReq(env, "GET", `${base}/files/contents`, undefined, { file: "/server.properties" }));
  record(probes, "client.server.files.download", "client", await clientReq(env, "GET", `${base}/files/download`, undefined, { file: "/server.properties" }));
  record(probes, "client.server.files.upload", "client", await clientReq(env, "GET", `${base}/files/upload`));
  record(
    probes,
    "client.server.files.create_folder",
    "client",
    await clientReq(env, "POST", `${base}/files/create-folder`, { root: "/", name: `parity-${SEED}` }),
  );
  record(
    probes,
    "client.server.files.write",
    "client",
    await clientReq(env, "POST", `${base}/files/write`, undefined, { file: `/parity-${SEED}.txt` }),
  );
  // write often expects raw body — also try JSON path used by some panels
  record(
    probes,
    "client.server.files.rename",
    "client",
    await clientReq(env, "PUT", `${base}/files/rename`, {
      root: "/",
      files: [{ from: `parity-${SEED}`, to: `parity-${SEED}-r` }],
    }),
  );
  record(probes, "client.server.files.copy", "client", await clientReq(env, "POST", `${base}/files/copy`, { location: `/parity-${SEED}-r` }));
  record(probes, "client.server.files.compress", "client", await clientReq(env, "POST", `${base}/files/compress`, { root: "/", files: [`parity-${SEED}-r`] }));
  record(probes, "client.server.files.decompress", "client", await clientReq(env, "POST", `${base}/files/decompress`, { root: "/", file: "archive.tar.gz" }));
  record(probes, "client.server.files.chmod", "client", await clientReq(env, "POST", `${base}/files/chmod`, { root: "/", files: [{ file: `parity-${SEED}-r`, mode: "0644" }] }));
  record(probes, "client.server.files.pull", "client", await clientReq(env, "POST", `${base}/files/pull`, { url: "https://example.com/", directory: "/" }));
  record(probes, "client.server.files.delete", "client", await clientReq(env, "POST", `${base}/files/delete`, { root: "/", files: [`parity-${SEED}-r`] }));

  // Network
  const allocs = await clientReq(env, "GET", `${base}/network/allocations`);
  record(probes, "client.server.allocations.list", "client", allocs);
  record(probes, "client.server.allocations.create", "client", await clientReq(env, "POST", `${base}/network/allocations`));
  const allocId = allocs.json?.data?.[0]?.attributes?.id || ctx.allocationId;
  if (allocId) {
    record(probes, "client.server.allocations.notes", "client", await clientReq(env, "POST", `${base}/network/allocations/${allocId}`, { notes: "parity" }));
    record(probes, "client.server.allocations.primary", "client", await clientReq(env, "POST", `${base}/network/allocations/${allocId}/primary`));
  }

  // Subusers
  record(probes, "client.server.users.list", "client", await clientReq(env, "GET", `${base}/users`));
  const subCreate = await clientReq(env, "POST", `${base}/users`, {
    email: `${SEED}_sub@example.com`,
    permissions: ["control.console", "control.start"],
  });
  record(probes, "client.server.users.create", "client", subCreate);
  const subUuid = subCreate.json?.attributes?.uuid || subCreate.json?.attributes?.uid;
  if (subUuid) {
    record(probes, "client.server.users.show", "client", await clientReq(env, "GET", `${base}/users/${subUuid}`));
    record(
      probes,
      "client.server.users.update",
      "client",
      await clientReq(env, "POST", `${base}/users/${subUuid}`, { permissions: ["control.console"] }),
    );
    record(probes, "client.server.users.delete", "client", await clientReq(env, "DELETE", `${base}/users/${subUuid}`));
  }

  // Databases
  record(probes, "client.server.databases.list", "client", await clientReq(env, "GET", `${base}/databases`));
  const cdb = await clientReq(env, "POST", `${base}/databases`, { database: `c${SEED}`.slice(0, 48), remote: "%" });
  record(probes, "client.server.databases.create", "client", cdb);
  const cdbId = cdb.json?.attributes?.id;
  if (cdbId) {
    record(probes, "client.server.databases.rotate", "client", await clientReq(env, "POST", `${base}/databases/${cdbId}/rotate-password`));
    record(probes, "client.server.databases.delete", "client", await clientReq(env, "DELETE", `${base}/databases/${cdbId}`));
  }

  // Schedules
  record(probes, "client.server.schedules.list", "client", await clientReq(env, "GET", `${base}/schedules`));
  const sched = await clientReq(env, "POST", `${base}/schedules`, {
    name: `parity-${SEED}`,
    minute: "0",
    hour: "*",
    day_of_month: "*",
    month: "*",
    day_of_week: "*",
    is_active: true,
  });
  record(probes, "client.server.schedules.create", "client", sched);
  const scheduleId = sched.json?.attributes?.id;
  if (scheduleId) {
    record(probes, "client.server.schedules.show", "client", await clientReq(env, "GET", `${base}/schedules/${scheduleId}`));
    record(
      probes,
      "client.server.schedules.update",
      "client",
      await clientReq(env, "POST", `${base}/schedules/${scheduleId}`, {
        name: `parity-${SEED}-u`,
        minute: "0",
        hour: "*",
        day_of_month: "*",
        month: "*",
        day_of_week: "*",
        is_active: true,
      }),
    );
    const task = await clientReq(env, "POST", `${base}/schedules/${scheduleId}/tasks`, {
      action: "command",
      payload: "say parity",
      time_offset: 0,
      continue_on_failure: false,
    });
    record(probes, "client.server.schedules.tasks.create", "client", task);
    const taskId = task.json?.attributes?.id;
    if (taskId) {
      record(
        probes,
        "client.server.schedules.tasks.update",
        "client",
        await clientReq(env, "POST", `${base}/schedules/${scheduleId}/tasks/${taskId}`, {
          action: "command",
          payload: "say parity2",
          time_offset: 0,
          continue_on_failure: false,
        }),
      );
      record(probes, "client.server.schedules.tasks.delete", "client", await clientReq(env, "DELETE", `${base}/schedules/${scheduleId}/tasks/${taskId}`));
    }
    record(probes, "client.server.schedules.execute", "client", await clientReq(env, "POST", `${base}/schedules/${scheduleId}/execute`));
    record(probes, "client.server.schedules.delete", "client", await clientReq(env, "DELETE", `${base}/schedules/${scheduleId}`));
  }

  // Backups
  record(probes, "client.server.backups.list", "client", await clientReq(env, "GET", `${base}/backups`));
  const backup = await clientReq(env, "POST", `${base}/backups`, { name: `parity-${SEED}` });
  record(probes, "client.server.backups.create", "client", backup);
  const backupUuid = backup.json?.attributes?.uuid || backup.json?.attributes?.id;
  if (backupUuid) {
    record(probes, "client.server.backups.show", "client", await clientReq(env, "GET", `${base}/backups/${backupUuid}`));
    record(probes, "client.server.backups.download", "client", await clientReq(env, "GET", `${base}/backups/${backupUuid}/download`));
    record(probes, "client.server.backups.lock", "client", await clientReq(env, "POST", `${base}/backups/${backupUuid}/lock`));
    record(probes, "client.server.backups.restore", "client", await clientReq(env, "POST", `${base}/backups/${backupUuid}/restore`, { truncate: false }));
    record(probes, "client.server.backups.delete", "client", await clientReq(env, "DELETE", `${base}/backups/${backupUuid}`));
  }
}

async function cleanupApplication(env, ctx, probes) {
  // Delete in reverse dependency order — capture final delete shapes
  if (ctx.databaseId && ctx.serverId) {
    record(
      probes,
      "app.servers.databases.delete",
      "application",
      await appReq(env, "DELETE", `/api/application/servers/${ctx.serverId}/databases/${ctx.databaseId}`),
    );
  }
  if (ctx.serverId) {
    record(probes, "app.servers.delete", "application", await appReq(env, "DELETE", `/api/application/servers/${ctx.serverId}`));
  }
  // Feather refuses node delete while any allocations remain (Ptero cascades).
  for (const allocId of [ctx.allocationId2, ctx.allocationId].filter(Boolean)) {
    if (!ctx.nodeId) break;
    record(
      probes,
      allocId === ctx.allocationId2 ? "app.nodes.allocations.delete" : "app.nodes.allocations.delete.primary",
      "application",
      await appReq(env, "DELETE", `/api/application/nodes/${ctx.nodeId}/allocations/${allocId}`),
    );
  }
  if (ctx.nodeId) {
    record(probes, "app.nodes.delete", "application", await appReq(env, "DELETE", `/api/application/nodes/${ctx.nodeId}`));
  }
  if (ctx.locationId) {
    record(probes, "app.locations.delete", "application", await appReq(env, "DELETE", `/api/application/locations/${ctx.locationId}`));
  }
  if (ctx.userId) {
    record(probes, "app.users.delete", "application", await appReq(env, "DELETE", `/api/application/users/${ctx.userId}`));
  }
}

function coverageReport(probes, pluginAppRoutes, pluginClientRoutes) {
  const hit = new Set();
  for (const p of Object.values(probes)) {
    if (p.skipped || !p.request?.path) continue;
    hit.add(routeKey(p.request.method, p.request.path));
  }
  const missingApp = pluginAppRoutes
    .map((r) => routeKey(r.method, r.path))
    .filter((k) => !hit.has(k))
    .sort();
  const missingClient = pluginClientRoutes
    .map((r) => routeKey(r.method, r.path))
    .filter((k) => !hit.has(k))
    .sort();
  return {
    pluginApplicationRoutes: pluginAppRoutes.length,
    pluginClientRoutes: pluginClientRoutes.length,
    probesTotal: Object.keys(probes).length,
    probesSkipped: Object.values(probes).filter((p) => p.skipped).length,
    probesHit: Object.values(probes).filter((p) => !p.skipped).length,
    missingApplicationRouteHits: missingApp,
    missingClientRouteHits: missingClient,
  };
}

async function main() {
  const env = loadEnvFile(credFile);
  if (!env.BASE_URL || !env.API_KEY) throw new Error(`Bad credentials file: ${credFile}`);

  const pluginAppRoutes = extractPluginRoutes("application");
  const pluginClientRoutes = extractPluginRoutes("client");
  const probes = {};
  const ctx = {};

  console.log(`==> Application suite (${pluginAppRoutes.length} plugin routes)`);
  await runApplicationSuite(env, probes, ctx);
  console.log(`==> Client suite (${pluginClientRoutes.length} plugin routes)`);
  await runClientSuite(env, probes, ctx);
  console.log("==> Application cleanup deletes");
  await cleanupApplication(env, ctx, probes);

  const coverage = coverageReport(probes, pluginAppRoutes, pluginClientRoutes);
  console.log(
    JSON.stringify(
      {
        hit: coverage.probesHit,
        skipped: coverage.probesSkipped,
        missingApp: coverage.missingApplicationRouteHits.length,
        missingClient: coverage.missingClientRouteHits.length,
      },
      null,
      2,
    ),
  );

  const snapshot = {
    panel: panelId,
    major: String(env.MAJOR || panelId),
    baseUrl: env.BASE_URL,
    apis: ["application", "client"],
    coverage,
    context: {
      userId: ctx.userId || null,
      locationId: ctx.locationId || null,
      nodeId: ctx.nodeId || null,
      serverId: ctx.serverId || null,
      serverIdentifier: ctx.serverIdentifier || null,
      nestId: ctx.nestId || null,
      eggId: ctx.eggId || null,
    },
    probes,
  };

  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  fs.writeFileSync(outFile, JSON.stringify(snapshot, null, 2) + "\n");
  console.log(`Wrote snapshot ${outFile}`);

  // Hard requirements: core list endpoints must be 200
  for (const name of ["app.users.list", "app.locations.list", "app.nodes.list", "app.servers.list"]) {
    if (probes[name]?.status !== 200) {
      console.error(`Probe ${name} failed with status ${probes[name]?.status}`);
      process.exit(1);
    }
  }
  if (probes["client.account.show"] && ![200].includes(probes["client.account.show"].status)) {
    console.error(`client.account.show failed with status ${probes["client.account.show"].status}`);
    process.exit(1);
  }
  // Application routes that need Wings / panel-specific resources may remain unhit in CI.
  const major = String(env.MAJOR || panelId);
  const unexpectedAppMiss = coverage.missingApplicationRouteHits.filter((k) => {
    if (k.includes("/servers/{id}")) return false; // Wings-backed nested server routes
    if (k.includes("/servers/external")) return false; // needs a created server
    if (k.includes("/databases")) return false;
    // Pterodactyl v2 removed /nests in favor of /eggs + /tags.
    if (major === "2" && k.includes("/api/application/nests")) return false;
    // Egg show needs at least one egg/spell seeded.
    if (k.includes("/nests/{id}/eggs/{id}") && !ctx.eggId) return false;
    return true;
  });
  if (unexpectedAppMiss.length) {
    console.error("Application routes not exercised:", unexpectedAppMiss);
    process.exit(1);
  }

  // Client account/API-key routes must always be hit; server-scoped ones need Wings.
  const unexpectedClientMiss = coverage.missingClientRouteHits.filter((k) => {
    if (k.includes("/servers/{id}")) return false;
    return true;
  });
  if (unexpectedClientMiss.length) {
    console.error("Client routes not exercised:", unexpectedClientMiss);
    process.exit(1);
  }
  if (coverage.missingClientRouteHits.length && !ctx.serverIdentifier) {
    console.log(
      `INFO  ${coverage.missingClientRouteHits.length} client server routes unhit (no Wings server) — OK`,
    );
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
