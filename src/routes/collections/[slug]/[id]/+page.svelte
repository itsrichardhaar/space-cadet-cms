<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import FieldRenderer from '$lib/components/fields/FieldRenderer.svelte';
  import StatusBadge from '$lib/components/common/StatusBadge.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { goto, beforeNavigate } from '$app/navigation';
  import { page } from '$app/stores';
  import api from '$lib/api.js';
  import { onMount } from 'svelte';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import Select from '$lib/components/common/Select.svelte';

  const STATUS_OPTS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
  ];

  let slug       = $derived($page.params.slug);
  let itemId     = $derived($page.params.id);

  let collection  = $state(null);
  let item        = $state(null);
  let loading     = $state(true);
  let saving      = $state(false);
  let duplicating = $state(false);
  let notFound    = $state(false);
  let showDelete  = $state(false);
  let allLabels   = $state([]);

  // Editable fields
  let title    = $state('');
  let itemSlug = $state('');
  let status   = $state('draft');
  let publishedAt = $state(null);
  let fields   = $state({});
  let labelIds = $state([]);

  let slugEdited = false;

  // Revision history
  let showRevisions    = $state(false);
  let revisions        = $state(null);   // null = not loaded
  let loadingRevisions = $state(false);
  let restoreRevId     = $state(null);   // pending confirm
  let restoring        = $state(false);

  async function loadRevisions() {
    if (!item) return;
    loadingRevisions = true;
    try {
      const res = await api.get('revisions', { entity_type: 'collection_item', entity_id: item.id });
      revisions = res.data ?? [];
    } catch { revisions = []; }
    finally { loadingRevisions = false; }
  }

  async function restoreRevision() {
    const revId = restoreRevId;
    restoreRevId = null;
    restoring = true;
    try {
      await api.post(`revisions/${revId}/restore`);
      notifications.success('Revision restored');
      savedSnap = ''; // prevent dirty guard
      await load();
    } catch (e) {
      notifications.error(e.message);
    } finally {
      restoring = false;
    }
  }

  // Unsaved-changes tracking
  let savedSnap = $state('');
  let isDirty = $derived(
    !loading && savedSnap !== '' &&
    JSON.stringify({ title, itemSlug, status, publishedAt, fields, labelIds }) !== savedSnap
  );

  beforeNavigate(({ cancel }) => {
    if (isDirty && !confirm('You have unsaved changes. Leave anyway?')) cancel();
  });

  onMount(() => load());

  let _prevId = '';
  $effect(() => {
    const id = itemId;
    if (id !== _prevId) { _prevId = id; if (!loading) load(); }
  });

  async function load() {
    loading = true;
    try {
      const allRes = await api.get('collections');
      const c = (allRes.data ?? []).find(c => c.slug === slug);
      if (!c) { notFound = true; return; }

      const [collRes, itemRes, labelsRes] = await Promise.all([
        api.get(`collections/${c.id}`),
        api.get(`collections/${c.id}/items/${itemId}`),
        api.get('labels'),
      ]);

      collection = collRes.data;
      item       = itemRes.data;
      allLabels  = labelsRes.data ?? [];

      title       = item.title ?? '';
      itemSlug    = item.slug  ?? '';
      status      = item.status ?? 'draft';
      publishedAt = item.published_at ?? null;
      const rawFields = item.fields ?? {};
      fields      = { ...rawFields };
      labelIds    = (item.labels ?? []).map(l => l.id);

      // Pre-initialise $bindable defaults to prevent false isDirty on mount
      for (const fd of (collection?.fields ?? [])) {
        if (fd.key && !(fd.key in rawFields)) {
          if (fd.type === 'toggle')        fields[fd.key] = false;
          else if (fd.type === 'checkbox') fields[fd.key] = [];
          else                             fields[fd.key] = null;
        }
      }

      savedSnap = JSON.stringify({ title, itemSlug, status, publishedAt, fields, labelIds });
    } catch (e) {
      if (e.status === 404) notFound = true;
      else notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function onTitleInput() {
    if (!slugEdited) itemSlug = toSlug(title);
  }

  function toSlug(s) {
    return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  function toggleLabel(id) {
    if (labelIds.includes(id)) labelIds = labelIds.filter(l => l !== id);
    else labelIds = [...labelIds, id];
  }

  async function save() {
    saving = true;
    try {
      await api.put(`collections/${collection.id}/items/${itemId}`, {
        title: title.trim(),
        slug:  itemSlug.trim(),
        status,
        published_at: publishedAt,
        fields,
        labels: labelIds,
      });
      savedSnap = JSON.stringify({ title, itemSlug, status, publishedAt, fields, labelIds });
      notifications.success('Saved.');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function duplicate() {
    duplicating = true;
    try {
      const res = await api.post(`collections/${collection.id}/items/${itemId}/duplicate`);
      notifications.success('Item duplicated.');
      goto(`/admin/collections/${slug}/${res.data.id}`);
    } catch (e) {
      notifications.error(e.message);
    } finally {
      duplicating = false;
    }
  }

  function openDelete() { showDelete = true; }

  async function confirmDelete() {
    showDelete = false;
    try {
      await api.delete(`collections/${collection.id}/items/${itemId}`);
      savedSnap = ''; // clear dirty state before navigating
      notifications.success(`"${title}" deleted.`);
      goto(`/admin/collections/${slug}`);
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<svelte:window onbeforeunload={e => { if (isDirty) { e.preventDefault(); return ''; } }} />

<AdminShell title={item?.title ?? 'Loading…'}>
  {#snippet actions()}
    {#if item}
      <a href="/admin/collections/{slug}" class="btn-ghost">← {collection?.name}</a>
      {#if isDirty}
        <span class="dirty-badge">Unsaved changes</span>
      {/if}
      <button class="btn-ghost" onclick={duplicate} disabled={duplicating}>
        {duplicating ? 'Duplicating…' : 'Duplicate'}
      </button>
      <button class="btn-danger-ghost" onclick={openDelete}>Delete</button>
      <button class="btn-primary" onclick={save} disabled={saving}>
        {saving ? 'Saving…' : 'Save'}
      </button>
    {/if}
  {/snippet}

  {#if notFound}
    <p class="err">Item not found.</p>
  {:else if loading}
    <p class="muted">Loading…</p>
  {:else}
    <div class="layout">
      <!-- Main content: custom fields -->
      <div class="main">
        {#if (collection?.fields ?? []).length === 0}
          <div class="no-fields">
            <p>No custom fields defined.</p>
            <a href="/admin/collections/{slug}/schema" class="schema-link">Edit schema →</a>
          </div>
        {:else}
          <div class="fields-card">
            {#each (collection?.fields ?? []) as fieldDef}
              <div class="field-wrap">
                <FieldRenderer {fieldDef} bind:value={fields[fieldDef.key]} />
              </div>
            {/each}
          </div>
        {/if}
      </div>

      <!-- Sidebar: core properties -->
      <aside class="sidebar">
        {#if collection?.supports_status}
          <div class="side-card">
            <div class="side-card-head">Status</div>
            <div class="side-card-body">
              <Select bind:value={status} options={STATUS_OPTS} />
              {#if status === 'published'}
                <div class="pub-date">
                  <span class="sub-label">Published at</span>
                  <input
                    class="input"
                    type="datetime-local"
                    value={publishedAt ? String(publishedAt).slice(0, 16) : ''}
                    onchange={(e) => publishedAt = e.target.value || null}
                  />
                </div>
              {/if}
            </div>
          </div>
        {/if}

        <div class="side-card">
          <div class="side-card-head">Identity</div>
          <div class="side-card-body">
            <span class="sub-label">Title</span>
            <input class="input" type="text" bind:value={title} oninput={onTitleInput} />
            <span class="sub-label">Slug</span>
            <input
              class="input slug"
              type="text"
              bind:value={itemSlug}
              oninput={() => slugEdited = true}
            />
          </div>
        </div>

        {#if allLabels.length > 0}
          <div class="side-card">
            <div class="side-card-head">Labels</div>
            <div class="side-card-body labels-list">
              {#each allLabels as lbl}
                <label class="label-item">
                  <input type="checkbox" checked={labelIds.includes(lbl.id)} onchange={() => toggleLabel(lbl.id)} />
                  <span class="label-dot" style="background:{lbl.color || '#888'}"></span>
                  <span>{lbl.name}</span>
                </label>
              {/each}
            </div>
          </div>
        {/if}

        <div class="side-card side-card--meta">
          <div class="meta-row"><span>Created</span><span>{formatDate(item.created_at, 'relative')}</span></div>
          <div class="meta-row"><span>Updated</span><span>{formatDate(item.updated_at, 'relative')}</span></div>
          {#if item.author_name}
            <div class="meta-row"><span>Author</span><span>{item.author_name}</span></div>
          {/if}
        </div>

        <!-- Revision history -->
        <div class="side-card">
          <button
            class="side-card-head side-card-head--toggle"
            onclick={() => { showRevisions = !showRevisions; if (showRevisions && revisions === null) loadRevisions(); }}
          >
            <span>History</span>
            <span class="toggle-chevron">{showRevisions ? '▴' : '▾'}</span>
          </button>
          {#if showRevisions}
            <div class="side-card-body rev-body">
              {#if loadingRevisions}
                <p class="rev-hint">Loading…</p>
              {:else if !revisions?.length}
                <p class="rev-hint">No history yet. Save to create a revision.</p>
              {:else}
                {#each revisions as rev}
                  <div class="rev-row">
                    <div class="rev-info">
                      <span class="rev-time">{formatDate(rev.created_at, 'relative')}</span>
                      {#if rev.user_name}<span class="rev-user">{rev.user_name}</span>{/if}
                    </div>
                    <button
                      class="rev-restore-btn"
                      onclick={() => restoreRevId = rev.id}
                      disabled={restoring}
                    >Restore</button>
                  </div>
                {/each}
              {/if}
            </div>
          {/if}
        </div>
      </aside>
    </div>
  {/if}
</AdminShell>

<ConfirmDialog
  open={showDelete}
  title="Delete item"
  message="Delete '{title}'? This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => showDelete = false}
/>

<ConfirmDialog
  open={!!restoreRevId}
  title="Restore this revision?"
  message="This will overwrite the current content with the selected version. A new revision will be saved first so you can undo."
  confirmLabel="Restore"
  danger={false}
  onconfirm={restoreRevision}
  oncancel={() => restoreRevId = null}
/>

<style>
  .muted { color: var(--sc-text-muted); }
  .err   { color: var(--sc-danger); }
  .dirty-badge { font-size: 11px; color: var(--sc-text-muted); padding: 4px 8px; }

  .layout { display: grid; grid-template-columns: 1fr 280px; gap: 24px; align-items: start; }

  .no-fields { border: 2px dashed var(--sc-border); border-radius: var(--sc-radius-lg); padding: 40px; text-align: center; color: var(--sc-text-muted); }
  .schema-link { display: inline-block; margin-top: 8px; color: var(--sc-accent); font-size: 13px; }

  .fields-card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 24px; display: flex; flex-direction: column; gap: 22px; }

  .sidebar { display: flex; flex-direction: column; gap: 12px; }

  .side-card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .side-card-head { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--sc-text-muted); border-bottom: 1px solid var(--sc-border); }
  .side-card-head--toggle { width: 100%; background: none; border: none; border-bottom: 1px solid var(--sc-border); cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--sc-text-muted); }
  .side-card-head--toggle:hover { color: var(--sc-text); }
  .toggle-chevron { font-size: 9px; }
  .side-card-body { padding: 14px; display: flex; flex-direction: column; gap: 10px; }
  .rev-body { padding: 8px 0; gap: 0; }
  .rev-hint { padding: 8px 14px; font-size: 12px; color: var(--sc-text-muted); margin: 0; }
  .rev-row { display: flex; align-items: center; justify-content: space-between; padding: 7px 14px; border-bottom: 1px solid var(--sc-border); }
  .rev-row:last-child { border-bottom: none; }
  .rev-info { display: flex; flex-direction: column; gap: 2px; }
  .rev-time { font-size: 12px; color: var(--sc-text); }
  .rev-user { font-size: 11px; color: var(--sc-text-muted); }
  .rev-restore-btn { background: none; border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 3px 8px; font-size: 11px; color: var(--sc-text-muted); cursor: pointer; }
  .rev-restore-btn:hover:not(:disabled) { border-color: var(--sc-accent); color: var(--sc-accent); }
  .rev-restore-btn:disabled { opacity: .4; cursor: default; }

  .input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 7px 10px; color: var(--sc-text); font-size: 13.5px; width: 100%; }
  .input:focus { outline: none; border-color: var(--sc-accent); }
  .input.slug { font-family: var(--sc-font-mono); font-size: 12px; color: var(--sc-text-muted); }
  .sub-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .pub-date { display: flex; flex-direction: column; gap: 4px; }

  .labels-list { display: flex; flex-direction: column; gap: 8px; }
  .label-item { display: flex; align-items: center; gap: 7px; font-size: 13px; cursor: pointer; }
  .label-item input { accent-color: var(--sc-accent); cursor: pointer; }
  .label-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

  .side-card--meta { }
  .meta-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 14px; font-size: 12px; border-bottom: 1px solid var(--sc-border); }
  .meta-row:last-child { border-bottom: none; }
  .meta-row span:first-child { color: var(--sc-text-muted); }
  .meta-row span:last-child  { color: var(--sc-text); }

  .btn-primary { display: inline-flex; align-items: center; padding: 8px 18px; background: var(--sc-accent); color: #fff; border-radius: var(--sc-radius); font-size: 13.5px; font-weight: 600; border: none; cursor: pointer; }
  .btn-primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn-primary:disabled { opacity: .6; cursor: default; }
  .btn-ghost { display: inline-flex; align-items: center; padding: 8px 14px; border: 1px solid var(--sc-border); color: var(--sc-text-muted); border-radius: var(--sc-radius); font-size: 13.5px; text-decoration: none; background: none; cursor: pointer; }
  .btn-ghost:hover:not(:disabled) { border-color: var(--sc-accent); color: var(--sc-accent); }
  .btn-ghost:disabled { opacity: .5; cursor: default; }
  .btn-danger-ghost { display: inline-flex; align-items: center; padding: 8px 14px; border: 1px solid var(--sc-border); color: var(--sc-danger); border-radius: var(--sc-radius); font-size: 13.5px; background: none; cursor: pointer; }
  .btn-danger-ghost:hover { border-color: var(--sc-danger); }
</style>
