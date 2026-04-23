<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import Modal from '$lib/components/common/Modal.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { slugify } from '$lib/utils/slugify.js';

  let menus      = $state([]);
  let loading    = $state(true);
  let showCreate = $state(false);
  let deleteItem = $state(null);

  let newName    = $state('');
  let newSlug    = $state('');
  let creating   = $state(false);
  let slugEdited = false;

  onMount(loadMenus);

  async function loadMenus() {
    loading = true;
    try {
      const res = await api.get('menus');
      menus = res.data ?? [];
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
      const res = await api.post('menus', { name: newName.trim(), slug: newSlug || slugify(newName) });
      menus = [...menus, res.data];
      showCreate = false;
      newName = ''; newSlug = ''; slugEdited = false;
      notifications.success('Menu created');
      goto(`/admin/menus/${res.data.id}`);
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
      await api.delete(`menus/${item.id}`);
      menus = menus.filter(m => m.id !== item.id);
      notifications.success('Menu deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<AdminShell title="Menus">
  {#snippet actions()}
    <button class="btn btn--primary" onclick={() => showCreate = true}>+ New Menu</button>
  {/snippet}

  {#snippet children()}
    {#if loading}
      <p class="muted">Loading…</p>
    {:else if menus.length === 0}
      <EmptyState
        title="No menus yet"
        message="Build navigation menus for your site."
        action="New Menu"
        onaction={() => showCreate = true}
      />
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>Slug</th><th></th></tr>
          </thead>
          <tbody>
            {#each menus as m (m.id)}
              <tr>
                <td><a href="/admin/menus/{m.id}" class="item-link">{m.name}</a></td>
                <td class="muted-cell">{m.slug}</td>
                <td class="actions-cell">
                  <button class="btn-icon" onclick={() => deleteItem = m} title="Delete">
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

<Modal open={showCreate} title="New Menu" onclose={() => showCreate = false}>
  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Name <span class="req">*</span></label>
        <input class="input" type="text" bind:value={newName} oninput={onNameInput} placeholder="e.g. Main Navigation" />
      </div>
      <div class="field">
        <label class="label">Slug</label>
        <input class="input" type="text" bind:value={newSlug} oninput={() => slugEdited = true} placeholder="auto-generated" />
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
  title="Delete menu"
  message="Delete '{deleteItem?.name}'?"
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
  .actions-cell { text-align: right; width: 40px; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .form { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
</style>
