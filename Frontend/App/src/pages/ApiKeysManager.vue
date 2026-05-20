<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  ArrowLeft,
  ChevronLeft,
  ChevronRight,
  Copy,
  Info,
  KeyRound,
  Loader2,
  Pencil,
  Plus,
  RefreshCw,
  Save,
  Search,
  Trash2,
} from "lucide-vue-next";
import { useToast } from "vue-toastification";
import {
  useApiKeysAPI,
  generateApiKey,
  maskApiKey,
  type ApiKey,
  type ApiKeyMode,
  type ApiKeyPagination,
} from "@/composables/useApiKeysAPI";

const props = withDefaults(
  defineProps<{
    mode?: ApiKeyMode;
  }>(),
  { mode: "admin" }
);

type PageView = "list" | "create" | "edit" | "saving";

const toast = useToast();
const api = useApiKeysAPI(props.mode);

const isAdmin = computed(() => props.mode === "admin");

const pageView = ref<PageView>("list");

const keys = ref<ApiKey[]>([]);
const pagination = ref<ApiKeyPagination | null>(null);
const contextScope = ref(isAdmin.value ? "global" : "self");
const loading = ref(false);
const searchQuery = ref("");
const page = ref(1);
const limit = ref(10);

const formName = ref("");
const formKey = ref("");
const editingKey = ref<ApiKey | null>(null);

const scopeBadge = computed(() => {
  const label = isAdmin.value ? "Admin" : "Client";
  return `${label} scope: ${contextScope.value}`;
});

const pageSubtitle = computed(() =>
  isAdmin.value
    ? "Manage application API keys for the Pterodactyl-compatible Application API"
    : "Manage your personal client API keys for Pterodactyl-compatible access"
);

const createSubtitle = computed(() =>
  isAdmin.value
    ? "Generate a new application key for Pterodactyl-compatible integrations"
    : "Generate a new client key scoped to your account"
);

const paginationLabel = computed(() => {
  const p = pagination.value;
  if (!p || p.total_records === 0) return "Showing 0 of 0";
  return `Showing ${p.from}-${p.to} of ${p.total_records}`;
});

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return "Never";
  return new Date(dateStr).toLocaleString();
}

function resetForm() {
  formName.value = "";
  formKey.value = "";
}

function goBackToList() {
  pageView.value = "list";
  editingKey.value = null;
  resetForm();
}

async function loadKeys() {
  loading.value = true;
  try {
    const result = await api.listApiKeys({
      page: page.value,
      limit: limit.value,
      search: searchQuery.value.trim() || undefined,
    });
    keys.value = result.keys;
    pagination.value = result.pagination;
    contextScope.value =
      result.context?.scope ?? (isAdmin.value ? "global" : "self");
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load API keys");
    keys.value = [];
    pagination.value = null;
  } finally {
    loading.value = false;
  }
}

function handleSearch() {
  page.value = 1;
  loadKeys();
}

function openCreatePage() {
  resetForm();
  formKey.value = generateApiKey();
  editingKey.value = null;
  pageView.value = "create";
}

function openEditPage(key: ApiKey) {
  editingKey.value = key;
  formName.value = key.name;
  formKey.value = key.key;
  pageView.value = "edit";
}

async function handleCreate() {
  const name = formName.value.trim();
  const key = formKey.value.trim();
  if (!name || !key) {
    toast.warning("Name and key are required");
    return;
  }

  pageView.value = "saving";
  try {
    await api.createApiKey({ name, key });
    toast.success("API key created");
    goBackToList();
    await loadKeys();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to create API key");
    pageView.value = "create";
  }
}

async function handleUpdate() {
  if (!editingKey.value) return;
  const name = formName.value.trim();
  const key = formKey.value.trim();
  if (!name || !key) {
    toast.warning("Name and key are required");
    return;
  }

  pageView.value = "saving";
  try {
    await api.updateApiKey(editingKey.value.id, { name, key });
    toast.success("API key updated");
    goBackToList();
    await loadKeys();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to update API key");
    pageView.value = "edit";
  }
}

async function handleDelete(key: ApiKey) {
  if (!confirm(`Delete "${key.name}"? This cannot be undone.`)) return;

  loading.value = true;
  try {
    await api.deleteApiKey(key.id);
    toast.success("API key deleted");
    await loadKeys();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to delete API key");
  } finally {
    loading.value = false;
  }
}

