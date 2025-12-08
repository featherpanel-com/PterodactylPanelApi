// ===============================================
// PterodactylPanelApi Plugin - Frontend JavaScript
// ===============================================

console.log('🚀 PterodactylPanelApi Plugin Loading...');

// Wait for FeatherPanel API to be available
function waitForAPI() {
	return new Promise((resolve) => {
		if (window.FeatherPanel && window.FeatherPanel.api) {
			resolve();
		} else {
			// Check every 100ms until API is available
			const check = setInterval(() => {
				if (window.FeatherPanel && window.FeatherPanel.api) {
					clearInterval(check);
					resolve();
				}
			}, 100);
		}
	});
}

// Create modal/overlay system for plugin UI
class PterodactylPanelApiUI {
	constructor() {
		this.modals = new Map();
	}

	showModal(id, title, content, options = {}) {
		this.closeModal(id); // Close existing modal with same ID

		const overlay = document.createElement('div');
		overlay.className = 'PterodactylPanelApi-overlay';
		overlay.addEventListener('click', (e) => {
			if (e.target === overlay) {
				this.closeModal(id);
			}
		});

		const modal = document.createElement('div');
		modal.className = 'PterodactylPanelApi-modal';
		modal.style.width = options.width || '800px';

		const header = document.createElement('div');
		header.className = 'PterodactylPanelApi-modal-header';

		const titleEl = document.createElement('h2');
		titleEl.className = 'PterodactylPanelApi-modal-title';
		titleEl.innerHTML = title;

		const closeBtn = document.createElement('button');
		closeBtn.className = 'PterodactylPanelApi-modal-close';
		closeBtn.innerHTML = '×';
		closeBtn.addEventListener('click', () => this.closeModal(id));

		header.appendChild(titleEl);
		header.appendChild(closeBtn);

		const contentEl = document.createElement('div');
		contentEl.className = 'PterodactylPanelApi-modal-content';
		contentEl.innerHTML = content;

		modal.appendChild(header);
		modal.appendChild(contentEl);
		overlay.appendChild(modal);

		document.body.appendChild(overlay);
		this.modals.set(id, overlay);

		// Handle escape key
		const handleEscape = (e) => {
			if (e.key === 'Escape') {
				this.closeModal(id);
				document.removeEventListener('keydown', handleEscape);
			}
		};
		document.addEventListener('keydown', handleEscape);
	}

	closeModal(id) {
		const modal = this.modals.get(id);
		if (modal) {
			modal.style.animation = 'PterodactylPanelApi-fade-in 0.2s ease-out reverse';
			setTimeout(() => {
				if (modal.parentNode) {
					modal.parentNode.removeChild(modal);
				}
				this.modals.delete(id);
			}, 200);
		}
	}

	closeAllModals() {
		for (const [id] of this.modals) {
			this.closeModal(id);
		}
	}
}

// Main PterodactylPanelApi Plugin Class
class PterodactylPanelApiPlugin {
	constructor() {
		this.ui = new PterodactylPanelApiUI();
		this.api = null;
		this.apiKeysState = {
			items: [],
			page: 1,
			limit: 10,
			totalPages: 1,
			totalRecords: 0,
			query: ''
		};
	}

	async init(api) {
		this.api = api;
		console.log('🚀 PterodactylPanelApi Plugin initialized!');
	}



	// API Keys Manager
	openApiKeysManager() {
		this.apiKeysState.page = 1;
		this.apiKeysState.query = '';
		this.renderApiKeysWindow();
		this.loadApiKeys();
	}

