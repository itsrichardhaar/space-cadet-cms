<script>
  import { onMount } from 'svelte';
  import { goto, beforeNavigate } from '$app/navigation';
  import { page } from '$app/stores';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import FieldRenderer from '$lib/components/fields/FieldRenderer.svelte';
  import StatusBadge from '$lib/components/common/StatusBadge.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { slugify } from '$lib/utils/slugify.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import Select from '$lib/components/common/Select.svelte';

  const PAGE_STATUS_OPTS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
  ];

  let pageId   = $derived(parseInt($page.params.id));

  let loading      = $state(true);
  let saving       = $state(false);
  let duplicating  = $state(false);
  let showDelete   = $state(false);
  let notFound     = $state(false);
  let allPages     = $state([]);
  let templates    = $state([]);

  // Form state
  let title        = $state('');
  let slug         = $state('');
  let parentId     = $state('');
  let status       = $state('draft');
  let publishedAt  = $state('');
  let metaTitle    = $state('');
  let metaDesc     = $state('');
  let fields       = $state({});
  let fieldDefs    = $state([]);
  let createdAt    = $state(null);
  let updatedAt    = $state(null);

  let slugEdited   = false;

  // Unsaved-changes tracking
  let savedSnap = $state('');
  let isDirty = $derived(
    !loading && savedSnap !== '' &&
    JSON.stringify({ title, slug, status, publishedAt, parentId, metaTitle, metaDesc, fields }) !== savedSnap
  );

  beforeNavigate(({ cancel }) => {
    if (isDirty && !confirm('You have unsaved changes. Leave anyway?')) cancel();
  });

  $effect(() => { void pageId; load(); });

  async function load() {
    loading = true;
    slugEdited = false;
    try {
      const [pageRes, pagesRes, tplRes] = await Promise.all([
        api.get(`pages/${pageId}`),
        api.get('pages'),
        api.get('templates').catch(() => ({ data: [] })),
      ]);
      const p = pageRes.data;
      if (!p) { notFound = true; return; }
      title       = p.title ?? '';
      slug        = p.slug  ?? '';
      parentId    = p.parent_id ? String(p.parent_id) : '';
      status      = p.status    ?? 'draft';
      publishedAt = p.published_at ? new Date(p.published_at * 1000).toISOString().slice(0,16) : '';
      metaTitle   = p.meta_title ?? '';
      metaDesc    = p.meta_desc  ?? '';
      fields      = { ...(p.fields ?? {}) };
      fieldDefs   = p.fieldDefs ?? [];
      createdAt   = p.created_at;
      updatedAt   = p.updated_at;
      allPages    = (pagesRes.data ?? []).filter(pp => pp.id !== pageId);
      templates   = tplRes.data ?? [];
      savedSnap   = JSON.stringify({ title, slug, status, publishedAt, parentId, metaTitle, metaDesc, fields });
    } catch (e) {
      if (e.status === 404) notFound = true;
      else notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function onTitleInput() {
    if (!slugEdited) slug = slugify(title);
  }

  async function save() {
    if (!title.trim()) { notifications.error('Title is required'); return; }
    saving = true;
    try {
      const body = {
        title: title.trim(),
        slug,
        status,
        parent_id: parentId ? parseInt(parentId) : null,
        meta_title: metaTitle || null,
        meta_desc:  metaDesc  || null,
        published_at: (status === 'published' && publishedAt)
          ? Math.floor(new Date(publishedAt).getTime() / 1000) : null,
        fields,
      };
      await api.put(`pages/${pageId}`, body);
      savedSnap = JSON.stringify({ title, slug, status, publishedAt, parentId, metaTitle, metaDesc, fields });
      notifications.success('Page saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function duplicate() {
    duplicating = true;
    try {
      const res = await api.post(`pages/${pageId}/duplicate`);
      notifications.success('Page duplicated.');
      goto(`/admin/pages/${res.data.id}`);
    } catch (e) {
      notifications.error(e.message);
    } finally {
      duplicating = false;
    }
  }

  async function deletePage() {
    showDelete = false;
    try {
      await api.delete(`pages/${pageId}`);
      savedSnap = ''; // clear dirty so beforeNavigate won't block
      notifications.success('Page deleted');
      goto('/admin/pages');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<svelte:window onbeforeunload={e => { if (isDirty) { e.preventDefault(); return ''; } }} />

{#if notFound}
  <AdminShell title="Page not found">
    {#snippet children()}
      <p class="muted">This page does not exist. <a href="/admin/pages">Back to Pages</a></p>
    {/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : title || 'Edit Page'}>
    {#snippet actions()}
      {#if isDirty}<span class="dirty-badge">Unsaved changes</span>{/if}
      <button class="btn btn--ghost" onclick={duplicate} disabled={duplicating || loading}>
        {duplicating ? 'Duplicating…' : 'Duplicate'}
      </button>
      <button class="btn btn--ghost btn--danger" onclick={() => showDelete = true}>Delete</button>
      <button class="btn btn--primary" onclick={save} disabled={saving || loading}>
        {saving ? 'Saving…' : 'Save'}
      </button>
    {/snippet}

    {#snippet children()}
      {#if loading}
        <p class="muted">Loading…</p>
      {:else}
        <div class="layout">
          <!-- Main column -->
          <div class="main">
            {#if fieldDefs.length > 0}
              <div class="card">
                <h3 class="card-title">Content Fields</h3>
                <div class="fields-list">
                  {#each fieldDefs as fd (fd.key)}
                    <FieldRenderer fieldDef={fd} bind:value={fields[fd.key]} />
                  {/each}
                </div>
              </div>
            {:else}
              <p class="muted">No custom fields defined for this page.</p>
            {/if}
          </div>

          <!-- Sidebar -->
          <aside class="sidebar">
            <!-- Status card -->
            <div class="card">
              <h3 class="card-title">Status</h3>
              <Select bind:value={status} options={PAGE_STATUS_OPTS} />
              {#if status === 'published'}
                <label class="label" style="margin-top:12px">Publish date</label>
                <input class="input" type="datetime-local" bind:value={publishedAt} />
              {/if}
            </div>

            <!-- Identity card -->
            <div class="card">
              <h3 class="card-title">Identity</h3>
              <label class="label">Title</label>
              <input class="input" type="text" bind:value={title} oninput={onTitleInput} />
              <label class="label" style="margin-top:10px">Slug</label>
              <input class="input" type="text" bind:value={slug} oninput={() => slugEdited = true} />
              <label class="label" style="margin-top:10px">Parent page</label>
              <Select bind:value={parentId}
                options={[{ value: '', label: 'None' }, ...allPages.map(p => ({ value: String(p.id), label: p.title }))]}
              />
            </div>

            <!-- SEO card -->
            <div class="card">
              <h3 class="card-title">SEO</h3>
              <label class="label">Meta title</label>
              <input class="input" type="text" bind:value={metaTitle} placeholder={title} />
              <label class="label" style="margin-top:10px">Meta description</label>
              <textarea class="input input--ta" bind:value={metaDesc} rows="3" placeholder="Brief description…"></textarea>
            </div>

            <!-- Meta card -->
            {#if createdAt}
              <div class="card card--meta">
                <p class="meta-row">Created: <span>{formatDate(createdAt)}</span></p>
                <p class="meta-row">Updated: <span>{formatDate(updatedAt)}</span></p>
              </div>
            {/if}
          </aside>
        </div>
      {/if}
    {/snippet}
  </AdminShell>
{/if}

<ConfirmDialog
  open={showDelete}
  title="Delete page"
  message="Delete '{title}'? This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={deletePage}
  oncancel={() => showDelete = false}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .dirty-badge { font-size: 11px; color: var(--sc-text-muted); padding: 4px 8px; }
  .layout { display: grid; grid-template-columns: 1fr 280px; gap: 24px; align-items: start; }
  .main   { display: flex; flex-direction: column; gap: 20px; }
  .sidebar { display: flex; flex-direction: column; gap: 16px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 18px; }
  .card-title { margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .card--meta { padding: 12px 18px; }
  .fields-list { display: flex; flex-direction: column; gap: 18px; }
  .label { display: block; font-size: 12px; font-weight: 600; color: var(--sc-text-muted); margin-bottom: 4px; }
  .input { width: 100%; padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .input--ta { resize: vertical; font-family: inherit; }
  .meta-row { margin: 0 0 6px; font-size: 12px; color: var(--sc-text-muted); }
  .meta-row span { color: var(--sc-text); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
  .btn--danger:hover { background: rgba(248,113,113,.1); opacity: 1; }
</style>
