<script>
  import AdminShell    from '$lib/components/layout/AdminShell.svelte';
  import StatusBadge   from '$lib/components/common/StatusBadge.svelte';
  import Pagination    from '$lib/components/common/Pagination.svelte';
  import EmptyState    from '$lib/components/common/EmptyState.svelte';
  import CompassPanel  from '$lib/components/compass/CompassPanel.svelte';
  import { goto }      from '$app/navigation';
  import { page }      from '$app/stores';
  import api           from '$lib/api.js';
  import { onMount }   from 'svelte';
  import { formatDate } from '$lib/utils/formatDate.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import Select from '$lib/components/common/Select.svelte';

  const STATUS_FILTER_OPTS = [
    { value: '', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
  ];

  let slug       = $derived($page.params.slug);
  let collection = $state(null);
  let items      = $state([]);
  let meta       = $state({ total: 0, page: 1, per_page: 20, total_pages: 1 });
  let loading    = $state(true);
  let notFound   = $state(false);

  // Filters
  let filterStatus = $state('');
  let filterQ      = $state('');
  let currentPage  = $state(1);

  // Compass
  let compassOpen    = $state(false);
  let compassFilters = $state({});

  // Derived: any active compass filter (excluding status which is in toolbar)
  let compassActiveCount = $derived(
    Object.entries(compassFilters)
      .filter(([k, v]) => k !== 'status' && v !== '' && v !== null && !(Array.isArray(v) && v.length === 0))
      .length
  );

  // Bulk select
  let selected   = $state(new Set());
  let allChecked = $derived(items.length > 0 && items.every(i => selected.has(i.id)));

  onMount(() => loadCollection());

  $effect(() => {
    void slug;
    collection = null; items = []; loading = true; notFound = false;
    compassFilters = {};
    loadCollection();
  });

  async function loadCollection() {
    try {
      const allRes = await api.get('collections');
      const c = (allRes.data ?? []).find(c => c.slug === slug);
      if (!c) { notFound = true; loading = false; return; }
      collection = c;
      await loadItems(1);
    } catch (e) {
      notifications.error(e.message);
      loading = false;
    }
  }

  async function loadItems(p = currentPage) {
    if (!collection) return;
    loading = true;
    try {
      // Build query params: standard filters + compass custom field filters
      const params = {
        page:     p,
        per_page: 20,
        status:   compassFilters.status || filterStatus || undefined,
        q:        filterQ || undefined,
      };

      // Pass custom field filters (prefixed cf_) so backend can handle them
      for (const [key, val] of Object.entries(compassFilters)) {
        if (key === 'status') continue;
        if (val === '' || val === null) continue;
        if (Array.isArray(val) && val.length === 0) continue;
        params['cf_' + key] = Array.isArray(val) ? val.join(',') : val;
      }

      const res = await api.get(`collections/${collection.id}/items`, params);
      items       = res.data ?? [];
      meta        = res.meta ?? meta;
      currentPage = p;
      selected    = new Set();
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function toggleAll() {
    if (allChecked) selected = new Set();
    else selected = new Set(items.map(i => i.id));
  }

  function toggleOne(id) {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id); else s.add(id);
    selected = s;
  }

  async function bulkDelete() {
    if (!selected.size) return;
    if (!confirm(`Delete ${selected.size} item(s)? This cannot be undone.`)) return;
    try {
      await api.post(`collections/${collection.id}/items/bulk`, {
        ids: [...selected],
        action: 'delete',
      });
      notifications.success(`${selected.size} item(s) deleted.`);
      await loadItems(1);
    } catch (e) { notifications.error(e.message); }
  }

  async function bulkStatus(action) {
    if (!selected.size) return;
    try {
      await api.post(`collections/${collection.id}/items/bulk`, {
        ids: [...selected],
        action,
      });
      notifications.success(`${selected.size} item(s) updated.`);
      await loadItems(currentPage);
    } catch (e) { notifications.error(e.message); }
  }

  async function deleteItem(id, title) {
    if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;
    try {
      await api.delete(`collections/${collection.id}/items/${id}`);
      items = items.filter(i => i.id !== id);
      notifications.success(`"${title}" deleted.`);
    } catch (e) { notifications.error(e.message); }
  }

  let debounceTimer;
  function onSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadItems(1), 350);
  }

  function onCompassFilter() { loadItems(1); }
  function onCompassReset() { compassFilters = {}; loadItems(1); }
