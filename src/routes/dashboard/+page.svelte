<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { userStore } from '$lib/stores/user.svelte.js';
  import api from '$lib/api.js';
  import { onMount } from 'svelte';
  import { formatDate } from '$lib/utils/formatDate.js';

  let collections = $state([]);
  let loading     = $state(true);

  onMount(async () => {
    try {
      const res = await api.get('collections');
      collections = res.data ?? [];
    } catch {
      // silent on dashboard
    } finally {
      loading = false;
    }
  });
</script>

<AdminShell title="Dashboard">
  <div class="dashboard">
    <div class="welcome">
      <h2>Welcome back, {userStore.current?.displayName} 👋</h2>
      <p>Here's what's happening in your CMS.</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card__num">{collections.length}</div>
        <div class="stat-card__label">Collections</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__num">{collections.reduce((a, c) => a + (c.item_count ?? 0), 0)}</div>
        <div class="stat-card__label">Total Items</div>
      </div>
    </div>

    {#if collections.length}
      <section class="section">
        <h3 class="section-title">Collections</h3>
        <div class="collection-list">
          {#each collections as c}
            <a href="/admin/collections/{c.slug}" class="collection-row">
              <span class="collection-row__icon">{c.icon ?? '⊞'}</span>
              <span class="collection-row__name">{c.name}</span>
              <span class="collection-row__count">{c.item_count ?? 0} items</span>
              <span class="collection-row__updated">{formatDate(c.updated_at, 'relative')}</span>
            </a>
          {/each}
        </div>
      </section>
    {/if}
  </div>
</AdminShell>

<style>
  .dashboard { max-width: 900px; }
  .welcome { margin-bottom: 28px; }
  .welcome h2 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
  .welcome p  { margin: 0; color: var(--sc-text-muted); }

  .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }

  .stat-card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 20px; }
  .stat-card__num { font-size: 32px; font-weight: 800; color: var(--sc-accent); }
  .stat-card__label { font-size: 13px; color: var(--sc-text-muted); margin-top: 4px; }

  .section-title { font-size: 14px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 12px; }

  .collection-list { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }

  .collection-row { display: flex; align-items: center; gap: 12px; padding: 13px 16px; text-decoration: none; color: var(--sc-text); border-bottom: 1px solid var(--sc-border); transition: background 0.12s; }
  .collection-row:last-child { border-bottom: none; }
  .collection-row:hover { background: var(--sc-surface-2); }

  .collection-row__icon   { font-size: 16px; width: 24px; text-align: center; }
  .collection-row__name   { flex: 1; font-weight: 500; }
  .collection-row__count  { font-size: 12px; color: var(--sc-text-muted); background: var(--sc-surface-2); padding: 2px 8px; border-radius: 99px; }
  .collection-row__updated{ font-size: 12px; color: var(--sc-text-muted); margin-left: 8px; }
</style>
