<script>
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { api } from '$lib/api.js';
  import { userStore } from '$lib/stores/user.svelte.js';

  // ── State ─────────────────────────────────────────────────────────────────

  let pageId     = $derived(parseInt($page.params.id));
  let pageData   = $state(null);
  let loading    = $state(true);
  let notFound   = $state(false);
  let device     = $state('desktop'); // 'desktop' | 'tablet' | 'mobile'

  const DEVICES = [
    { id: 'desktop', label: 'Desktop', width: '100%'  },
    { id: 'tablet',  label: 'Tablet',  width: '768px' },
    { id: 'mobile',  label: 'Mobile',  width: '375px' },
  ];

  // Preview URL: use window.location.origin to construct the URL.
  // The page is at /{slug}?_sc_preview=1
  let previewUrl = $derived(
    pageData ? `${window.location.origin}/${pageData.slug}?_sc_preview=1` : ''
  );

  let iframeWidth = $derived(
    DEVICES.find(d => d.id === device)?.width ?? '100%'
  );

  let blocks = $derived(
    Array.isArray(pageData?.blocks) ? pageData.blocks : []
  );

  // ── Auth guard ────────────────────────────────────────────────────────────

  onMount(() => {
    if (!userStore.isLoggedIn) {
      goto('/admin/login');
      return;
    }
    load();
  });

  $effect(() => {
    void pageId;
    if (userStore.isLoggedIn) load();
  });

  // ── Load page ─────────────────────────────────────────────────────────────

  async function load() {
    loading  = true;
    notFound = false;
    try {
      const res = await api.get(`pages/${pageId}`);
      if (!res.data) { notFound = true; return; }
      pageData = res.data;
    } catch (e) {
      if (e.status === 404) notFound = true;
    } finally {
      loading = false;
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
      <div class="sidebar__header">
        <span class="sidebar__label">Blocks</span>
      </div>

      {#if loading}
        <div class="sidebar__state">
          <span class="sidebar__hint">Loading…</span>
        </div>
      {:else if notFound}
        <div class="sidebar__state">
          <span class="sidebar__hint">Page not found.</span>
        </div>
      {:else if blocks.length === 0}
        <div class="sidebar__state sidebar__state--empty">
          <p class="sidebar__empty-title">No blocks yet</p>
          <p class="sidebar__empty-hint">Add one to get started.</p>
        </div>
      {:else}
        <ul class="block-list">
          {#each blocks as block, i}
            <li class="block-row">
              <span class="block-row__index">{i + 1}</span>
              <span class="block-row__type">{block.type}</span>
            </li>
          {/each}
        </ul>
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
      {:else if blocks.length === 0}
        <div class="canvas__placeholder canvas__placeholder--empty">
          <p class="canvas__empty-title">No blocks yet — add one to get started.</p>
        </div>
      {:else}
        <div class="canvas__frame-wrap" style="max-width: {iframeWidth};">
          <iframe
            class="canvas__iframe"
            src={previewUrl}
            title="Page preview"
            sandbox="allow-same-origin allow-scripts"
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

  .block-list {
    list-style: none;
    padding: 8px 0;
    margin: 0;
  }

  .block-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    cursor: default;
  }
  .block-row:hover { background: var(--sc-surface-2, #f5f5f3); }

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