async function copyKey(value: string) {
  try {
    await navigator.clipboard.writeText(value);
    toast.success("Copied to clipboard");
  } catch {
    toast.error("Failed to copy");
  }
}

function goPrev() {
  if (page.value > 1) {
    page.value--;
    loadKeys();
  }
}

function goNext() {
  if (pagination.value?.has_next) {
    page.value++;
    loadKeys();
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(searchQuery, () => {
  if (pageView.value !== "list") return;
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(handleSearch, 300);
});

onMounted(loadKeys);
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-5xl">
      <template v-if="pageView === 'saving'">
        <div class="flex flex-col items-center justify-center py-24 gap-4">
          <Loader2 class="h-10 w-10 animate-spin text-muted-foreground" />
          <div class="text-center space-y-1">
            <p class="text-lg font-medium">Saving API key</p>
            <p class="text-sm text-muted-foreground">Please wait…</p>
          </div>
        </div>
      </template>

      <template v-else-if="pageView === 'create'">
        <div class="mb-6">
          <Button variant="ghost" size="sm" class="mb-4 -ml-2" @click="goBackToList">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to API keys
          </Button>
          <h2 class="text-xl font-semibold">Create API key</h2>
          <p class="text-sm text-muted-foreground mt-1">{{ createSubtitle }}</p>
        </div>

        <div class="space-y-4 max-w-2xl">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Key details</CardTitle>
              <CardDescription>
                Choose a descriptive name and a secure key value
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2">
                <Label for="create-name">Name</Label>
                <Input id="create-name" v-model="formName" placeholder="My integration" />
              </div>
              <div class="space-y-2">
                <Label for="create-key">API key</Label>
                <div class="flex gap-2">
                  <Input
                    id="create-key"
                    v-model="formKey"
                    class="font-mono text-xs"
                    placeholder="ptla_..."
                  />
                  <Button type="button" variant="outline" @click="formKey = generateApiKey()">
                    Generate
                  </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                  Store this key securely — it will not be shown again in full after creation.
                </p>
              </div>
            </CardContent>
          </Card>

          <div class="flex gap-2 pt-2">
            <Button variant="outline" @click="goBackToList">Cancel</Button>
            <Button @click="handleCreate">
              <Plus class="h-4 w-4 mr-2" />
              Create API key
            </Button>
          </div>
        </div>
      </template>

      <template v-else-if="pageView === 'edit' && editingKey">
        <div class="mb-6">
          <Button variant="ghost" size="sm" class="mb-4 -ml-2" @click="goBackToList">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to API keys
          </Button>
          <h2 class="text-xl font-semibold">Edit API key</h2>
          <p class="text-sm text-muted-foreground mt-1">Update {{ editingKey.name }}</p>
        </div>

        <div class="space-y-4 max-w-2xl">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Key details</CardTitle>
              <CardDescription>
                Last used: {{ formatDate(editingKey.last_used) }}
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2">
                <Label for="edit-name">Name</Label>
                <Input id="edit-name" v-model="formName" />
              </div>
              <div class="space-y-2">
                <Label for="edit-key">API key</Label>
                <div class="flex gap-2">
                  <Input id="edit-key" v-model="formKey" class="font-mono text-xs" />
                  <Button type="button" variant="outline" @click="formKey = generateApiKey()">
                    Generate
                  </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                  Regenerating the key will invalidate the previous value for integrations using it.
                </p>
              </div>
            </CardContent>
          </Card>

          <div class="flex gap-2 pt-2">
            <Button variant="outline" @click="goBackToList">Cancel</Button>
            <Button @click="handleUpdate">
              <Save class="h-4 w-4 mr-2" />
              Save changes
            </Button>
          </div>
        </div>
      </template>

      <template v-else>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-2xl font-semibold">Pterodactyl API Keys</h1>
              <Badge variant="outline" class="capitalize">{{ scopeBadge }}</Badge>
            </div>
            <p class="text-sm text-muted-foreground mt-1">{{ pageSubtitle }}</p>
          </div>
          <Button variant="outline" size="sm" :disabled="loading" @click="loadKeys">
            <RefreshCw class="h-4 w-4 mr-2" :class="loading ? 'animate-spin' : ''" />
            Refresh
          </Button>
        </div>

        <Alert v-if="isAdmin" class="mb-6">
          <Info class="h-4 w-4" />
          <AlertTitle>About admin API keys</AlertTitle>
          <AlertDescription>
            <ul class="mt-2 space-y-1 text-sm list-disc list-inside">
              <li>Keys remain valid until manually deleted</li>
              <li>They grant application-level access across the panel</li>
              <li>Keep private keys secure — they authenticate privileged requests</li>
            </ul>
          </AlertDescription>
        </Alert>

        <Alert v-else class="mb-6">
          <Info class="h-4 w-4" />
          <AlertTitle>About client API keys</AlertTitle>
          <AlertDescription>
            <ul class="mt-2 space-y-1 text-sm list-disc list-inside">
              <li>Client keys are persistent until manually deleted</li>
              <li>Access is scoped to your own account</li>
              <li>Keep your private key secure — it authenticates requests on your behalf</li>
            </ul>
          </AlertDescription>
        </Alert>

        <Card class="mb-6">
          <CardHeader class="pb-3">
            <CardTitle class="text-base">API keys</CardTitle>
            <CardDescription>
              {{
                isAdmin
                  ? "Create and manage keys used by external integrations"
                  : "Create and manage your personal API keys"
              }}
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="relative flex-1 max-w-md">
                <Search
                  class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                />
                <Input
                  v-model="searchQuery"
                  class="pl-9"
                  placeholder="Search by name..."
                  @keydown.enter="handleSearch"
                />
              </div>
              <Button @click="openCreatePage">
                <Plus class="h-4 w-4 mr-2" />
                Create API key
              </Button>
            </div>

            <div v-if="loading && keys.length === 0" class="flex justify-center py-16">
              <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <div
              v-else-if="keys.length === 0"
              class="flex flex-col items-center justify-center py-16 text-muted-foreground border border-dashed border-border rounded-lg"
            >
              <KeyRound class="h-10 w-10 mb-3 opacity-50" />
              <p class="text-sm font-medium">No API keys found</p>
              <p class="text-xs mt-1">
                {{
                  searchQuery
                    ? "Try a different search"
                    : "Create your first API key to get started"
                }}
              </p>
            </div>

            <div v-else class="rounded-lg border border-border overflow-hidden">
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-muted/50 border-b border-border">
                    <tr>
                      <th class="text-left px-4 py-3 font-medium text-muted-foreground">Name</th>
                      <th class="text-left px-4 py-3 font-medium text-muted-foreground">Key</th>
                      <th class="text-left px-4 py-3 font-medium text-muted-foreground">
                        Last used
                      </th>
                      <th class="text-right px-4 py-3 font-medium text-muted-foreground">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="item in keys"
                      :key="item.id"
                      class="border-b border-border last:border-0 hover:bg-muted/30"
                    >
                      <td class="px-4 py-3 font-medium">{{ item.name }}</td>
                      <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                          <code class="text-xs bg-muted px-2 py-1 rounded font-mono">
                            {{ maskApiKey(item.key) }}
                          </code>
                          <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7"
                            title="Copy key"
                            @click="copyKey(item.key)"
                          >
                            <Copy class="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-muted-foreground">
                        {{ formatDate(item.last_used) }}
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex justify-end gap-1">
                          <Button variant="outline" size="sm" @click="openEditPage(item)">
                            <Pencil class="h-4 w-4" />
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            :disabled="loading"
                            @click="handleDelete(item)"
                          >
                            <Trash2 class="h-4 w-4 text-destructive" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div
                v-if="pagination && pagination.total_pages > 0"
                class="flex items-center justify-between px-4 py-3 border-t border-border bg-muted/20"
              >
                <p class="text-sm text-muted-foreground">{{ paginationLabel }}</p>
                <div class="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    :disabled="!pagination.has_prev || loading"
                    @click="goPrev"
                  >
                    <ChevronLeft class="h-4 w-4 mr-1" />
                    Previous
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    :disabled="!pagination.has_next || loading"
                    @click="goNext"
                  >
                    Next
                    <ChevronRight class="h-4 w-4 ml-1" />
                  </Button>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </template>
    </div>
  </div>
</template>
