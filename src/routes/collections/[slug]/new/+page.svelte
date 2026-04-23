<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import api from '$lib/api.js';
  import { onMount } from 'svelte';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import Select from '$lib/components/common/Select.svelte';

  const STATUS_OPTS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
  ];

  let slug       = $derived($page.params.slug);
  let collection = $state(null);
  let title      = $state('');
  let status     = $state('draft');
  let saving     = $state(false);
  let notFound   = $state(false);

  onMount(async () => {
    try {
      const res = await api.get('collections');
      collection = (res.data ?? []).find(c => c.slug === slug);
      if (!collection) notFound = true;
    } catch (e) {
      notifications.error(e.message);
    }
  });

  async function submit(e) {
    e.preventDefault();
    if (!title.trim()) return;
    saving = true;
    try {
      const res = await api.post(`collections/${collection.id}/items`, {
        title: title.trim(),
        status,
        fields: {},
        labels: [],
      });
      goto(`/admin/collections/${slug}/${res.data.id}`);
    } catch (err) {
      notifications.error(err.message);
      saving = false;
    }
  }
</script>

<AdminShell title="New item in {collection?.name ?? '…'}">
  {#snippet actions()}
    <a href="/admin/collections/{slug}" class="btn-ghost">Cancel</a>
  {/snippet}

  {#if notFound}
    <p class="err">Collection not found.</p>
  {:else if !collection}
    <p class="muted">Loading…</p>
  {:else}
    <div class="form-wrap">
      <form onsubmit={submit}>
        <div class="card">
          <div class="field">
            <label class="label" for="item-title">Title <span class="req">*</span></label>
            <input
              id="item-title"
              class="input"
              type="text"
              bind:value={title}
              placeholder="Item title…"
            />
          </div>

          {#if collection.supports_status}
            <div class="field">
              <label class="label" for="item-status">Status</label>
              <Select id="item-status" bind:value={status} options={STATUS_OPTS} />
            </div>
          {/if}
        </div>

        <div class="actions">
          <button class="btn-primary" type="submit" disabled={saving || !title.trim()}>
            {saving ? 'Creating…' : 'Create & edit'}
          </button>
        </div>
      </form>
    </div>
  {/if}
</AdminShell>

<style>
  .muted { color: var(--sc-text-muted); }
  .err   { color: var(--sc-danger); }
  .form-wrap { max-width: 520px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 20px; display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 9px 12px; color: var(--sc-text); font-size: 15px; width: 100%; }
  .input:focus { outline: none; border-color: var(--sc-accent); }
  .select { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 14px; width: 100%; cursor: pointer; }
  .select:focus { outline: none; border-color: var(--sc-accent); }
  .actions { margin-top: 16px; display: flex; gap: 10px; }
  .btn-primary { display: inline-flex; align-items: center; padding: 9px 20px; background: var(--sc-accent); color: #fff; border-radius: var(--sc-radius); font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
  .btn-primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn-primary:disabled { opacity: .6; cursor: default; }
  .btn-ghost { display: inline-flex; align-items: center; padding: 9px 16px; border: 1px solid var(--sc-border); color: var(--sc-text-muted); border-radius: var(--sc-radius); font-size: 14px; text-decoration: none; }
  .btn-ghost:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
