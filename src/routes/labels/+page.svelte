<script>
  import { onMount } from 'svelte';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { slugify } from '$lib/utils/slugify.js';

  const PRESET_COLORS = ['#7c6af7','#60a5fa','#34d399','#fbbf24','#f87171','#f472b6','#a78bfa','#fb923c'];

  let labels     = $state([]);
  let loading    = $state(true);
  let deleteItem = $state(null);

  // Inline-create state
  let newName    = $state('');
  let newColor   = $state(PRESET_COLORS[0]);
  let creating   = $state(false);
  let slugEdited = false;

  // Inline-edit state
  let editId     = $state(null);
  let editName   = $state('');
  let editColor  = $state('');

  onMount(loadLabels);

  async function loadLabels() {
    loading = true;
    try {
      const res = await api.get('labels');
      labels = res.data ?? [];
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
      const res = await api.post('labels', {
        name: newName.trim(),
        slug: slugify(newName),
        color: newColor,
      });
      labels = [...labels, res.data];
      newName = ''; newColor = PRESET_COLORS[0];
      notifications.success('Label created');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      creating = false;
    }
  }

  function startEdit(l) { editId = l.id; editName = l.name; editColor = l.color; }
  function cancelEdit() { editId = null; }

  async function saveEdit(id) {
    if (!editName.trim()) return;
    try {
      await api.put(`labels/${id}`, { name: editName.trim(), color: editColor });
      labels = labels.map(l => l.id === id ? { ...l, name: editName.trim(), color: editColor } : l);
      editId = null;
      notifications.success('Label updated');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`labels/${item.id}`);
      labels = labels.filter(l => l.id !== item.id);
      notifications.success('Label deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<AdminShell title="Labels">
  {#snippet children()}
    <!-- Create row -->
    <div class="create-row">
      <input
        class="input"
        type="text"
        bind:value={newName}
        placeholder="Label name"
        onkeydown={e => e.key === 'Enter' && create()}
      />
      <div class="color-picker">
        {#each PRESET_COLORS as c}
          <button
            class="color-swatch"
            class:color-swatch--active={newColor === c}
            style="background:{c}"
            onclick={() => newColor = c}
            title={c}
          ></button>
        {/each}
      </div>
      <button class="btn btn--primary" onclick={create} disabled={creating || !newName.trim()}>
        {creating ? '…' : '+ Add'}
      </button>
    </div>

    {#if loading}
      <p class="muted">Loading…</p>
    {:else if labels.length === 0}
      <EmptyState icon="◉" title="No labels yet" message="Labels help you organize and filter collection items. Create one above." />
    {:else}
      <div class="list">
        {#each labels as l (l.id)}
          <div class="label-row">
            {#if editId === l.id}
              <span class="dot" style="background:{editColor}"></span>
              <div class="color-picker color-picker--inline">
                {#each PRESET_COLORS as c}
                  <button
                    class="color-swatch color-swatch--sm"
                    class:color-swatch--active={editColor === c}
                    style="background:{c}"
                    onclick={() => editColor = c}
                  ></button>
                {/each}
              </div>
              <input
                class="input input--inline"
                type="text"
                bind:value={editName}
                onkeydown={e => { if (e.key === 'Enter') saveEdit(l.id); if (e.key === 'Escape') cancelEdit(); }}
              />
              <button class="btn-sm" onclick={() => saveEdit(l.id)}>Save</button>
              <button class="btn-sm btn-sm--ghost" onclick={cancelEdit}>Cancel</button>
            {:else}
              <span class="dot" style="background:{l.color ?? '#888'}"></span>
              <span class="label-name">{l.name}</span>
              <span class="label-slug">{l.slug}</span>
              <div class="row-actions">
                <button class="btn-icon" onclick={() => startEdit(l)} title="Edit">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M11.5 2.5l2 2L5 13H3v-2L11.5 2.5z"/>
                  </svg>
                </button>
                <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = l} title="Delete">
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
  title="Delete label"
  message="Delete '{deleteItem?.name}'? It will be removed from all items."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .create-row { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; max-width: 600px; flex-wrap: wrap; }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; flex: 1; min-width: 140px; }
  .input:focus { border-color: var(--sc-accent); }
  .input--inline { flex: 1; padding: 4px 8px; }
  .color-picker { display: flex; gap: 5px; align-items: center; }
  .color-picker--inline { flex-shrink: 0; }
  .color-swatch { width: 18px; height: 18px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; padding: 0; flex-shrink: 0; }
  .color-swatch--sm { width: 14px; height: 14px; }
  .color-swatch--active { border-color: var(--sc-text); }
  .list { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; max-width: 600px; }
  .label-row { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--sc-border); }
  .label-row:last-child { border-bottom: none; }
  .label-row:hover { background: var(--sc-surface-2); }
  .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .label-name { font-size: 13px; font-weight: 600; color: var(--sc-text); flex: 1; }
  .label-slug { font-size: 12px; color: var(--sc-text-muted); }
  .row-actions { display: flex; gap: 4px; opacity: 0; transition: opacity .15s; }
  .label-row:hover .row-actions { opacity: 1; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon:hover { color: var(--sc-accent); background: rgba(var(--sc-accent-rgb), .1); }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .btn-sm { padding: 4px 10px; border-radius: var(--sc-radius); font-size: 12px; font-weight: 600; border: none; cursor: pointer; background: var(--sc-accent); color: #fff; }
  .btn-sm--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn { padding: 8px 14px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; flex-shrink: 0; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
</style>