	renderApiKeysWindow() {
		const content = `
			<div class="PterodactylPanelApi-toolbar">
				<input id="papi-search" class="PterodactylPanelApi-input" type="text" placeholder="Search by name..." />
				<button class="PterodactylPanelApi-button" id="papi-create-btn">+</button>
			</div>
			<div id="papi-table"></div>
			<div id="papi-pagination" class="PterodactylPanelApi-pagination">
				<div id="papi-page-info"></div>
				<div>
					<button class="PterodactylPanelApi-button secondary" id="papi-prev">Prev</button>
					<button class="PterodactylPanelApi-button" id="papi-next">Next</button>
				</div>
			</div>
		`;
		this.ui.showModal('api-keys', '🔐 Manage Pterodactyl API Keys', content, { width: '900px' });

		setTimeout(() => {
			const search = document.getElementById('papi-search');
			const createBtn = document.getElementById('papi-create-btn');
			const prev = document.getElementById('papi-prev');
			const next = document.getElementById('papi-next');

			if (search) {
				search.value = this.apiKeysState.query;
				search.addEventListener('input', () => {
					this.apiKeysState.query = search.value;
					this.apiKeysState.page = 1;
					this.loadApiKeys();
				});
			}
			if (createBtn) {
				createBtn.addEventListener('click', () => this.openCreateForm());
			}
			if (prev) {
				prev.addEventListener('click', () => {
					if (this.apiKeysState.page > 1) {
						this.apiKeysState.page -= 1;
						this.loadApiKeys();
					}
				});
			}
			if (next) {
				next.addEventListener('click', () => {
					if (this.apiKeysState.page < this.apiKeysState.totalPages) {
						this.apiKeysState.page += 1;
						this.loadApiKeys();
					}
				});
			}
		}, 0);
	}

	async loadApiKeys() {
		try {
			const url = new URL('/api/pterodactylpanelapi/api-keys', window.location.origin);
			url.searchParams.set('page', String(this.apiKeysState.page));
			url.searchParams.set('limit', String(this.apiKeysState.limit));
			if (this.apiKeysState.query) {
				url.searchParams.set('search', this.apiKeysState.query);
			}
			const res = await fetch(url.toString(), { credentials: 'include' });
			const data = await res.json();
			if (!res.ok || data.success === false) {
				throw new Error(data?.message || 'Failed to load');
			}
			this.apiKeysState.items = data.data.keys || [];
			const p = data.data.pagination || {};
			this.apiKeysState.totalPages = p.total_pages || 1;
			this.apiKeysState.totalRecords = p.total_records || 0;
			this.renderApiKeysTable();
		} catch (e) {
			this.renderApiKeysTable(String(e?.message || e));
		}
	}

