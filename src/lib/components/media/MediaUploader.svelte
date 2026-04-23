<script>
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  let {
    folderId   = null,
    onuploaded = null,
    multiple   = true,
  } = $props();

  let dragging   = $state(false);
  let uploading  = $state(false);
  let progress   = $state([]);  // [{name, done, error}]
  let inputEl;

  function openPicker() { inputEl?.click(); }

  function handleDragover(e) { e.preventDefault(); dragging = true; }
  function handleDragleave()  { dragging = false; }
  function handleDrop(e) {
    e.preventDefault();
    dragging = false;
    const files = [...(e.dataTransfer?.files ?? [])];
    if (files.length) uploadFiles(files);
  }

  function handleFileInput(e) {
    const files = [...(e.target?.files ?? [])];
    if (files.length) uploadFiles(files);
    e.target.value = '';
  }

  async function uploadFiles(files) {
    uploading = true;
    progress  = files.map(f => ({ name: f.name, done: false, error: null }));

    const results = [];
    for (let i = 0; i < files.length; i++) {
      try {
        const extra = folderId ? { folder_id: folderId } : {};
        const res = await api.upload(files[i], extra);
        results.push(res.data);
        progress = progress.map((p, j) => j === i ? { ...p, done: true } : p);
      } catch (e) {
        progress = progress.map((p, j) => j === i ? { ...p, error: e.message } : p);
        notifications.error(`Failed to upload ${files[i].name}: ${e.message}`);
      }
    }

    uploading = false;
    if (results.length && onuploaded) onuploaded(results);
    // Clear progress after brief delay
    setTimeout(() => { progress = []; }, 1500);
  }
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
  class="uploader"
  class:uploader--drag={dragging}
  ondragover={handleDragover}
  ondragleave={handleDragleave}
  ondrop={handleDrop}
>
  {#if uploading}
    <div class="uploader__progress">
      {#each progress as p}
        <div class="prog-item" class:prog-item--done={p.done} class:prog-item--err={p.error}>
          <span class="prog-name truncate">{p.name}</span>
          <span class="prog-status">
            {#if p.error}✕{:else if p.done}✓{:else}…{/if}
          </span>
        </div>
      {/each}
    </div>
  {:else}
    <button class="uploader__zone" type="button" onclick={openPicker}>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 3v13M8 7l4-4 4 4" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Drop files here or <strong>click to upload</strong></span>
      <span class="uploader__hint">JPEG, PNG, GIF, WebP, SVG — max 10 MB</span>
    </button>
  {/if}

  <input
    bind:this={inputEl}
    type="file"
    accept="image/*,.pdf,.svg"
    {multiple}
    class="sr-only"
    onchange={handleFileInput}
  />
</div>

<style>
  .uploader { position: relative; }

  .uploader__zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 32px 24px;
    background: var(--sc-surface-2);
    border: 2px dashed var(--sc-border);
    border-radius: var(--sc-radius-lg);
    color: var(--sc-text-muted);
    font-size: 13px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
  }
  .uploader__zone:hover,
  .uploader--drag .uploader__zone {
    border-color: var(--sc-accent);
    background: rgba(var(--sc-accent-rgb), .06);
    color: var(--sc-text);
  }
  .uploader__hint { font-size: 11px; color: var(--sc-text-muted); }

  .uploader__progress {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px;
    background: var(--sc-surface-2);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius-lg);
  }
  .prog-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--sc-text-muted);
  }
  .prog-item--done .prog-status { color: var(--sc-success); }
  .prog-item--err  .prog-status { color: var(--sc-danger); }
  .prog-name { flex: 1; min-width: 0; }
  .prog-status { flex-shrink: 0; font-weight: 700; }
</style>
