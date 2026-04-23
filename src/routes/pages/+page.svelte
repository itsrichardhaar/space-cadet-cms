<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import StatusBadge from '$lib/components/common/StatusBadge.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  let pages      = $state([]);
  let loading    = $state(true);
  let deleteItem = $state(null);

  onMount(loadPages);

  async function loadPages() {
    loading = true;
    try {
      const res = await api.get('pages');
      pages = res.data ?? [];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`pages/${item.id}`);
      pages = pages.filter(p => p.id !== item.id);
      notifications.success('Page deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  // Build a simple indented list from flat array with parent_id
  function sorted(list) {
    const roots = list.filter(p => !p.parent_id);
    const result = [];
    function walk(items, depth = 0) {
      for (const p of items) {
        result.push({ ...p, _depth: depth });
        const children = list.filter(c => c.parent_id === p.id);
        if (children.length) walk(children, depth + 1);
      }
    }
    walk(roots);
    return result;
  }
</script>

<AdminShell title="Pages">
  {#snippet actions()}
    <a href="/admin/pages/new" class="btn btn--primary">+ New Page</a>
  {/snippet}

  {#snippet children()}
    {#if loading}
      <p class="muted">Loading…</p>
    {:else if pages.length === 0}
      <EmptyState
        title="No pages yet"
        message="Create your first page to get started."
        action="New Page"
        onaction={() => goto('/admin/pages/new')}
      />
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Slug</th>
              <th>Status</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {#each sorted(pages) as page (page.id)}
              <tr>
                <td>
                  <a href="/admin/pages/{page.id}" class="item-link" style="padding-left: {page._depth * 20}px">
                    {#if page._depth > 0}<span class="tree-indent">↳ </span>{/if}
                    {page.title}
                  </a>
                </td>
                <td class="muted-cell">/{page.slug}</td>
                <td><StatusBadge status={page.status} /></td>
                <td class="muted-cell">{formatDate(page.updated_at)}</td>
                <td class="actions-cell">
                  <button class="btn-icon" onclick={() => deleteItem = page} title="Delete">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10"/>
                    </svg>
                  </button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  {/snippet}
</AdminShell>

<ConfirmDialog
  open={!!deleteItem}
  title="Delete page"
  message="Delete '{deleteItem?.title}'? This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .table-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; }
  .table thead th { padding: 10px 16px; background: var(--sc-surface-2); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); text-align: left; border-bottom: 1px solid var(--sc-border); }
  .table tbody tr { border-bottom: 1px solid var(--sc-border); }
  .table tbody tr:last-child { border-bottom: none; }
  .table tbody tr:hover { background: var(--sc-surface-2); }
  .table td { padding: 10px 16px; font-size: 13px; }
  .item-link { color: var(--sc-text); font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
  .item-link:hover { color: var(--sc-accent); }
  .tree-indent { color: var(--sc-text-muted); }
  .muted-cell { color: var(--sc-text-muted); font-size: 12px; }
  .actions-cell { text-align: right; width: 40px; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover { background: var(--sc-accent-hover); }
</style>
