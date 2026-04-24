<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import Modal from '$lib/components/common/Modal.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import { slugify } from '$lib/utils/slugify.js';
  import Select from '$lib/components/common/Select.svelte';

  const TYPE_OPTS = [
    { value: 'css', label: 'CSS' },
    { value: 'js',  label: 'JavaScript' },
  ];

  let assets     = $state([]);
  let loading    = $state(true);
  let deleteItem = $state(null);
  let showCreate = $state(false);

  let newName    = $state('');
  let newSlug    = $state('');
  let newType    = $state('css');
  let creating   = $state(false);
  let slugEdited = false;

  onMount(loadAssets);

  async function loadAssets() {
    loading = true;
    try {
      const res = await api.get('assets');
      assets = res.data ?? [];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function onNameInput() { if (!slugEdited) newSlug = slugify(newName); }

  async function create() {
    if (!newName.trim()) { notifications.error('Name is required'); return; }
    creating = true;
    try {
      const res = await api.post('assets', {
        name: newName.trim(),
        slug: newSlug || slugify(newName),
        type: newType,
        content: '',
      });
      notifications.success('Asset created');
      goto(`/admin/assets/${res.data.id}`);
    } catch (e) {
      notifications.error(e.message);
    } finally {
      creating = false;
    }
  }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`assets/${item.id}`);
      assets = assets.filter(a => a.id !== item.id);
      notifications.success('Asset deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  function typeColor(type) {
    return type === 'css' ? 'var(--sc-info)' : 'var(--sc-warning, #f59e0b)';
  }

  function resetModal() {
    newName = ''; newSlug = ''; newType = 'css'; slugEdited = false;
  }
</script>

<AdminShell title="Assets">
  {#snippet actions()}
    <button class="btn btn--primary" onclick={() => { resetModal(); showCreate = true; }}>+ New Asset</button>
  {/snippet}

  {#snippet children()}
    {#if loading}
      <p class="muted">Loading…</p>
    {:else if assets.length === 0}
      <EmptyState
        title="No assets yet"
        message="Create CSS stylesheets and JavaScript files to include in your templates."
        action="New Asset"
        onaction={() => { resetModal(); showCreate = true; }}
      />
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>File</th><th>Type</th><th>Updated</th><th></th></tr>
          </thead>
          <tbody>
            {#each assets as a (a.id)}
              <tr>
                <td><a href="/admin/assets/{a.id}" class="item-link">{a.name}</a></td>
                <td class="muted-cell mono">{a.slug}.{a.type}</td>
                <td>
                  <span class="type-badge" style="color:{typeColor(a.type)};background:{typeColor(a.type)}18">{a.type.toUpperCase()}</span>
                </td>
                <td class="muted-cell">{formatDate(a.updated_at)}</td>
                <td class="actions-cell">
                  <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = a} title="Delete">
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

<Modal open={showCreate} title="New Asset" onclose={() => showCreate = false}>
  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Name <span class="req">*</span></label>
        <input class="input" type="text" bind:value={newName} oninput={onNameInput} placeholder="e.g. Main Styles" />
      </div>
      <div class="field">
        <label class="label">Filename</label>
        <input class="input" type="text" bind:value={newSlug} oninput={() => slugEdited = true} placeholder="auto-generated" />
        <p class="hint">Will be saved as <strong>{newSlug || slugify(newName) || 'filename'}.{newType}</strong></p>
      </div>
      <div class="field">
        <label class="label">Type</label>
        <Select bind:value={newType} options={TYPE_OPTS} />
      </div>
    </div>
  {/snippet}
  {#snippet footer()}
    <button class="btn btn--ghost" onclick={() => showCreate = false}>Cancel</button>
    <button class="btn btn--primary" onclick={create} disabled={creating}>
      {creating ? 'Creating…' : 'Create'}
    </button>
  {/snippet}
</Modal>

<ConfirmDialog
  open={!!deleteItem}
  title="Delete asset"
  message="Delete '{deleteItem?.name}'? The file will be removed."
  confirmLabel="Delete"
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
  .item-link { color: var(--sc-text); font-weight: 500; }
  .item-link:hover { color: var(--sc-accent); }
  .muted-cell { color: var(--sc-text-muted); font-size: 12px; }
  .mono { font-family: var(--sc-font-mono); }
  .type-badge { font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 700; }
  .actions-cell { text-align: right; width: 40px; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .form { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
  .hint { margin: 0; font-size: 12px; color: var(--sc-text-muted); }
  .hint strong { color: var(--sc-text); }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
</style>
