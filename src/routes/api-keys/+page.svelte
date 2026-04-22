<script>
  import { onMount } from 'svelte';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import Modal from '$lib/components/common/Modal.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  const ALL_SCOPES = ['read','write','media','webhooks'];

  let keys       = $state([]);
  let loading    = $state(true);
  let deleteItem = $state(null);
  let showCreate = $state(false);
  let newKey     = $state(null);   // shown once after creation
  let copied     = $state(false);

  let newName    = $state('');
  let newScopes  = $state(['read']);
  let creating   = $state(false);

  onMount(loadKeys);

  async function loadKeys() {
    loading = true;
    try {
      const res = await api.get('api-keys');
      keys = res.data ?? [];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function toggleScope(s) {
    newScopes = newScopes.includes(s) ? newScopes.filter(x => x !== s) : [...newScopes, s];
  }

  async function create() {
    if (!newName.trim()) { notifications.error('Name is required'); return; }
    if (!newScopes.length) { notifications.error('Select at least one scope'); return; }
    creating = true;
    try {
      const res = await api.post('api-keys', { name: newName.trim(), scopes: newScopes });
      newKey = res.data.key;
      keys = [...keys, { ...res.data, key: undefined }];
      showCreate = false;
      newName = ''; newScopes = ['read'];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      creating = false;
    }
  }

  async function copy() {
    await navigator.clipboard.writeText(newKey);
    copied = true;
    setTimeout(() => { copied = false; }, 2000);
  }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`api-keys/${item.id}`);
      keys = keys.filter(k => k.id !== item.id);
      notifications.success('API key revoked');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<AdminShell title="API Keys">
  {#snippet actions()}
    <button class="btn btn--primary" onclick={() => showCreate = true}>+ New Key</button>
  {/snippet}

  {#snippet children()}
    {#if loading}
      <p class="muted">Loading…</p>
    {:else if keys.length === 0}
      <EmptyState
        title="No API keys yet"
        message="API keys allow external services to authenticate with Space Cadet CMS."
        action="New Key"
        onaction={() => showCreate = true}
      />
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>Prefix</th><th>Scopes</th><th>Last used</th><th></th></tr>
          </thead>
          <tbody>
            {#each keys as k (k.id)}
              <tr>
                <td class="key-name">{k.name}</td>
                <td class="mono">{k.key_prefix}…</td>
                <td>
                  {#each (Array.isArray(k.scopes) ? k.scopes : JSON.parse(k.scopes ?? '[]')) as s}
                    <span class="scope-badge">{s}</span>
                  {/each}
                </td>
                <td class="muted-cell">{k.last_used_at ? formatDate(k.last_used_at) : 'Never'}</td>
                <td class="actions-cell">
                  <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = k} title="Revoke">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10"/>
                    </svg>
                  </button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  {/snippet}
</AdminShell>

<!-- Create modal -->
<Modal open={showCreate} title="Create API Key" onclose={() => showCreate = false}>
  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label" for="new-key-name">Name <span class="req">*</span></label>
        <input id="new-key-name" class="input" type="text" bind:value={newName} placeholder="e.g. My App" />
      </div>
      <div class="field">
        <div class="label" role="group" aria-label="Scopes">Scopes</div>
        <div class="scopes">
          {#each ALL_SCOPES as s}
            <label class="check-label">
              <input type="checkbox" checked={newScopes.includes(s)} onchange={() => toggleScope(s)} />
              {s}
            </label>
          {/each}
        </div>
      </div>
    </div>
  {/snippet}
  {#snippet footer()}
    <button class="btn btn--ghost" onclick={() => showCreate = false}>Cancel</button>
    <button class="btn btn--primary" onclick={create} disabled={creating}>
      {creating ? 'Creating…' : 'Create Key'}
    </button>
  {/snippet}
</Modal>

<!-- New key reveal modal -->
<Modal open={!!newKey} title="Your new API Key">
  {#snippet children()}
    <p class="reveal-msg">Copy this key now — it will not be shown again.</p>
    <div class="key-display">
      <code class="key-value">{newKey}</code>
      <button class="btn btn--secondary" onclick={copy}>{copied ? '✓ Copied' : 'Copy'}</button>
    </div>
  {/snippet}
  {#snippet footer()}
    <button class="btn btn--primary" onclick={() => newKey = null}>Done</button>
  {/snippet}
</Modal>

<ConfirmDialog
  open={!!deleteItem}
  title="Revoke API key"
  message="Revoke '{deleteItem?.name}'? Any integrations using this key will stop working."
  confirmLabel="Revoke"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .table-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; }
  .table thead th { padding: 10px 16px; background: var(--sc-surface-2); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); text-align: left; border-bottom: 1px solid var(--sc-border); }
  .table tbody tr { border-bottom: 1px solid var(--sc-border); }
  .table tbody tr:last-child { border-bottom: none; }
  .table tbody tr:hover { background: var(--sc-surface-2); }
  .table td { padding: 10px 16px; font-size: 13px; }
  .key-name { font-weight: 600; color: var(--sc-text); }
  .mono { font-family: var(--sc-font-mono); font-size: 12px; color: var(--sc-text-muted); }
  .scope-badge { display: inline-block; font-size: 11px; background: rgba(124,106,247,.12); color: var(--sc-accent); padding: 2px 7px; border-radius: 20px; margin-right: 4px; }
  .muted-cell { color: var(--sc-text-muted); font-size: 12px; }
  .actions-cell { text-align: right; width: 40px; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .form { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .scopes { display: flex; gap: 14px; flex-wrap: wrap; }
  .check-label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--sc-text-muted); cursor: pointer; }
  .check-label input { accent-color: var(--sc-accent); }
  .reveal-msg { margin: 0 0 14px; font-size: 13px; color: var(--sc-warning); }
  .key-display { display: flex; gap: 10px; align-items: center; }
  .key-value { flex: 1; font-family: var(--sc-font-mono); font-size: 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 10px; word-break: break-all; color: var(--sc-text); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--secondary { background: var(--sc-surface-2); border: 1px solid var(--sc-border); color: var(--sc-text); flex-shrink: 0; }
</style>
