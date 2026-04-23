<script>
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import StatusBadge from '$lib/components/common/StatusBadge.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  const TYPE_LABELS = {
    collection_item: 'Item',
    page: 'Page',
    media: 'Media',
  };

  let q        = $state('');
  let types    = $state({ collection_item: true, page: true, media: true });
  let results  = $state([]);
  let loading  = $state(false);
  let searched = $state(false);
  let timer;

  // Pick up ?q= from URL on load
  onMount(() => {
    const urlQ = $page.url.searchParams.get('q') ?? '';
    if (urlQ) { q = urlQ; doSearch(); }
  });

  function handleInput() {
    clearTimeout(timer);
    timer = setTimeout(doSearch, 400);
  }

  async function doSearch() {
    if (!q.trim()) { results = []; searched = false; return; }
    loading = true;
    searched = true;
    try {
      const enabledTypes = Object.entries(types).filter(([,v]) => v).map(([k]) => k);
      const res = await api.get('search', { q: q.trim(), types: enabledTypes.join(',') });
      results = res.data ?? [];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function resultHref(r) {
    if (r.entity_type === 'page')            return `/pages/${r.entity_id}`;
    if (r.entity_type === 'collection_item') return `#`;  // needs collection slug
    if (r.entity_type === 'media')           return `/media`;
    return '#';
  }
</script>

<AdminShell title="Search">
  {#snippet children()}
    <div class="search-wrap">
      <input
        class="search-input"
        type="search"
        bind:value={q}
        oninput={handleInput}
        placeholder="Search everything…"
        autofocus
      />

      <div class="type-filters">
        {#each Object.entries(TYPE_LABELS) as [key, label]}
          <label class="filter-toggle">
            <input type="checkbox" bind:checked={types[key]} onchange={doSearch} />
            {label}
          </label>
        {/each}
      </div>
    </div>

    {#if loading}
      <p class="muted">Searching…</p>
    {:else if searched && results.length === 0}
      <p class="muted">No results for "{q}"</p>
    {:else if results.length > 0}
      <div class="results">
        {#each results as r (r.entity_type + '_' + r.entity_id)}
          <a href={resultHref(r)} class="result-row">
            <span class="type-badge">{TYPE_LABELS[r.entity_type] ?? r.entity_type}</span>
            <div class="result-info">
              <span class="result-title">{r.title}</span>
              {#if r.meta}<span class="result-meta">{r.meta}</span>{/if}
            </div>
          </a>
        {/each}
      </div>
    {/if}
  {/snippet}
</AdminShell>

<style>
  .search-wrap { margin-bottom: 20px; }
  .search-input {
    width: 100%;
    max-width: 600px;
    padding: 12px 16px;
    background: var(--sc-surface);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius-lg);
    color: var(--sc-text);
    font-size: 16px;
    outline: none;
    display: block;
    margin-bottom: 14px;
  }
  .search-input:focus { border-color: var(--sc-accent); }
  .search-input::-webkit-search-cancel-button { display: none; }
  .type-filters { display: flex; gap: 14px; }
  .filter-toggle { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--sc-text-muted); cursor: pointer; user-select: none; }
  .filter-toggle input { accent-color: var(--sc-accent); cursor: pointer; }
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .results { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; max-width: 700px; }
  .result-row { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--sc-border); text-decoration: none; transition: background .1s; }
  .result-row:last-child { border-bottom: none; }
  .result-row:hover { background: var(--sc-surface-2); }
  .type-badge { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-accent); background: rgba(var(--sc-accent-rgb), .12); padding: 3px 8px; border-radius: 20px; flex-shrink: 0; }
  .result-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
  .result-title { font-size: 14px; font-weight: 600; color: var(--sc-text); }
  .result-meta { font-size: 12px; color: var(--sc-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
