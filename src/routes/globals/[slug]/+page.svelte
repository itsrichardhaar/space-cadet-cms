<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import FieldRenderer from '$lib/components/fields/FieldRenderer.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  let slug      = $derived($page.params.slug);

  let loading   = $state(true);
  let saving    = $state(false);
  let notFound  = $state(false);
  let group     = $state(null);
  let values    = $state({});

  $effect(() => { void slug; load(); });

  async function load() {
    loading = true;
    try {
      const all = await api.get('globals');
      const g = (all.data ?? []).find(g => g.slug === slug);
      if (!g) { notFound = true; return; }
      const res = await api.get(`globals/${g.id}`);
      group  = res.data;
      values = { ...(res.data.values ?? {}) };
    } catch (e) {
      if (e.status === 404) notFound = true;
      else notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  async function save() {
    saving = true;
    try {
      await api.put(`globals/${group.id}`, { values });
      notifications.success('Globals saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }
</script>

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}
      <p class="muted">Global group not found. <a href="/globals">Back to Globals</a></p>
    {/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : (group?.name ?? 'Globals')}>
    {#snippet actions()}
      <a href="/globals" class="btn btn--ghost">← All Globals</a>
      <button class="btn btn--primary" onclick={save} disabled={saving || loading}>
        {saving ? 'Saving…' : 'Save Changes'}
      </button>
    {/snippet}

    {#snippet children()}
      {#if loading}
        <p class="muted">Loading…</p>
      {:else if !group?.fields?.length}
        <div class="empty">
          <p>No fields defined for this global group.</p>
          <p class="hint">Add fields to this group to store global values.</p>
        </div>
      {:else}
        <div class="fields-card">
          {#if group.description}
            <p class="desc">{group.description}</p>
          {/if}
          <div class="fields-list">
            {#each group.fields as fd (fd.key)}
              <FieldRenderer fieldDef={fd} bind:value={values[fd.key]} />
            {/each}
          </div>
        </div>
      {/if}
    {/snippet}
  </AdminShell>
{/if}

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .fields-card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 24px; max-width: 680px; }
  .desc { margin: 0 0 20px; font-size: 13px; color: var(--sc-text-muted); }
  .fields-list { display: flex; flex-direction: column; gap: 20px; }
  .empty { color: var(--sc-text-muted); font-size: 13px; }
  .empty .hint { color: var(--sc-text-muted); font-size: 12px; }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
</style>
