<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { userStore } from '$lib/stores/user.svelte.js';
  import api from '$lib/api.js';
  import Skeleton from '$lib/components/common/Skeleton.svelte';
  import { onMount } from 'svelte';
  import { formatDate } from '$lib/utils/formatDate.js';

  let collections   = $state([]);
  let stats         = $state(null);
  let loading       = $state(true);
  let setupDismissed = $state(true); // default true to avoid flash

  // Setup checklist derived from stats
  let setupDone = $derived(
    !loading &&
    (stats?.collections ?? 0) > 0 &&
    (stats?.pages ?? 0) > 0
  );

  // Show the banner if: not dismissed, not all done, and we have stats
  let showSetup = $derived(
    !loading && !setupDismissed && !(setupDone)
  );

  function dismissSetup() {
    setupDismissed = true;
    try { localStorage.setItem('sc_setup_dismissed', '1'); } catch {}
  }

  onMount(async () => {
    // Check dismiss state first (avoids flash)
    try { setupDismissed = localStorage.getItem('sc_setup_dismissed') === '1'; } catch {}

    try {
      const [statsRes, colRes] = await Promise.all([
        api.get('stats'),
        api.get('collections'),
      ]);
      stats       = statsRes.data ?? {};
      collections = colRes.data ?? [];
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
      <h2>Welcome back, {userStore.current?.displayName}</h2>
      <p>Here's what's happening in your CMS.</p>
    </div>

    <!-- Setup wizard banner: shown on fresh installs -->
    {#if showSetup}
      <div class="setup-banner">
        <div class="setup-banner__header">
          <div class="setup-banner__icon">🚀</div>
          <div>
            <div class="setup-banner__title">Get started with Space Cadet</div>
            <div class="setup-banner__sub">A few quick steps to have your CMS ready.</div>
          </div>
          <button class="setup-banner__dismiss" onclick={dismissSetup} title="Dismiss">✕</button>
        </div>
        <div class="setup-checklist">
          <a href="/admin/collections" class="setup-step" class:done={(stats?.collections ?? 0) > 0}>
            <span class="setup-step__check">{(stats?.collections ?? 0) > 0 ? '✓' : '○'}</span>
            <span class="setup-step__label">Create your first collection</span>
            {#if (stats?.collections ?? 0) === 0}
              <span class="setup-step__cta">→</span>
            {/if}
          </a>
          <a href="/admin/pages" class="setup-step" class:done={(stats?.pages ?? 0) > 0}>
            <span class="setup-step__check">{(stats?.pages ?? 0) > 0 ? '✓' : '○'}</span>
            <span class="setup-step__label">Create your first page</span>
            {#if (stats?.pages ?? 0) === 0}
              <span class="setup-step__cta">→</span>
            {/if}
          </a>
          <a href="/admin/settings" class="setup-step">
            <span class="setup-step__check">○</span>
            <span class="setup-step__label">Configure site URL in Settings</span>
            <span class="setup-step__cta">→</span>
          </a>
        </div>
      </div>
    {/if}

    <div class="stats-grid">
      {#if loading}
        {#each {length: 6} as _}
          <div class="stat-card">
            <Skeleton height="36px" width="50%" radius="4px" />
            <Skeleton height="12px" width="70%" radius="4px" class="sk-label" />
          </div>
        {/each}
      {:else}
        <div class="stat-card">
          <div class="stat-card__num">{stats?.collections ?? collections.length}</div>
          <div class="stat-card__label">Collections</div>
        </div>
        <div class="stat-card">
          <div class="stat-card__num">{stats?.items ?? '—'}</div>
          <div class="stat-card__label">Items</div>
        </div>
        <div class="stat-card">
          <div class="stat-card__num">{stats?.pages ?? '—'}</div>
          <div class="stat-card__label">Pages</div>
        </div>
        <div class="stat-card">
          <div class="stat-card__num">{stats?.media ?? '—'}</div>
          <div class="stat-card__label">Media</div>
        </div>
        <div class="stat-card">
          <div class="stat-card__num">{stats?.members ?? '—'}</div>
          <div class="stat-card__label">Members</div>
        </div>
        <div class="stat-card">
          <div class="stat-card__num">{stats?.forms ?? '—'}</div>
          <div class="stat-card__label">Forms</div>
        </div>
      {/if}
    </div>

    {#if collections.length}
      <section class="section">
        <h3 class="section-title">Collections</h3>
        <div class="collection-list">
          {#each collections as c}
            <a href="/admin/collections/{c.slug}" class="collection-row">
              <span class="collection-row__icon">⊞</span>
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

  /* Setup banner */
  .setup-banner { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 20px 24px; margin-bottom: 28px; }
  .setup-banner__header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px; }
  .setup-banner__icon { font-size: 24px; flex-shrink: 0; }
  .setup-banner__title { font-size: 15px; font-weight: 700; color: var(--sc-text); margin-bottom: 3px; }
  .setup-banner__sub { font-size: 13px; color: var(--sc-text-muted); }
  .setup-banner__dismiss { margin-left: auto; background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 14px; padding: 2px 6px; border-radius: var(--sc-radius); flex-shrink: 0; }
  .setup-banner__dismiss:hover { color: var(--sc-text); background: var(--sc-surface-2); }

  .setup-checklist { display: flex; flex-direction: column; gap: 8px; }
  .setup-step { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border: 1px solid var(--sc-border); border-radius: var(--sc-radius); text-decoration: none; color: var(--sc-text); transition: border-color .12s, background .12s; }
  .setup-step:hover { border-color: var(--sc-accent); background: rgba(var(--sc-accent-rgb), .03); }
  .setup-step.done { opacity: .5; }
  .setup-step__check { font-size: 14px; color: var(--sc-accent); flex-shrink: 0; width: 18px; text-align: center; }
  .setup-step.done .setup-step__check { color: var(--sc-success); }
  .setup-step__label { flex: 1; font-size: 13px; font-weight: 500; }
  .setup-step__cta { font-size: 13px; color: var(--sc-text-muted); }

  .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }

  .stat-card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 20px; }
  .stat-card__num { font-size: 32px; font-weight: 800; color: var(--sc-accent); }
  .stat-card__label { font-size: 13px; color: var(--sc-text-muted); margin-top: 4px; }
  :global(.sk-label) { margin-top: 8px; }

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
