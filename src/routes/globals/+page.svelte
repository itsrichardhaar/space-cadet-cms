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

  let groups     = $state([]);
  let loading    = $state(true);
  let showCreate = $state(false);
  let deleteItem = $state(null);

  // Create form
  let newName    = $state('');
  let newSlug    = $state('');
  let newDesc    = $state('');
  let creating   = $state(false);
  let slugEdited = false;

  onMount(loadGroups);

  async function loadGroups() {
    loading = true;
    try {
      const res = await api.get('globals');
      groups = res.data ?? [];
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
      const res = await api.post('globals', { name: newName.trim(), slug: newSlug || slugify(newName), description: newDesc || null });
      groups = [...groups, res.data];
      showCreate = false;
      newName = ''; newSlug = ''; newDesc = ''; slugEdited = false;
      notifications.success('Global group created');
      goto(`/globals/${res.data.slug}`);
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
      await api.delete(`globals/${item.id}`);
      groups = groups.filter(g => g.id !== item.id);
      notifications.success('Global group deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<AdminShell title="Globals">
  {#snippet actions()}
    <button class="btn btn--primary" onclick={() => showCreate = true}>+ New Group</button>
  {/snippet}

  {#snippet children()}
    {#if loading}
      <p class="muted">Loading…</p>
    {:else if groups.length === 0}
      <EmptyState
        title="No global groups yet"
        message="Globals store site-wide settings and content available anywhere."
        action="New Group"
        onaction={() => showCreate = true}
      />
    {:else}
      <div class="grid">
        {#each groups as g (g.id)}
          <a href="/globals/{g.slug}" class="group-card">
            <span class="group-icon">⚙</span>
            <div class="group-info">
              <span class="group-name">{g.name}</span>
              {#if g.description}<span class="group-desc">{g.description}</span>{/if}
            </div>
            <button class="del-btn" onclick={(e) => { e.stopPropagation(); e.preventDefault(); deleteItem = g; }} title="Delete">×</button>
          </a>
        {/each}
      </div>
    {/if}
  {/snippet}
</AdminShell>

<!-- Create modal -->
<Modal open={showCreate} title="New Global Group" onclose={() => showCreate = false}>
  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Name <span class="req">*</span></label>
        <input class="input" type="text" bind:value={newName} oninput={onNameInput} placeholder="e.g. Site Settings" />
      </div>
      <div class="field">
        <label class="label">Slug</label>
        <input class="input" type="text" bind:value={newSlug} oninput={() => slugEdited = true} placeholder="auto-generated" />
      </div>
      <div class="field">
        <label class="label">Description</label>
        <input class="input" type="text" bind:value={newDesc} placeholder="Optional description" />
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
  title="Delete global group"
  message="Delete '{deleteItem?.name}'? All saved values will be lost."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
  .group-card { display: flex; align-items: center; gap: 14px; padding: 18px; background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); text-decoration: none; position: relative; transition: border-color .15s; }
  .group-card:hover { border-color: var(--sc-accent); }
  .group-icon { font-size: 22px; flex-shrink: 0; }
  .group-info { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
  .group-name { font-size: 14px; font-weight: 600; color: var(--sc-text); }
  .group-desc { font-size: 12px; color: var(--sc-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .del-btn { position: absolute; top: 10px; right: 10px; background: none; border: none; color: var(--sc-text-muted); font-size: 18px; line-height: 1; padding: 0; cursor: pointer; opacity: 0; transition: opacity .15s; }
  .group-card:hover .del-btn { opacity: 1; }
  .del-btn:hover { color: var(--sc-danger); }
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
