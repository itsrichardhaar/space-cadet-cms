<script>
  import { onMount } from 'svelte';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  let folders    = $state([]);
  let loading    = $state(true);
  let deleteItem = $state(null);

  // Inline-create state
  let newName    = $state('');
  let newParent  = $state('');
  let creating   = $state(false);

  // Inline-edit state
  let editId     = $state(null);
  let editName   = $state('');

  onMount(loadFolders);

  async function loadFolders() {
    loading = true;
    try {
      const res = await api.get('folders');
      folders = res.data ?? [];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  async function create() {
    if (!newName.trim()) return;
    creating = true;
    try {
      const body = { name: newName.trim() };
      if (newParent) body.parent_id = parseInt(newParent);
      const res = await api.post('folders', body);
      folders = [...folders, res.data];
      newName = ''; newParent = '';
      notifications.success('Folder created');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      creating = false;
    }
  }

  function startEdit(f) { editId = f.id; editName = f.name; }
  function cancelEdit() { editId = null; editName = ''; }

  async function saveEdit(id) {
    if (!editName.trim()) return;
    try {
      await api.put(`folders/${id}`, { name: editName.trim() });
      folders = folders.map(f => f.id === id ? { ...f, name: editName.trim() } : f);
      editId = null;
    } catch (e) {
      notifications.error(e.message);
    }
  }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`folders/${item.id}`);
      folders = folders.filter(f => f.id !== item.id);
      notifications.success('Folder deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  // Build a tree display from flat list
  function tree(list) {
    const roots = list.filter(f => !f.parent_id);
    const result = [];
    function walk(items, depth = 0) {
      for (const f of items) {
        result.push({ ...f, _depth: depth });
        const children = list.filter(c => c.parent_id === f.id);
        if (children.length) walk(children, depth + 1);
      }
    }
    walk(roots);
    return result;
  }
</script>

<AdminShell title="Folders">
  {#snippet children()}
    <!-- Create row -->
    <div class="create-row">
      <input
        class="input"
        type="text"
        bind:value={newName}
        placeholder="New folder name"
        onkeydown={e => e.key === 'Enter' && create()}
      />
      <select class="input input--sm" bind:value={newParent}>
        <option value="">No parent</option>
        {#each folders as f}
          <option value={f.id}>{f.name}</option>
        {/each}
      </select>
      <button class="btn btn--primary" onclick={create} disabled={creating || !newName.trim()}>
        {creating ? '…' : '+ Add'}
      </button>
    </div>

    {#if loading}
      <p class="muted">Loading…</p>
    {:else if folders.length === 0}
      <p class="muted">No folders yet. Create one above.</p>
    {:else}
      <div class="list">
        {#each tree(folders) as f (f.id)}
          <div class="folder-row" style="padding-left: {f._depth * 24 + 14}px">
            <svg class="folder-icon" width="15" height="15" viewBox="0 0 16 16" fill="none">
              <path d="M2 4a1 1 0 011-1h4l1 1h5a1 1 0 011 1v7a1 1 0 01-1 1H3a1 1 0 01-1-1V4z" stroke="currentColor" stroke-width="1.2"/>
            </svg>

            {#if editId === f.id}
              <input
                class="input input--inline"
                type="text"
                bind:value={editName}
                onkeydown={e => { if (e.key === 'Enter') saveEdit(f.id); if (e.key === 'Escape') cancelEdit(); }}
                autofocus
              />
              <button class="btn-sm" onclick={() => saveEdit(f.id)}>Save</button>
              <button class="btn-sm btn-sm--ghost" onclick={cancelEdit}>Cancel</button>
            {:else}
              <span class="folder-name">{f.name}</span>
              <div class="folder-actions">
                <button class="btn-icon" onclick={() => startEdit(f)} title="Rename">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M11.5 2.5l2 2L5 13H3v-2L11.5 2.5z"/>
                  </svg>
                </button>
                <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = f} title="Delete">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10"/>
                  </svg>
                </button>
              </div>
            {/if}
          </div>
        {/each}
      </div>
    {/if}
  {/snippet}
</AdminShell>

<ConfirmDialog
  open={!!deleteItem}
  title="Delete folder"
  message="Delete '{deleteItem?.name}'? Files in this folder will be moved to the root."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .create-row { display: flex; gap: 10px; margin-bottom: 20px; max-width: 500px; }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; flex: 1; }
  .input:focus { border-color: var(--sc-accent); }
  .input--sm { flex: 0 0 160px; }
  .input--inline { flex: 1; padding: 4px 8px; }
  .list { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; max-width: 600px; }
  .folder-row { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid var(--sc-border); }
  .folder-row:last-child { border-bottom: none; }
  .folder-row:hover { background: var(--sc-surface-2); }
  .folder-icon { color: var(--sc-text-muted); flex-shrink: 0; }
  .folder-name { flex: 1; font-size: 13px; color: var(--sc-text); }
  .folder-actions { display: flex; gap: 4px; opacity: 0; transition: opacity .15s; }
  .folder-row:hover .folder-actions { opacity: 1; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon:hover { color: var(--sc-accent); background: rgba(124,106,247,.1); }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .btn-sm { padding: 4px 10px; border-radius: var(--sc-radius); font-size: 12px; font-weight: 600; border: none; cursor: pointer; background: var(--sc-accent); color: #fff; }
  .btn-sm--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn { padding: 8px 14px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; flex-shrink: 0; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
</style>
