<script>
  import { onMount } from 'svelte';
  import api from '$lib/api.js';
  import Modal from '$lib/components/common/Modal.svelte';

  let { value = $bindable(null), label = '', required = false } = $props();

  let selected  = $state(null);  // full media object for the current value
  let open      = $state(false);
  let mediaList = $state([]);
  let loading   = $state(false);
  let search    = $state('');

  // Load full details for the currently-selected media ID
  $effect(() => {
    if (value && (!selected || selected.id !== value)) {
      api.get(`media/${value}`).then(r => { selected = r.data; }).catch(() => { selected = null; });
    } else if (!value) {
      selected = null;
    }
  });

  async function openPicker() {
    open    = true;
    loading = true;
    try {
      mediaList = (await api.get('media', { per_page: 100 })).data ?? [];
    } catch { mediaList = []; }
    finally { loading = false; }
  }

  function pick(item) {
    value    = item.id;
    selected = item;
    open     = false;
  }

  function clear() { value = null; selected = null; }

  let filtered = $derived(
    search
      ? mediaList.filter(m => m.original_name?.toLowerCase().includes(search.toLowerCase()))
      : mediaList
  );

  function thumb(m) {
    return m.thumb_path ? `/storage/thumbnails/${m.thumb_path.split('/').pop()}` : null;
  }
</script>

<div class="field">
  {#if label}
    <span class="label">{label}{#if required}<span class="req"> *</span>{/if}</span>
  {/if}

  {#if selected}
    <div class="preview">
      {#if thumb(selected)}
        <img class="thumb" src={thumb(selected)} alt={selected.alt_text || selected.original_name} />
      {:else}
        <div class="file-icon">📄</div>
      {/if}
      <div class="info">
        <span class="name">{selected.original_name}</span>
        <span class="meta">{selected.mime_type}</span>
      </div>
      <button type="button" class="btn-change" onclick={openPicker}>Change</button>
      <button type="button" class="btn-clear"  onclick={clear}>✕</button>
    </div>
  {:else}
    <button type="button" class="pick-btn" onclick={openPicker}>
      <span>🖼</span> Pick media
    </button>
  {/if}
</div>

<Modal {open} title="Media Library" onclose={() => open = false}>
  <div class="modal-body">
    <input class="search" type="search" placeholder="Search…" bind:value={search} />
    {#if loading}
      <p class="hint">Loading…</p>
    {:else if !filtered.length}
      <p class="hint">No media found.</p>
    {:else}
      <div class="grid">
        {#each filtered as m}
          <button type="button" class="card" class:selected={m.id === value} onclick={() => pick(m)}>
            {#if thumb(m)}
              <img src={thumb(m)} alt={m.original_name} />
            {:else}
              <div class="file-placeholder">📄</div>
            {/if}
            <span class="card-name">{m.original_name}</span>
          </button>
        {/each}
      </div>
    {/if}
  </div>
</Modal>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }

  .preview { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--sc-border); border-radius: var(--sc-radius); background: var(--sc-surface-2); }
  .thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
  .file-icon { font-size: 32px; width: 48px; text-align: center; flex-shrink: 0; }
  .info { flex: 1; min-width: 0; }
  .name { display: block; font-size: 13px; font-weight: 500; color: var(--sc-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .meta { font-size: 11px; color: var(--sc-text-muted); }
  .btn-change { font-size: 12px; padding: 4px 10px; border: 1px solid var(--sc-border); border-radius: var(--sc-radius); background: none; color: var(--sc-text-muted); cursor: pointer; }
  .btn-change:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .btn-clear { font-size: 13px; padding: 4px 8px; border: none; background: none; color: var(--sc-text-muted); cursor: pointer; }
  .btn-clear:hover { color: var(--sc-danger); }

  .pick-btn { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border: 2px dashed var(--sc-border); border-radius: var(--sc-radius); background: none; color: var(--sc-text-muted); cursor: pointer; font-size: 14px; width: 100%; transition: border-color .15s, color .15s; }
  .pick-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }

  .modal-body { display: flex; flex-direction: column; gap: 14px; }
  .search { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 14px; width: 100%; }
  .search:focus { outline: none; border-color: var(--sc-accent); }
  .hint { color: var(--sc-text-muted); font-size: 13px; text-align: center; padding: 20px 0; }

  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; max-height: 400px; overflow-y: auto; }
  .card { background: var(--sc-surface-2); border: 2px solid transparent; border-radius: var(--sc-radius); padding: 8px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 6px; transition: border-color .15s; }
  .card:hover { border-color: var(--sc-accent); }
  .card.selected { border-color: var(--sc-accent); background: rgba(var(--sc-accent-rgb), .1); }
  .card img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 4px; }
  .file-placeholder { font-size: 40px; height: 80px; display: flex; align-items: center; justify-content: center; }
  .card-name { font-size: 11px; color: var(--sc-text-muted); text-align: center; word-break: break-all; line-height: 1.3; }
</style>
