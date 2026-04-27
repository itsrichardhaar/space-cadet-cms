<script>
  import { onDestroy } from 'svelte';
  import { goto, beforeNavigate } from '$app/navigation';
  import { page } from '$app/stores';
  import Sortable from 'sortablejs';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import FieldRenderer from '$lib/components/fields/FieldRenderer.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { slugify } from '$lib/utils/slugify.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import { FIELD_TYPES, defaultFieldOptions } from '$lib/utils/fieldTypes.js';
  import Select from '$lib/components/common/Select.svelte';

  const PAGE_STATUS_OPTS = [
    { value: 'draft',     label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived',  label: 'Archived' },
  ];
  const FIELD_TYPE_OPTS = FIELD_TYPES.map(t => ({ value: t.type, label: t.label }));
  const CODE_LANG_OPTS = [
    { value: 'html',       label: 'HTML' },
    { value: 'css',        label: 'CSS' },
    { value: 'javascript', label: 'JavaScript' },
    { value: 'php',        label: 'PHP' },
  ];

  let pageId = $derived(parseInt($page.params.id));

  let loading     = $state(true);
  let saving      = $state(false);
  let duplicating = $state(false);
  let showDelete  = $state(false);
  let notFound    = $state(false);
  let allPages    = $state([]);
  let templates   = $state([]);

  // Form state
  let title       = $state('');
  let slug        = $state('');
  let parentId    = $state('');
  let templateId  = $state('');
  let status      = $state('draft');
  let publishedAt = $state('');
  let metaTitle   = $state('');
  let metaDesc    = $state('');
  let fields      = $state({});
  let fieldDefs   = $state([]);
  let createdAt   = $state(null);
  let updatedAt   = $state(null);

  let slugEdited = false;
  let _uid = 0;

  // Sortable
  let listEl  = $state();
  let sortable;
  $effect(() => {
    if (!listEl) return;
    sortable = Sortable.create(listEl, {
      animation: 150,
      handle: '.drag-handle',
      onEnd(evt) {
        const arr = [...fieldDefs];
        const [item] = arr.splice(evt.oldIndex, 1);
        arr.splice(evt.newIndex, 0, item);
        fieldDefs = arr.map((f, i) => ({ ...f, sort_order: i }));
      },
    });
    return () => sortable?.destroy();
  });
  onDestroy(() => sortable?.destroy());

  // Unsaved-changes tracking — exclude UI-only _uid/_open from fieldDefs
  function defsSnap(defs) {
    return defs.map(({ name, key, type, options, required }) => ({ name, key, type, options, required }));
  }
  let savedSnap = $state('');
  let isDirty = $derived(
    !loading && savedSnap !== '' &&
    JSON.stringify({ title, slug, status, publishedAt, parentId, templateId, metaTitle, metaDesc, fields, fieldDefs: defsSnap(fieldDefs) }) !== savedSnap
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
      parentId    = p.parent_id   ? String(p.parent_id)   : '';
      templateId  = p.template_id ? String(p.template_id) : '';
      status      = p.status    ?? 'draft';
      publishedAt = p.published_at ? new Date(p.published_at * 1000).toISOString().slice(0,16) : '';
      metaTitle   = p.meta_title ?? '';
      metaDesc    = p.meta_desc  ?? '';
      const rawFields = p.fields ?? {};
      fields      = { ...rawFields };
      fieldDefs   = (p.fieldDefs ?? []).map(f => ({
        ...f,
        _uid: ++_uid,
        _open: false,
        required: !!f.required,
        options: (() => { const o = typeof f.options === 'string' ? JSON.parse(f.options || '{}') : (f.options ?? {}); return Array.isArray(o) ? {} : o; })(),
      }));
      // Pre-initialise field values so FieldRenderer's $bindable defaults don't write back
      // after savedSnap is taken and cause a false isDirty on page load.
      for (const fd of fieldDefs) {
        if (fd.key && !(fd.key in rawFields)) {
          if (fd.type === 'toggle')        fields[fd.key] = false;
          else if (fd.type === 'checkbox') fields[fd.key] = [];
          else                             fields[fd.key] = null;
        }
      }
      createdAt   = p.created_at;
      updatedAt   = p.updated_at;
      allPages    = (pagesRes.data ?? []).filter(pp => pp.id !== pageId);
      templates   = tplRes.data ?? [];
      savedSnap   = JSON.stringify({ title, slug, status, publishedAt, parentId, templateId, metaTitle, metaDesc, fields, fieldDefs: defsSnap(fieldDefs) });
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
    // Silently discard rows where neither name nor key has been filled in (user added but didn't define)
    const activeDefs = fieldDefs.filter(f => (f.name ?? '').trim() || (f.key ?? '').trim());
    // Validate any partially-filled row
    const badField = activeDefs.find(f => !(f.name ?? '').trim() || !(f.key ?? '').trim());
    if (badField) {
      badField._open = true;
      notifications.error('Fill in the name and key for the highlighted field.');
      setTimeout(() => {
        const idx = fieldDefs.findIndex(f => f._uid === badField._uid);
        listEl?.querySelectorAll('.fd-row')?.[idx]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 50);
      return;
    }
    if (activeDefs.length < fieldDefs.length) fieldDefs = activeDefs;
    saving = true;
    try {
      const body = {
        title: title.trim(),
        slug,
        status,
        parent_id:   parentId   ? parseInt(parentId)   : null,
        template_id: templateId ? parseInt(templateId) : null,
        meta_title:  metaTitle || null,
        meta_desc:   metaDesc  || null,
        published_at: (status === 'published' && publishedAt)
          ? Math.floor(new Date(publishedAt).getTime() / 1000) : null,
        fields,
        fieldDefs: fieldDefs.map((f, i) => ({
          name:       f.name.trim(),
          key:        f.key.trim(),
          type:       f.type,
          options:    f.options ?? {},
          required:   !!f.required,
          sort_order: i,
        })),
      };
      await api.put(`pages/${pageId}`, body);
      savedSnap = JSON.stringify({ title, slug, status, publishedAt, parentId, templateId, metaTitle, metaDesc, fields, fieldDefs: defsSnap(fieldDefs) });
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
      savedSnap = '';
      notifications.success('Page deleted');
      goto('/admin/pages');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  // ── Field schema management ───────────────────────────────────────────────

  function toKey(name) {
    return name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
  }

  function addField() {
    fieldDefs = [...fieldDefs, {
      _uid: ++_uid,
      _open: true,
      name: '',
      key: '',
      type: 'text',
      options: {},
      required: false,
      sort_order: fieldDefs.length,
    }];
    setTimeout(() => {
      const rows = listEl?.querySelectorAll('.fd-row');
      const last = rows?.[rows.length - 1];
      last?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      last?.querySelector('.fd-name-input')?.focus();
    }, 60);
  }

  function removeField(uid) {
    const def = fieldDefs.find(f => f._uid === uid);
    if (def?.key) {
      const f = { ...fields };
      delete f[def.key];
      fields = f;
    }
    fieldDefs = fieldDefs.filter(f => f._uid !== uid);
  }

  function onFieldNameInput(f) {
    if (!f._keyEdited) f.key = toKey(f.name);
  }

  function onTypeChange(f) {
    f.options = defaultFieldOptions(f.type);
    fieldDefs = [...fieldDefs]; // trigger reactivity
  }

  function addChoice(f) {
    f.options = { ...f.options, choices: [...(f.options?.choices ?? []), ''] };
    fieldDefs = [...fieldDefs];
  }

  function removeChoice(f, i) {
    const c = [...(f.options?.choices ?? [])];
    c.splice(i, 1);
    f.options = { ...f.options, choices: c };
    fieldDefs = [...fieldDefs];
  }

  function updateChoice(f, i, val) {
    const c = [...(f.options?.choices ?? [])];
    c[i] = val;
    f.options = { ...f.options, choices: c };
    fieldDefs = [...fieldDefs];
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

            <!-- Content fields (fill in values) -->
            {#if fieldDefs.some(fd => fd.key)}
              <div class="card">
                <h3 class="card-title">Content</h3>
                <div class="content-fields">
                  {#each fieldDefs.filter(fd => fd.key) as fd (fd._uid)}
                    <FieldRenderer fieldDef={fd} bind:value={fields[fd.key]} />
                  {/each}
                </div>
              </div>
            {/if}

            <!-- Field schema editor (define fields) -->
            <div class="card">
              <h3 class="card-title">Custom Fields</h3>

              {#if fieldDefs.length === 0}
                <p class="fd-empty">No fields yet. Add fields to store structured content for this page — text, rich text, images, numbers, and more. Field keys become template variables.</p>
              {/if}

              <ul class="fd-list" bind:this={listEl}>
                {#each fieldDefs as f (f._uid)}
                  <li class="fd-row">
                    <div class="fd-row-header">
                      <button class="drag-handle" type="button" title="Drag to reorder">⠿</button>
                      <span class="fd-type-badge">{f.type}</span>
                      <div class="fd-meta">
                        <input
                          class="fd-name-input"
                          type="text"
                          bind:value={f.name}
                          oninput={() => onFieldNameInput(f)}
                          placeholder="Field name…"
                        />
                        <span class="fd-key-sep">→</span>
                        <input
                          class="fd-key-input"
                          type="text"
                          bind:value={f.key}
                          oninput={() => f._keyEdited = true}
                          placeholder="field_key"
                        />
                      </div>
                      <Select bind:value={f.type} options={FIELD_TYPE_OPTS} onchange={() => onTypeChange(f)} />
                      <label class="fd-req" title="Required">
                        <input type="checkbox" bind:checked={f.required} />
                        <span>Req</span>
                      </label>
                      <button class="fd-expand" type="button" onclick={() => f._open = !f._open}>{f._open ? '▴' : '▾'}</button>
                      <button class="fd-del" type="button" onclick={() => removeField(f._uid)} title="Remove">✕</button>
                    </div>

                    {#if f._open}
                      <div class="fd-options">
                        {#if f.type === 'select' || f.type === 'checkbox'}
                          <span class="fd-opt-label">Choices</span>
                          {#each (f.options?.choices ?? []) as choice, ci}
                            <div class="fd-choice-row">
                              <input class="fd-choice-input" type="text" value={choice}
                                oninput={(e) => updateChoice(f, ci, e.target.value)} placeholder="Option…" />
                              <button class="fd-choice-del" type="button" onclick={() => removeChoice(f, ci)}>✕</button>
                            </div>
                          {/each}
                          <button class="fd-add-choice" type="button" onclick={() => addChoice(f)}>+ Add choice</button>

                        {:else if f.type === 'relation'}
                          <div class="fd-opt-row">
                            <label class="fd-opt-label">Collection slug</label>
                            <input class="fd-opt-input" type="text" bind:value={f.options.collection} placeholder="collection-slug" />
                          </div>
                          <label class="fd-opt-label" style="flex-direction:row;gap:6px;align-items:center">
                            <input type="checkbox" bind:checked={f.options.multiple} /> Allow multiple
                          </label>

                        {:else if f.type === 'number'}
                          <div class="fd-opt-row">
                            <label class="fd-opt-label">Min<input class="fd-opt-num" type="number" bind:value={f.options.min} /></label>
                            <label class="fd-opt-label">Max<input class="fd-opt-num" type="number" bind:value={f.options.max} /></label>
                            <label class="fd-opt-label">Step<input class="fd-opt-num" type="number" bind:value={f.options.step} /></label>
                          </div>

                        {:else if f.type === 'code'}
                          <div class="fd-opt-row">
                            <label class="fd-opt-label">Language</label>
                            <Select bind:value={f.options.language} options={CODE_LANG_OPTS} />
                          </div>

                        {:else}
                          <p class="fd-no-opts">No options for this field type.</p>
                        {/if}
                      </div>
                    {/if}
                  </li>
                {/each}
              </ul>

              <button class="fd-add-btn" type="button" onclick={addField}>+ Add field</button>
            </div>

          </div>

          <!-- Sidebar -->
          <aside class="sidebar">
            <div class="card">
              <h3 class="card-title">Status</h3>
              <Select bind:value={status} options={PAGE_STATUS_OPTS} />
              {#if status === 'published'}
                <label class="label" style="margin-top:12px">Publish date</label>
                <input class="input" type="datetime-local" bind:value={publishedAt} />
              {/if}
            </div>

            <div class="card">
              <h3 class="card-title">Template</h3>
              <Select
                bind:value={templateId}
                options={[{ value: '', label: 'No template' }, ...templates.filter(t => t.type === 'page').map(t => ({ value: String(t.id), label: t.name }))]}
              />
              {#if templateId}
                <a href="/admin/templates/{templateId}" class="tpl-link">Edit template →</a>
              {/if}
            </div>

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

            <div class="card">
              <h3 class="card-title">SEO</h3>
              <label class="label">Meta title</label>
              <input class="input" type="text" bind:value={metaTitle} placeholder={title} />
              <label class="label" style="margin-top:10px">Meta description</label>
              <textarea class="input input--ta" bind:value={metaDesc} rows="3" placeholder="Brief description…"></textarea>
            </div>

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
  .content-fields { display: flex; flex-direction: column; gap: 18px; }
  .label { display: block; font-size: 12px; font-weight: 600; color: var(--sc-text-muted); margin-bottom: 4px; }
  .input { width: 100%; padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .input--ta { resize: vertical; font-family: inherit; }
  .meta-row { margin: 0 0 6px; font-size: 12px; color: var(--sc-text-muted); }
  .meta-row span { color: var(--sc-text); }
  .tpl-link { display: inline-block; margin-top: 8px; font-size: 12px; color: var(--sc-accent); text-decoration: none; }
  .tpl-link:hover { text-decoration: underline; }

  /* Field schema editor */
  .fd-empty { font-size: 13px; color: var(--sc-text-muted); margin: 0 0 14px; line-height: 1.5; }
  .fd-list { list-style: none; padding: 0; margin: 0 0 10px; display: flex; flex-direction: column; gap: 6px; }
  .fd-row { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); overflow: hidden; }
  .fd-row-header { display: flex; align-items: center; gap: 8px; padding: 8px 10px; }
  .drag-handle { background: none; border: none; color: var(--sc-text-muted); cursor: grab; font-size: 16px; padding: 2px 4px; flex-shrink: 0; }
  .drag-handle:active { cursor: grabbing; }
  .fd-type-badge { font-size: 10px; font-weight: 700; background: rgba(var(--sc-accent-rgb),.12); color: var(--sc-accent); padding: 2px 7px; border-radius: 99px; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; flex-shrink: 0; }
  .fd-meta { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; }
  .fd-name-input { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 5px 9px; color: var(--sc-text); font-size: 13px; width: 160px; outline: none; }
  .fd-name-input:focus { border-color: var(--sc-accent); }
  .fd-key-sep { color: var(--sc-text-muted); font-size: 12px; flex-shrink: 0; }
  .fd-key-input { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 5px 9px; color: var(--sc-text-muted); font-size: 11px; font-family: var(--sc-font-mono); width: 120px; outline: none; }
  .fd-key-input:focus { border-color: var(--sc-accent); color: var(--sc-text); }
  .fd-req { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--sc-text-muted); cursor: pointer; white-space: nowrap; flex-shrink: 0; }
  .fd-req input { accent-color: var(--sc-accent); }
  .fd-expand { background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 13px; padding: 2px 6px; }
  .fd-expand:hover { color: var(--sc-text); }
  .fd-del { background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 13px; padding: 2px 6px; margin-left: auto; flex-shrink: 0; }
  .fd-del:hover { color: var(--sc-danger); }
  .fd-options { padding: 12px 14px; border-top: 1px solid var(--sc-border); background: var(--sc-surface); display: flex; flex-direction: column; gap: 8px; }
  .fd-opt-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; display: flex; flex-direction: column; gap: 4px; }
  .fd-opt-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .fd-opt-input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 5px 9px; color: var(--sc-text); font-size: 13px; width: 200px; outline: none; }
  .fd-opt-input:focus { border-color: var(--sc-accent); }
  .fd-opt-num { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 5px 9px; color: var(--sc-text); font-size: 13px; width: 70px; outline: none; margin-top: 4px; }
  .fd-opt-num:focus { border-color: var(--sc-accent); }
  .fd-choice-row { display: flex; align-items: center; gap: 6px; }
  .fd-choice-input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 5px 9px; color: var(--sc-text); font-size: 13px; flex: 1; outline: none; }
  .fd-choice-input:focus { border-color: var(--sc-accent); }
  .fd-choice-del { background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 13px; padding: 2px 6px; }
  .fd-choice-del:hover { color: var(--sc-danger); }
  .fd-add-choice { background: none; border: 1px dashed var(--sc-border); border-radius: var(--sc-radius); padding: 4px 10px; color: var(--sc-text-muted); font-size: 12px; cursor: pointer; align-self: flex-start; }
  .fd-add-choice:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .fd-no-opts { font-size: 12px; color: var(--sc-text-muted); margin: 0; }
  .fd-add-btn { display: flex; align-items: center; justify-content: center; width: 100%; padding: 10px; border: 2px dashed var(--sc-border); border-radius: var(--sc-radius); background: none; color: var(--sc-text-muted); font-size: 13px; cursor: pointer; }
  .fd-add-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }

  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
  .btn--danger:hover { background: rgba(248,113,113,.1); opacity: 1; }
</style>
