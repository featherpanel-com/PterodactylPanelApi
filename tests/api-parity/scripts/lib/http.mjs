export const APP_ACCEPT = "application/vnd.pterodactyl.v1+json";
export const CLIENT_ACCEPT = "application/json";

export function shapeOf(value, depth = 0) {
  if (depth > 10) return "deep";
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

export function attributeKeys(payload) {
  if (!payload || typeof payload !== "object") return [];
  if (payload.object === "list" && Array.isArray(payload.data) && payload.data[0]?.attributes) {
    return Object.keys(payload.data[0].attributes).sort();
  }
  if (payload.attributes && typeof payload.attributes === "object") {
    return Object.keys(payload.attributes).sort();
  }
  return Object.keys(payload).sort();
}

export function paginationKeys(payload) {
  if (!payload?.meta?.pagination) return null;
  return Object.keys(payload.meta.pagination).sort();
}

export async function request(baseUrl, apiKey, method, routePath, { body, accept, query } = {}) {
  const url = new URL(baseUrl.replace(/\/$/, "") + routePath);
  if (query) {
    for (const [k, v] of Object.entries(query)) {
      if (v === undefined || v === null) continue;
      url.searchParams.set(k, String(v));
    }
  }
  const headers = {
    Accept: accept || APP_ACCEPT,
    Authorization: `Bearer ${apiKey}`,
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
    json = { _parse_error: true, _raw: text.slice(0, 500) };
  }
  return {
    status: res.status,
    json,
    request: {
      method,
      path: routePath,
      query: query || null,
      body: body === undefined ? null : body,
      accept: headers.Accept,
    },
  };
}

export function normalizeRoutePath(p) {
  return p
    .replace(/\{[^}]+\}/g, "{id}")
    .replace(/\/+/g, "/")
    .replace(/\/$/, "") || "/";
}

export function routeKey(method, p) {
  return `${method.toUpperCase()} ${normalizeRoutePath(p)}`;
}
