<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import { goto } from '$app/navigation';
  import api from '$lib/api.js';
  import { onMount } from 'svelte';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  let collections = $state([]);
  let loading     = $state(true);

  onMount(load);

  async function load() {
    loading = true;
    try { collections = (await api.get('collections')).data ?? []; }
    catch (e) { notifications.error(e.message); }
    finally { loading = false; }
  }

  async function deleteCollection(id, name) {
    if (!confirm(`Delete collection "${name}" and ALL its items? This cannot be undone.`)) return;
    try {
      await api.delete(`collections/${id}`);
      notifications.success(`"${name}" deleted.`);
      collections = collections.filter(c => c.id !== id);
    } catch (e) { notifications.error(e.message); }
  }
</script>

<AdminShell title="Collections">
  {#snippet actions()}
    <a href="/admin/collections/new" class="btn-primary">+ New Collection</a>
  {/snippet}

  {#if loading}
    <p class="loading">Loading…</p>
  {:else if !collections.length}
    <EmptyState icon="⊞" title="No collections yet" description="Create your first collection to start managing content.">
      {#snippet action()}<a href="/admin/collections/new" class="btn-primary">Create collection</a>{/snippet}
    </EmptyState>
  {:else}
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Items</th>
            <th>Fields</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {#each collections as c}
            <tr>
              <td>
                <a href="/admin/collections/{c.slug}" class="table-link">{c.icon ?? '⊞'} {c.name}</a>
              </td>
              <td><code class="code">{c.slug}</code></td>
              <td>{c.item_count ?? 0}</td>
              <td>
                <a href="/admin/collections/{c.slug}/schema" class="table-link-muted">Edit schema</a>
              </td>
              <td class="actions-cell">
                <a href="/admin/collections/{c.slug}" class="action-btn">View</a>
                <a href="/admin/collections/{c.slug}/schema" class="action-btn">Schema</a>
                <button class="action-btn action-btn--danger" onclick={() => deleteCollection(c.id, c.name)}>Delete</button>
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>
  {/if}
</AdminShell>

<style>
  .loading { color: var(--sc-text-muted); }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .table th { text-align: left; padding: 11px 16px; font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--sc-border); }
  .table td { padding: 13px 16px; border-bottom: 1px solid var(--sc-border); vertical-align: middle; }
  .table tbody tr:last-child td { border-bottom: none; }
  .table tbody tr:hover td { background: var(--sc-surface-2); }
  .table-link { color: var(--sc-text); font-weight: 500; text-decoration: none; }
  .table-link:hover { color: var(--sc-accent); }
  .table-link-muted { color: var(--sc-text-muted); font-size: 12px; text-decoration: none; }
  .table-link-muted:hover { color: var(--sc-accent); }
  .code { font-family: var(--sc-font-mono); font-size: 12px; background: var(--sc-surface-2); padding: 2px 6px; border-radius: 4px; color: var(--sc-text-muted); }
  .actions-cell { display: flex; gap: 6px; align-items: center; }
  .action-btn { font-size: 12px; padding: 4px 10px; border-radius: var(--sc-radius); border: 1px solid var(--sc-border); background: none; color: var(--sc-text-muted); cursor: pointer; text-decoration: none; }
  .action-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .action-btn--danger { color: var(--sc-danger); }
  .action-btn--danger:hover { border-color: var(--sc-danger); }
  .btn-primary { display: inline-flex; align-items: center; padding: 8px 16px; background: var(--sc-accent); color: #fff; border-radius: var(--sc-radius); font-size: 14px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
  .btn-primary:hover { background: var(--sc-accent-hover); }
</style>