	renderApiKeysTable(errorMsg) {
		const tableHost = document.getElementById('papi-table');
		const pageInfo = document.getElementById('papi-page-info');
		if (!tableHost) return;

		if (pageInfo) {
			const from = this.apiKeysState.totalRecords === 0 ? 0 : ((this.apiKeysState.page - 1) * this.apiKeysState.limit + 1);
			const to = this.apiKeysState.totalRecords === 0 ? 0 : Math.min(from + this.apiKeysState.limit - 1, this.apiKeysState.totalRecords);
			pageInfo.textContent = `Showing ${from}-${to} of ${this.apiKeysState.totalRecords}`;
		}

		if (errorMsg) {
			tableHost.innerHTML = `<div class="PterodactylPanelApi-card" style="color:#991b1b;">${errorMsg}</div>`;
			return;
		}

		if (!this.apiKeysState.items.length) {
			tableHost.innerHTML = `<div class="PterodactylPanelApi-card">No API keys found.</div>`;
			return;
		}

		const rows = this.apiKeysState.items.map((k) => {
			const lastUsed = k.last_used ? new Date(k.last_used).toLocaleString() : 'Never';
			return `
				<tr>
					<td>${k.id}</td>
					<td>${this.escapeHtml(k.name)}</td>
					<td>${this.maskKey(k.key)}</td>
					<td>${lastUsed}</td>
					<td class="PterodactylPanelApi-actions">
						<button class="PterodactylPanelApi-button secondary" data-edit="${k.id}">Edit</button>
						<button class="PterodactylPanelApi-button danger" data-delete="${k.id}">Delete</button>
					</td>
				</tr>
			`;
		}).join('');

		tableHost.innerHTML = `
			<div class="PterodactylPanelApi-card" style="padding:0; overflow:auto;">
				<table class="PterodactylPanelApi-table">
					<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Key</th>
							<th>Last used</th>
							<th class="PterodactylPanelApi-actions">Actions</th>
						</tr>
					</thead>
					<tbody>
						${rows}
					</tbody>
				</table>
			</div>
		`;

		// Bind row actions
		tableHost.querySelectorAll('[data-edit]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const id = Number(btn.getAttribute('data-edit'));
				const key = this.apiKeysState.items.find(i => Number(i.id) === id);
				if (key) this.openEditForm(key);
			});
		});
		tableHost.querySelectorAll('[data-delete]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const id = Number(btn.getAttribute('data-delete'));
				this.confirmDelete(id);
			});
		});
	}

	openCreateForm() {
		const form = this.renderKeyForm();
		this.ui.showModal('api-keys-form', '➕ Create API Key', form, { width: '520px' });
		this.bindFormHandlers('create');
	}

	openEditForm(key) {
		const form = this.renderKeyForm(key);
		this.ui.showModal('api-keys-form', '✏️ Edit API Key', form, { width: '520px' });
		this.bindFormHandlers('edit', key);
	}

	renderKeyForm(key = null) {
		const name = key?.name || '';
		const apiKey = key?.key || '';
		const inferredType = this.inferTypeFromKey(apiKey);
		return `
			<div class="PterodactylPanelApi-card">
				<div class="PterodactylPanelApi-form-group">
					<label class="PterodactylPanelApi-label">What kind of platform manipulation do you want to use?</label>
					<select id="papi-f-type" class="PterodactylPanelApi-field">
						<option value="admin" ${inferredType === 'admin' ? 'selected' : ''}>Admin</option>
					</select>
				</div>
				<div class="PterodactylPanelApi-form-group">
					<label class="PterodactylPanelApi-label">Name</label>
					<input id="papi-f-name" class="PterodactylPanelApi-field" type="text" value="${this.escapeAttr(name)}" />
				</div>
				<div class="PterodactylPanelApi-form-group">
					<label class="PterodactylPanelApi-label">Key</label>
					<div style="display:flex; gap:8px;">
						<input id="papi-f-key" class="PterodactylPanelApi-field" type="text" value="${this.escapeAttr(apiKey)}" />
						<button class="PterodactylPanelApi-button" id="papi-f-generate" type="button">Generate</button>
					</div>
				</div>
				<div class="PterodactylPanelApi-form-actions">
					<button class="PterodactylPanelApi-button secondary" id="papi-f-cancel">Cancel</button>
					<button class="PterodactylPanelApi-button" id="papi-f-save">Save</button>
				</div>
			</div>
		`;
	}

	bindFormHandlers(mode, key = null) {
		const cancel = document.getElementById('papi-f-cancel');
		const save = document.getElementById('papi-f-save');
		const gen = document.getElementById('papi-f-generate');
		const typeEl = document.getElementById('papi-f-type');
		if (cancel) cancel.addEventListener('click', () => this.ui.closeModal('api-keys-form'));
		if (save) save.addEventListener('click', async () => {
			const nameEl = document.getElementById('papi-f-name');
			const keyEl = document.getElementById('papi-f-key');
			const payload = {
				name: nameEl.value.trim(),
				key: keyEl.value.trim(),
			};
			try {
				if (!payload.name || !payload.key) {
					throw new Error('Name and Key are required');
				}
				if (mode === 'create') {
					await this.apiCreateKey(payload);
				} else {
					await this.apiUpdateKey(key.id, payload);
				}
				this.ui.closeModal('api-keys-form');
				await this.loadApiKeys();
			} catch (e) {
				alert(e?.message || String(e));
			}
		});
		if (gen) gen.addEventListener('click', () => {
			const keyEl = document.getElementById('papi-f-key');
			const type = typeEl && typeEl.value === 'admin' ? 'admin' : 'admin';
			keyEl.value = this.generateKey(type);
		});
	}

	confirmDelete(id) {
		const content = `
			<div class="PterodactylPanelApi-card">
				<p>Are you sure you want to delete API key #${id}?</p>
				<div class="PterodactylPanelApi-form-actions" style="margin-top:12px;">
					<button class="PterodactylPanelApi-button secondary" id="papi-d-cancel">Cancel</button>
					<button class="PterodactylPanelApi-button danger" id="papi-d-confirm">Delete</button>
				</div>
			</div>
		`;
		this.ui.showModal('api-keys-delete', '🗑️ Delete API Key', content, { width: '420px' });
		setTimeout(() => {
			const cancel = document.getElementById('papi-d-cancel');
			const confirm = document.getElementById('papi-d-confirm');
			if (cancel) cancel.addEventListener('click', () => this.ui.closeModal('api-keys-delete'));
			if (confirm) confirm.addEventListener('click', async () => {
				try {
					await this.apiDeleteKey(id);
					this.ui.closeModal('api-keys-delete');
					await this.loadApiKeys();
				} catch (e) {
					alert(e?.message || String(e));
				}
			});
		}, 0);
	}

	maskKey(value) {
		if (!value) return '';
		if (value.length <= 8) return '*'.repeat(Math.max(0, value.length - 2)) + value.slice(-2);
		return value.slice(0, 4) + '••••' + value.slice(-4);
	}

	escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	escapeAttr(str) { return this.escapeHtml(str); }

	generateKey(type = 'admin') {
		const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		const length = 48; // chars after prefix
		let body = '';
		for (let i = 0; i < length; i++) {
			const idx = Math.floor(Math.random() * alphabet.length);
			body += alphabet[idx];
		}
		const prefix = type === 'admin' ? 'ptla_' : 'ptla_';
		return prefix + body;
	}

	inferTypeFromKey(key) {
		if (typeof key === 'string' && key.startsWith('ptla_')) return 'admin';
		return 'admin';
	}

	// API calls
	async apiCreateKey(payload) {
		const res = await fetch('/api/pterodactylpanelapi/api-keys', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'include',
			body: JSON.stringify(payload)
		});
		const data = await res.json();
		if (!res.ok || data.success === false) throw new Error(data?.message || 'Failed to create');
		return data;
	}

	async apiUpdateKey(id, payload) {
		const res = await fetch(`/api/pterodactylpanelapi/api-keys/${id}`, {
			method: 'PATCH',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'include',
			body: JSON.stringify(payload)
		});
		const data = await res.json();
		if (!res.ok || data.success === false) throw new Error(data?.message || 'Failed to update');
		return data;
	}

	async apiDeleteKey(id) {
		const res = await fetch(`/api/pterodactylpanelapi/api-keys/${id}`, { method: 'DELETE', credentials: 'include' });
		const data = await res.json();
		if (!res.ok || data.success === false) throw new Error(data?.message || 'Failed to delete');
		return data;
	}
}

// Main plugin initialization
async function initPterodactylPanelApiPlugin() {
	await waitForAPI();

	const api = window.FeatherPanel.api;
	const PterodactylPanelApiPluginInstance = new PterodactylPanelApiPlugin();
	await PterodactylPanelApiPluginInstance.init(api);

	// Make plugin globally available
	window.PterodactylPanelApiPlugin = PterodactylPanelApiPluginInstance;

	console.log('🚀 PterodactylPanelApi Plugin API Ready!');
}

// Initialize the plugin
initPterodactylPanelApiPlugin();

console.log('🚀 PterodactylPanelApi Plugin script loaded');