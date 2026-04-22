<script>
  import { onMount } from 'svelte';
  import MediaCard from './MediaCard.svelte';
  import MediaUploader from './MediaUploader.svelte';
  import SearchBar from '$lib/components/common/SearchBar.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import Pagination from '$lib/components/common/Pagination.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  let {
    mode    = 'library',   // 'library' | 'picker'
    onpick  = null,
    onclose = null,
    open    = true,
  } = $props();

  let items       = $state([]);
  let loading     = $state(false);
  let folders     = $state([]);
  let folderId    = $state(null);
  let q           = $state('');
  let page        = $state(1);
  let total       = $state(0);
  let perPage     = 40;
  let showUpload  = $state(false);
  let confirmItem = $state(null);

  const isPicker = mode === 'picker';

  onMount(() => {
    loadFolders();
    loadMedia();
  });

  async function loadFolders() {
    try {
      const res = await api.get('folders');
      folders = res.data ?? [];
    } catch { /* non-critical */ }
  }

  async function loadMedia() {
    loading = true;
    try {
      const params = { page, per_page: perPage };
      if (q)        params.q = q;
      if (folderId) params.folder_id = folderId;
      const res = await api.get('media', params);
      items = res.data ?? [];
      total = res.meta?.total ?? 0;
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function handleSearch(val) { q = val; page = 1; loadMedia(); }
  function handleFolder(id)  { folderId = id; page = 1; loadMedia(); }
  function handlePage(p)     { page = p; loadMedia(); }

  function handleUploaded(newItems) {
    showUpload = false;
    page = 1;
    loadMedia();
  }

  function handlePick(item) {
    if (isPicker && onpick) onpick(item);
  }

  function askDelete(item) { confirmItem = item; }

  async function confirmDelete() {
    const item = confirmItem;
    confirmItem = null;
    try {
      await api.delete(`media/${item.id}`);
      notifications.success('File deleted');
      loadMedia();
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<div class="lib" class:lib--picker={isPicker}>
  <!-- Sidebar -->
  <aside class="lib__sidebar">
    <button
      class="folder-item"
      class:folder-item--active={folderId === null}
      onclick={() => handleFolder(null)}
    >All files</button>
    {#each folders as f}
      <button
        class="folder-item"
        class:folder-item--active={folderId === f.id}
        onclick={() => handleFolder(f.id)}
      >{f.name}</button>
    {/each}
  </aside>

  <!-- Main -->
  <div class="lib__main">
    <div class="lib__toolbar">
      <SearchBar value={q} onchange={handleSearch} placeholder="Search files…" />
      <div class="lib__toolbar-right">
        {#if !isPicker}
          <button class="btn btn--primary" onclick={() => showUpload = !showUpload}>
            {showUpload ? 'Close uploader' : '+ Upload'}
          </button>
        {/if}
        {#if onclose}
          <button class="btn btn--ghost" onclick={onclose}>Close</button>
        {/if}
      </div>
    </div>

    {#if showUpload}
      <div class="lib__upload">
        <MediaUploader {folderId} onuploaded={handleUploaded} />
      </div>
    {/if}

    {#if loading}
      <p class="lib__empty">Loading…</p>
    {:else if items.length === 0}
      <p class="lib__empty">No files found.</p>
    {:else}
      <div class="lib__grid">
        {#each items as item (item.id)}
          <MediaCard
            {item}
            onclick={isPicker ? handlePick : null}
            ondelete={!isPicker ? askDelete : null}
          />
        {/each}
      </div>
      <Pagination {page} {total} {perPage} onpage={handlePage} />
    {/if}
  </div>
</div>

<ConfirmDialog
  open={!!confirmItem}
  title="Delete file"
  message="This will permanently delete the file and its thumbnail. This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => confirmItem = null}
/>

<style>
  .lib {
    display: flex;
    height: 100%;
    min-height: 400px;
  }

  .lib__sidebar {
    width: 180px;
    flex-shrink: 0;
    border-right: 1px solid var(--sc-border);
    padding: 12px 0;
    overflow-y: auto;
  }

  .folder-item {
    display: block;
    width: 100%;
    padding: 7px 16px;
    background: none;
    border: none;
    text-align: left;
    font-size: 13px;
    color: var(--sc-text-muted);
    cursor: pointer;
  }
  .folder-item:hover { color: var(--sc-text); background: var(--sc-surface-2); }
  .folder-item--active { color: var(--sc-accent); font-weight: 600; }

  .lib__main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 16px;
    gap: 16px;
    overflow-y: auto;
  }

  .lib__toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .lib__toolbar-right {
    margin-left: auto;
    display: flex;
    gap: 8px;
  }

  .lib__upload { flex-shrink: 0; }

  .lib__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
  }

  .lib__empty {
    color: var(--sc-text-muted);
    font-size: 13px;
    text-align: center;
    padding: 40px 0;
    margin: 0;
  }

  .btn {
    padding: 7px 14px;
    border-radius: var(--sc-radius);
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
  }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover { background: var(--sc-accent-hover); }
  .btn--ghost {
    background: transparent;
    border: 1px solid var(--sc-border);
    color: var(--sc-text-muted);
  }
  .btn--ghost:hover { color: var(--sc-text); }
</style>
