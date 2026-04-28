<script>
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { onMount, onDestroy } from 'svelte';
  import { api } from '$lib/api.js';
  import { userStore } from '$lib/stores/user.svelte.js';
  import FieldEditor from '$lib/components/builder/FieldEditor.svelte';

  // ── State ─────────────────────────────────────────────────────────────────

  let pageId     = $derived(parseInt($page.params.id));
  let pageData   = $state(null);
  let loading    = $state(true);
  let notFound   = $state(false);
  let saving     = $state(false);
  let device     = $state('desktop'); // 'desktop' | 'tablet' | 'mobile'

  // Block interaction state
  let selectedBlockIndex = $state(null);
  let blocks             = $state([]);   // mutable copy of pageData.blocks
  let blockSchemas       = $state({});   // map of block type → {name, icon, fields}

  // Iframe ref
  let iframeEl = $state(null);

  const DEVICES = [
    { id: 'desktop', label: 'Desktop', width: '100%'  },
    { id: 'tablet',  label: 'Tablet',  width: '768px' },
    { id: 'mobile',  label: 'Mobile',  width: '375px' },
  ];

  // Preview URL: page at /{slug}?_sc_preview=1
  let previewUrl = $derived(
    pageData ? `${window.location.origin}/${pageData.slug}?_sc_preview=1` : ''
  );

  let iframeWidth = $derived(
    DEVICES.find(d => d.id === device)?.width ?? '100%'
  );

  let hasBlocks = $derived(blocks.length > 0);

  // Currently selected block's schema fields
  let selectedSchema = $derived.by(() => {
    if (selectedBlockIndex === null) return null;
    const block = blocks[selectedBlockIndex];
    if (!block) return null;
    return blockSchemas[block.type] ?? null;
  });

  // Debounce timer ref
  let saveTimer = null;

  // ── Auth guard ────────────────────────────────────────────────────────────

  onMount(() => {
    if (!userStore.isLoggedIn) {
      goto('/admin/login');
      return;
    }
    load();
    window.addEventListener('message', onIframeMessage);
  });

  onDestroy(() => {
    window.removeEventListener('message', onIframeMessage);
    if (saveTimer) clearTimeout(saveTimer);
  });

  $effect(() => {
    void pageId;
    if (userStore.isLoggedIn) load();
  });

  // ── Load page + block schemas ─────────────────────────────────────────────

  async function load() {
    loading  = true;
    notFound = false;
    selectedBlockIndex = null;
    try {
      const [pageRes, themeRes] = await Promise.all([
        api.get(`pages/${pageId}`),
        api.get('theme/blocks').catch(() => ({ data: [] })),
      ]);

      if (!pageRes.data) { notFound = true; return; }
      pageData = pageRes.data;

      // Mutable local blocks copy
      blocks = Array.isArray(pageData.blocks) ? structuredClone(pageData.blocks) : [];

      // Build blockSchemas map from theme API
      const schemaMap = {};
      for (const b of (themeRes.data ?? [])) {
        schemaMap[b.type] = b;
      }
      blockSchemas = schemaMap;

    } catch (e) {
      if (e.status === 404) notFound = true;
    } finally {
      loading = false;
    }
  }

  // ── Select a block ────────────────────────────────────────────────────────

  function selectBlock(index) {
    selectedBlockIndex = index;
    // Highlight in iframe
    postToIframe({ type: 'block:highlight', blockIndex: index });
  }

  function deselectBlock() {
    selectedBlockIndex = null;
    postToIframe({ type: 'block:unhighlight' });
  }

  // ── postMessage helpers ───────────────────────────────────────────────────

  function postToIframe(msg) {
    if (iframeEl?.contentWindow) {
      iframeEl.contentWindow.postMessage(msg, '*');
    }
  }

  // Messages from the iframe (PreviewBridge)
  function onIframeMessage(e) {
    const msg = e.data;
    if (!msg || typeof msg !== 'object') return;

    switch (msg.type) {
      case 'block:select':
        selectBlock(msg.blockIndex);
        break;
      case 'block:hover':
        postToIframe({ type: 'block:highlight', blockIndex: msg.blockIndex });
        break;
      case 'block:unhover':
        // Only remove if it's not the selected block
        if (msg.blockIndex !== selectedBlockIndex) {
          postToIframe({ type: 'block:unhighlight' });
          if (selectedBlockIndex !== null) {
            postToIframe({ type: 'block:highlight', blockIndex: selectedBlockIndex });
          }
        }
        break;
    }
  }

  // ── Field change handler ──────────────────────────────────────────────────

  // "Simple" types that support DOM injection (no reload needed)
  const INJECTION_TYPES = new Set(['text', 'textarea', 'number', 'toggle', 'color', 'select']);
  // "Heavy" types that require full iframe reload
  const RELOAD_TYPES    = new Set(['richtext', 'media', 'code', 'repeater', 'relation']);

  function onFieldChange(fieldName, value) {
    if (selectedBlockIndex === null) return;

    // Update local state
    blocks[selectedBlockIndex] = {
      ...blocks[selectedBlockIndex],
      data: {
        ...(blocks[selectedBlockIndex].data ?? {}),
        [fieldName]: value,
      },
    };

    // Determine field type from schema
    const schema   = selectedSchema;
    const fieldDef = schema?.fields?.find(f => f.name === fieldName);
    const fType    = fieldDef?.type ?? 'text';

    if (INJECTION_TYPES.has(fType)) {
      // DOM injection — no reload
      postToIframe({
        type:       'field:update',
        blockIndex: selectedBlockIndex,
        field:      fieldName,
        value:      String(value ?? ''),
      });
    }

    // Always debounce-save (regardless of injection vs reload)
    scheduleSave(RELOAD_TYPES.has(fType));
  }

  // ── Debounced save ────────────────────────────────────────────────────────

  function scheduleSave(reloadAfter = false) {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      saveBlocks(reloadAfter);
    }, 500);
  }

  async function saveBlocks(reloadAfter = false) {
    if (!pageData) return;
    saving = true;
    try {
      await api.put(`pages/${pageId}`, { blocks });
      if (reloadAfter && iframeEl) {
        // Full reload for richtext/media changes
        iframeEl.src = previewUrl;
      }
    } catch (_) {
      // Fail silently — builder edits are soft-saved
    } finally {
      saving = false;
    }
  }

  // ── Iframe load — re-apply highlight after reload ─────────────────────────

  function onIframeLoad() {
    if (selectedBlockIndex !== null) {
      // Brief delay to let PreviewBridge initialize
      setTimeout(() => {
        postToIframe({ type: 'block:highlight', blockIndex: selectedBlockIndex });
      }, 80);
    }
  }
