<script>
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import Modal from '$lib/components/common/Modal.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import { slugify } from '$lib/utils/slugify.js';

  let components = $state([]);
  let loading    = $state(true);
  let deleteItem = $state(null);
  let showCreate = $state(false);

  let newName    = $state('');
  let newSlug    = $state('');
  let creating   = $state(false);
  let slugEdited = false;

  $effect(() => { load(); });

  async function load() {
    loading = true;
    try {
      const res = await api.get('templates');
      components = (res.data ?? []).filter(t => t.type === 'partial');
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
      const res = await api.post('templates', {
        name:   newName.trim(),
        slug:   newSlug || slugify(newName),
        type:   'partial',
        source: '',
      });
      notifications.success('Component created');
      goto(`/admin/components/${res.data.id}`);
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
      await api.delete(`templates/${item.id}`);
      components = components.filter(c => c.id !== item.id);
      notifications.success('Component deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  function reset() { newName = ''; newSlug = ''; slugEdited = false; }
</script>

<AdminShell title="Components">
  {#snippet actions()}
    <button class="btn btn--primary" onclick={() => { reset(); showCreate = true; }}>+ New Component</button>
  {/snippet}

  {#snippet children()}
    {#if loading}
      <p class="muted">Loading…</p>
    {:else if components.length === 0}
      <EmptyState
        title="No components yet"
        message="Components are reusable HTML snippets — navbars, footers, cards. Include them in any template using the include tag."
        action="New Component"
        onaction={() => { reset(); showCreate = true; }}
      />
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>Slug</th><th>Updated</th><th></th></tr>
          </thead>
          <tbody>
            {#each components as c (c.id)}
              <tr>
                <td><a href="/admin/components/{c.id}" class="item-link">{c.name}</a></td>
                <td class="muted-cell mono">{c.slug}</td>
                <td class="muted-cell">{formatDate(c.updated_at)}</td>
                <td class="actions-cell">
                  <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = c} title="Delete">
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

      <p class="include-hint">Include in any template: <code>{'{% include "slug" %}'}</code></p>
    {/if}
  {/snippet}
</AdminShell>

<Modal open={showCreate} title="New Component" onclose={() => showCreate = false}>
  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Name <span class="req">*</span></label>
        <input class="input" type="text" bind:value={newName} oninput={onNameInput} placeholder="e.g. Site Header" autofocus />
      </div>
      <div class="field">
        <label class="label">Slug</label>
        <input class="input" type="text" bind:value={newSlug} oninput={() => slugEdited = true} placeholder="auto-generated" />
        <p class="hint">Include with <code>{'{% include "'}{newSlug || slugify(newName) || 'slug'}{'"%}'}</code></p>
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
  title="Delete component"
  message="Delete '{deleteItem?.name}'? This cannot be undone."
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
  .actions-cell { text-align: right; width: 40px; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .include-hint { margin-top: 16px; font-size: 12px; color: var(--sc-text-muted); }
  .include-hint code { font-family: var(--sc-font-mono); color: var(--sc-accent); font-size: 11px; }
  .form { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
  .hint { margin: 0; font-size: 12px; color: var(--sc-text-muted); }
  .hint code { font-family: var(--sc-font-mono); color: var(--sc-accent); font-size: 11px; }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
</style>
