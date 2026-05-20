export type ApiKeyMode = "admin" | "client";

interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  message?: string;
  error_message?: string;
}

export interface ApiKey {
  id: number;
  name: string;
  key: string;
  type?: string;
  last_used?: string | null;
  created_by?: number;
  created_at?: string;
}

export interface ApiKeyPagination {
  current_page: number;
  per_page: number;
  total_records: number;
  total_pages: number;
  has_next: boolean;
  has_prev: boolean;
  from: number;
  to: number;
}

export interface ApiKeysListResponse {
  keys: ApiKey[];
  pagination: ApiKeyPagination;
  context?: {
    type: string;
    scope: string;
  };
}

async function parseJson<T>(response: Response): Promise<ApiResponse<T>> {
  return response.json() as Promise<ApiResponse<T>>;
}

export function generateApiKey(): string {
  const alphabet =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  let body = "";
  for (let i = 0; i < 43; i++) {
    body += alphabet[Math.floor(Math.random() * alphabet.length)];
  }
  return `ptla_${body}`;
}

export function maskApiKey(value: string): string {
  if (!value) return "";
  if (value.length <= 8) {
    return "*".repeat(Math.max(0, value.length - 2)) + value.slice(-2);
  }
  return `${value.slice(0, 4)}••••${value.slice(-4)}`;
}

export function useApiKeysAPI(mode: ApiKeyMode = "admin") {
  const basePath =
    mode === "client"
      ? "/api/pterodactylpanelapi/client/api-keys"
      : "/api/pterodactylpanelapi/api-keys";

  async function listApiKeys(params: {
    page?: number;
    limit?: number;
    search?: string;
  }): Promise<ApiKeysListResponse> {
    const query = new URLSearchParams({
      page: String(params.page ?? 1),
      limit: String(params.limit ?? 10),
    });
    if (params.search) query.set("search", params.search);

    const response = await fetch(`${basePath}?${query}`, {
      credentials: "include",
    });
    const data = await parseJson<ApiKeysListResponse>(response);

    if (data.success && data.data) return data.data;
    throw new Error(data.message ?? data.error_message ?? "Failed to load API keys");
  }

  async function createApiKey(payload: { name: string; key: string }): Promise<ApiKey> {
    const response = await fetch(basePath, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(payload),
    });
    const data = await parseJson<{ key: ApiKey }>(response);
    if (data.success && data.data?.key) return data.data.key;
    throw new Error(data.message ?? data.error_message ?? "Failed to create API key");
  }

  async function updateApiKey(
    id: number,
    payload: { name: string; key: string }
  ): Promise<ApiKey> {
    const response = await fetch(`${basePath}/${id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify(payload),
    });
    const data = await parseJson<{ key: ApiKey }>(response);
    if (data.success && data.data?.key) return data.data.key;
    throw new Error(data.message ?? data.error_message ?? "Failed to update API key");
  }

  async function deleteApiKey(id: number): Promise<void> {
    const response = await fetch(`${basePath}/${id}`, {
      method: "DELETE",
      credentials: "include",
    });
    const data = await parseJson(response);
    if (!data.success) {
      throw new Error(data.message ?? data.error_message ?? "Failed to delete API key");
    }
  }

  return {
    listApiKeys,
    createApiKey,
    updateApiKey,
    deleteApiKey,
  };
}