</script>

<div class="builder">

  <!-- ── Top bar ──────────────────────────────────────────────────────────── -->
  <header class="topbar">
    <div class="topbar__left">
      <a href="/admin/pages" class="topbar__back" title="Back to Pages">←</a>
      <span class="topbar__sep">/</span>
      <span class="topbar__title">
        {#if loading}
          Loading…
        {:else if notFound}
          Page not found
        {:else}
          {pageData?.title ?? ''}
        {/if}
      </span>
      <span class="topbar__label">Builder</span>
    </div>

    <div class="topbar__center">
      {#each DEVICES as d}
        <button
          class="topbar__device-btn"
          class:topbar__device-btn--active={device === d.id}
          onclick={() => device = d.id}
        >{d.label}</button>
      {/each}
    </div>

    <div class="topbar__right">
      {#if saving}
        <span class="topbar__saving">Saving…</span>
      {/if}
      {#if pageData}
        <a
          href="/admin/pages/{pageId}"
          class="topbar__edit-link"
        >Edit page →</a>
      {/if}
    </div>
  </header>

  <!-- ── Main layout ──────────────────────────────────────────────────────── -->
  <div class="builder__body">

    <!-- Sidebar -->
    <aside class="sidebar">

      {#if loading}
        <div class="sidebar__header">
          <span class="sidebar__label">Blocks</span>
        </div>
        <div class="sidebar__state">
          <span class="sidebar__hint">Loading…</span>
        </div>

      {:else if notFound}
        <div class="sidebar__header">
          <span class="sidebar__label">Blocks</span>
        </div>
        <div class="sidebar__state">
          <span class="sidebar__hint">Page not found.</span>
        </div>

      {:else if !hasBlocks}
        <div class="sidebar__header">
          <span class="sidebar__label">Blocks</span>
        </div>
        <div class="sidebar__state sidebar__state--empty">
          <p class="sidebar__empty-title">No blocks yet</p>
          <p class="sidebar__empty-hint">Add one to get started.</p>
        </div>

      {:else}
        <!-- Block list -->
        <div class="sidebar__header">
          <span class="sidebar__label">Blocks</span>
        </div>
        <ul class="block-list">
          {#each blocks as block, i}
            <li class="block-row" class:block-row--selected={selectedBlockIndex === i}>
              <button
                class="block-row__btn"
                onclick={() => selectedBlockIndex === i ? deselectBlock() : selectBlock(i)}
              >
                <span class="block-row__index">{i + 1}</span>
                <span class="block-row__type">{blockSchemas[block.type]?.name ?? block.type}</span>
                {#if selectedBlockIndex === i}
                  <span class="block-row__chevron">›</span>
                {/if}
              </button>
            </li>
          {/each}
        </ul>

        <!-- Field editors for selected block -->
        {#if selectedBlockIndex !== null && selectedSchema}
          <div class="field-panel">
            <div class="field-panel__header">
              <span class="field-panel__title">{selectedSchema.name ?? blocks[selectedBlockIndex]?.type}</span>
              <button class="field-panel__close" onclick={deselectBlock} title="Close">✕</button>
            </div>
            <div class="field-panel__fields">
              {#if selectedSchema.fields?.length > 0}
                {#each selectedSchema.fields as fieldDef}
                  <FieldEditor
                    {fieldDef}
                    value={blocks[selectedBlockIndex]?.data?.[fieldDef.name] ?? ''}
                    onchange={(val) => onFieldChange(fieldDef.name, val)}
                  />
                {/each}
              {:else}
                <p class="field-panel__hint">This block has no editable fields.</p>
              {/if}
            </div>
          </div>
        {:else if selectedBlockIndex !== null}
          <div class="field-panel">
            <div class="field-panel__header">
              <span class="field-panel__title">{blocks[selectedBlockIndex]?.type}</span>
              <button class="field-panel__close" onclick={deselectBlock} title="Close">✕</button>
            </div>
            <div class="field-panel__fields">
              <p class="field-panel__hint">No schema found for this block.</p>
            </div>
          </div>
        {/if}
      {/if}

    </aside>

    <!-- Canvas -->
    <div class="canvas">
      {#if loading}
        <div class="canvas__placeholder">
          <span class="canvas__hint">Loading preview…</span>
        </div>
      {:else if notFound}
        <div class="canvas__placeholder">
          <span class="canvas__hint">Page not found.</span>
        </div>
      {:else if !hasBlocks}
        <div class="canvas__placeholder canvas__placeholder--empty">
          <p class="canvas__empty-title">No blocks yet — add one to get started.</p>
        </div>
      {:else}
        <div class="canvas__frame-wrap" style="max-width: {iframeWidth};">
          <iframe
            bind:this={iframeEl}
            class="canvas__iframe"
            src={previewUrl}
            title="Page preview"
            sandbox="allow-same-origin allow-scripts"
            onload={onIframeLoad}
          ></iframe>
        </div>
      {/if}
    </div>

  </div>
</div>

<style>
  /* ── Reset & shell ───────────────────────────────────────────────────────── */
  .builder {
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
    background: var(--sc-bg, #f9f9f7);
    color: var(--sc-text, #1a1a1a);
    font-family: var(--sc-font, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif);
  }

  /* ── Top bar ─────────────────────────────────────────────────────────────── */
  .topbar {
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    background: var(--sc-surface, #fff);
    border-bottom: 1px solid var(--sc-border, #e8e8e6);
    flex-shrink: 0;
    gap: 12px;
  }

  .topbar__left {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    flex: 1;
  }

  .topbar__back {
    color: var(--sc-text-muted, #888);
    text-decoration: none;
    font-size: 16px;
    line-height: 1;
    padding: 4px 6px;
    border-radius: 4px;
  }
  .topbar__back:hover { background: var(--sc-surface-2, #f5f5f3); }

  .topbar__sep {
    font-size: 12px;
    color: var(--sc-text-dim, #bbb);
    font-family: var(--sc-font-mono, monospace);
  }

  .topbar__title {
    font-size: 13px;
    font-weight: 600;
    color: var(--sc-text, #1a1a1a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .topbar__label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sc-text-muted, #888);
    flex-shrink: 0;
  }

  .topbar__center {
    display: flex;
    align-items: center;
    gap: 2px;
    background: var(--sc-surface-2, #f5f5f3);
    border: 1px solid var(--sc-border, #e8e8e6);
    border-radius: 6px;
    padding: 2px;
    flex-shrink: 0;
  }

  .topbar__device-btn {
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 500;
    color: var(--sc-text-muted, #888);
    background: none;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
  .topbar__device-btn:hover { color: var(--sc-text, #1a1a1a); }
  .topbar__device-btn--active {
    background: var(--sc-surface, #fff);
    color: var(--sc-text, #1a1a1a);
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  }

  .topbar__right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    justify-content: flex-end;
  }

  .topbar__saving {
    font-size: 12px;
    color: var(--sc-text-muted, #888);
  }

  .topbar__edit-link {
    font-size: 12px;
    color: var(--sc-text-muted, #888);
    text-decoration: none;
    white-space: nowrap;
  }
  .topbar__edit-link:hover { color: var(--sc-accent, #4f46e5); text-decoration: underline; }

  /* ── Body layout ─────────────────────────────────────────────────────────── */
  .builder__body {
    flex: 1;
    display: flex;
    overflow: hidden;
  }

  /* ── Sidebar ─────────────────────────────────────────────────────────────── */
  .sidebar {
    width: 240px;
    flex-shrink: 0;
    background: var(--sc-surface, #fff);
    border-right: 1px solid var(--sc-border, #e8e8e6);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }

  .sidebar__header {
    padding: 14px 16px 8px;
    border-bottom: 1px solid var(--sc-border, #e8e8e6);
    flex-shrink: 0;
  }

  .sidebar__label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sc-text-muted, #888);
  }

  .sidebar__state {
    padding: 20px 16px;
  }

  .sidebar__state--empty {
    text-align: center;
    padding: 32px 20px;
  }

  .sidebar__hint {
    font-size: 12px;
    color: var(--sc-text-muted, #888);
  }

  .sidebar__empty-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--sc-text, #1a1a1a);
    margin: 0 0 6px;
  }

  .sidebar__empty-hint {
    font-size: 12px;
    color: var(--sc-text-muted, #888);
    margin: 0;
  }

  /* ── Block list ──────────────────────────────────────────────────────────── */
  .block-list {
    list-style: none;
    padding: 8px 0;
    margin: 0;
    flex-shrink: 0;
  }

  .block-row {
    border-left: 2px solid transparent;
    transition: background 0.1s, border-color 0.1s;
  }
  .block-row:hover { background: var(--sc-surface-2, #f5f5f3); }
  .block-row--selected {
    background: var(--sc-surface-2, #f5f5f3);
    border-left-color: var(--sc-accent, #4f46e5);
  }

  .block-row__btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    width: 100%;
    cursor: pointer;
    background: none;
    border: none;
    text-align: left;
  }

  .block-row__index {
    font-size: 11px;
    color: var(--sc-text-dim, #bbb);
    font-family: var(--sc-font-mono, monospace);
    min-width: 16px;
    text-align: right;
    flex-shrink: 0;
  }

  .block-row__type {
    font-size: 13px;
    color: var(--sc-text, #1a1a1a);
    font-weight: 500;
    flex: 1;
  }

  .block-row__chevron {
    font-size: 14px;
    color: var(--sc-text-muted, #888);
  }

  /* ── Field panel ─────────────────────────────────────────────────────────── */
  .field-panel {
    border-top: 1px solid var(--sc-border, #e8e8e6);
    display: flex;
    flex-direction: column;
  }

  .field-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px 8px;
    border-bottom: 1px solid var(--sc-border, #e8e8e6);
  }

  .field-panel__title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sc-text-muted, #888);
  }

  .field-panel__close {
    background: none;
    border: none;
    font-size: 12px;
    color: var(--sc-text-dim, #bbb);
    cursor: pointer;
    padding: 2px 4px;
    line-height: 1;
    border-radius: 3px;
  }
  .field-panel__close:hover { color: var(--sc-text, #1a1a1a); background: var(--sc-surface-2, #f5f5f3); }

  .field-panel__fields {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .field-panel__hint {
    font-size: 12px;
    color: var(--sc-text-muted, #888);
    margin: 0;
  }

  /* ── Canvas ──────────────────────────────────────────────────────────────── */
  .canvas {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--sc-bg, #f2f2f0);
    overflow-y: auto;
    padding: 24px;
    gap: 0;
  }

  .canvas__placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    text-align: center;
    width: 100%;
  }

  .canvas__placeholder--empty {
    max-width: 360px;
    margin: auto;
  }

  .canvas__hint {
    font-size: 13px;
    color: var(--sc-text-muted, #888);
  }

  .canvas__empty-title {
    font-size: 15px;
    font-weight: 500;
    color: var(--sc-text-muted, #888);
    margin: 0;
  }

  .canvas__frame-wrap {
    width: 100%;
    transition: max-width 0.25s ease;
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 2px 16px rgba(0,0,0,0.08);
    height: 100%;
  }

  .canvas__iframe {
    width: 100%;
    flex: 1;
    border: none;
    display: block;
  }
</style>