</script>

<AdminShell title={collection?.name ?? 'Loading…'}>
  {#snippet actions()}
    {#if collection}
      <button
        class="btn-ghost"
        class:btn-ghost--active={compassOpen || compassActiveCount > 0}
        onclick={() => compassOpen = !compassOpen}
      >
        ⊹ Filter{compassActiveCount > 0 ? ` (${compassActiveCount})` : ''}
      </button>
      <a href="/admin/collections/{slug}/schema" class="btn-ghost">Schema</a>
      <a href="/admin/collections/{slug}/new" class="btn-primary">+ New item</a>
    {/if}
  {/snippet}

  {#if notFound}
    <EmptyState icon="⚠" title="Collection not found" description={'No collection with slug "' + slug + '" exists.'} />
  {:else if !collection}
    <p class="loading">Loading…</p>
  {:else}
    <!-- Filters bar -->
    <div class="toolbar">
      <input class="search" type="search" placeholder="Search items…" bind:value={filterQ} oninput={onSearch} />
      <Select bind:value={filterStatus} options={STATUS_FILTER_OPTS} onchange={() => loadItems(1)} />

      {#if selected.size > 0}
        <div class="bulk-actions">
          <span class="bulk-label">{selected.size} selected</span>
          <button class="bulk-btn" onclick={() => bulkStatus('publish')}>Publish</button>
          <button class="bulk-btn" onclick={() => bulkStatus('draft')}>Draft</button>
          <button class="bulk-btn bulk-btn--danger" onclick={bulkDelete}>Delete</button>
        </div>
      {/if}
    </div>

    <!-- Active compass filter chips -->
    {#if compassActiveCount > 0}
      <div class="filter-chips">
        {#each Object.entries(compassFilters) as [key, val]}
          {#if key !== 'status' && val !== '' && val !== null && !(Array.isArray(val) && val.length === 0)}
            <span class="chip">
              {key}: {Array.isArray(val) ? val.join(', ') : val}
              <button class="chip-remove" onclick={() => { const f = {...compassFilters}; delete f[key]; compassFilters = f; onCompassFilter(); }}>✕</button>
            </span>
          {/if}
        {/each}
        <button class="chip-clear" onclick={onCompassReset}>Clear all</button>
      </div>
    {/if}

    {#if loading}
      <p class="loading">Loading…</p>
    {:else if !items.length}
      <EmptyState icon="⊞" title="No items yet" description="Create your first item in this collection.">
        {#snippet action()}<a href="/admin/collections/{slug}/new" class="btn-primary">+ New item</a>{/snippet}
      </EmptyState>
    {:else}
      <div class="card">
        <table class="table">
          <thead>
            <tr>
              <th class="cb-col"><input type="checkbox" checked={allChecked} onchange={toggleAll} /></th>
              <th>Title</th>
              <th>Status</th>
              <th>Author</th>
              <th>Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {#each items as item}
              <tr class:selected={selected.has(item.id)}>
                <td class="cb-col"><input type="checkbox" checked={selected.has(item.id)} onchange={() => toggleOne(item.id)} /></td>
                <td>
                  <a href="/admin/collections/{slug}/{item.id}" class="row-link">{item.title}</a>
                </td>
                <td><StatusBadge status={item.status} /></td>
                <td class="muted">{item.author_name ?? '—'}</td>
                <td class="muted">{formatDate(item.updated_at, 'relative')}</td>
                <td class="actions-cell">
                  <a href="/admin/collections/{slug}/{item.id}" class="action-btn">Edit</a>
                  <button class="action-btn action-btn--danger" onclick={() => deleteItem(item.id, item.title)}>Delete</button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <Pagination
        meta={{ ...meta, page: currentPage }}
        onpage={(p) => loadItems(p)}
      />
    {/if}
  {/if}
</AdminShell>

<!-- Compass sliding panel -->
{#if collection}
  <CompassPanel
    bind:open={compassOpen}
    fieldDefs={collection.fields ?? []}
    bind:filters={compassFilters}
    onfilter={onCompassFilter}
    onreset={onCompassReset}
  />
{/if}

<style>
  .loading { color: var(--sc-text-muted); }

  .toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
  .search { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 13.5px; width: 240px; }
  .search:focus { outline: none; border-color: var(--sc-accent); }
  .select { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 13.5px; cursor: pointer; }
  .select:focus { outline: none; border-color: var(--sc-accent); }

  .bulk-actions { display: flex; align-items: center; gap: 6px; margin-left: auto; }
  .bulk-label { font-size: 12.5px; color: var(--sc-text-muted); }
  .bulk-btn { font-size: 12px; padding: 5px 10px; border-radius: var(--sc-radius); border: 1px solid var(--sc-border); background: none; color: var(--sc-text-muted); cursor: pointer; }
  .bulk-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .bulk-btn--danger { color: var(--sc-danger); }
  .bulk-btn--danger:hover { border-color: var(--sc-danger); }

  .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; align-items: center; }
  .chip { display: flex; align-items: center; gap: 4px; background: rgba(var(--sc-accent-rgb), .12); border: 1px solid rgba(var(--sc-accent-rgb), .3); color: var(--sc-accent); border-radius: 20px; padding: 3px 10px; font-size: 12px; }
  .chip-remove { background: none; border: none; cursor: pointer; color: var(--sc-accent); opacity: .7; padding: 0 0 0 4px; font-size: 11px; line-height: 1; }
  .chip-remove:hover { opacity: 1; }
  .chip-clear { background: none; border: none; cursor: pointer; color: var(--sc-text-muted); font-size: 12px; padding: 3px 6px; }
  .chip-clear:hover { color: var(--sc-danger); }

  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  .table th { text-align: left; padding: 10px 14px; font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--sc-border); }
  .table td { padding: 12px 14px; border-bottom: 1px solid var(--sc-border); vertical-align: middle; }
  .table tbody tr:last-child td { border-bottom: none; }
  .table tbody tr:hover td { background: var(--sc-surface-2); }
  .table tbody tr.selected td { background: rgba(var(--sc-accent-rgb), .06); }
  .cb-col { width: 40px; }
  .muted { color: var(--sc-text-muted); font-size: 12.5px; }

  .row-link { color: var(--sc-text); font-weight: 500; text-decoration: none; }
  .row-link:hover { color: var(--sc-accent); }

  .actions-cell { display: flex; gap: 6px; align-items: center; }
  .action-btn { font-size: 12px; padding: 4px 10px; border-radius: var(--sc-radius); border: 1px solid var(--sc-border); background: none; color: var(--sc-text-muted); cursor: pointer; text-decoration: none; }
  .action-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .action-btn--danger { color: var(--sc-danger); border-color: transparent; }
  .action-btn--danger:hover { border-color: var(--sc-danger); }

  .btn-primary { display: inline-flex; align-items: center; padding: 8px 16px; background: var(--sc-accent); color: #fff; border-radius: var(--sc-radius); font-size: 13.5px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
  .btn-primary:hover { background: var(--sc-accent-hover); }
  .btn-ghost { display: inline-flex; align-items: center; padding: 8px 14px; border: 1px solid var(--sc-border); color: var(--sc-text-muted); border-radius: var(--sc-radius); font-size: 13.5px; text-decoration: none; background: none; cursor: pointer; }
  .btn-ghost:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .btn-ghost--active { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
