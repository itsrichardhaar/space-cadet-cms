<script>
  import { onDestroy, untrack } from 'svelte';
  import { goto, beforeNavigate } from '$app/navigation';
  import { page } from '$app/stores';
  import { EditorState } from '@codemirror/state';
  import { EditorView, basicSetup } from 'codemirror';
  import { html } from '@codemirror/lang-html';
  import { oneDark } from '@codemirror/theme-one-dark';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { slugify } from '$lib/utils/slugify.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import Select from '$lib/components/common/Select.svelte';

  const TEMPLATE_TYPE_OPTS = [
    { value: 'page', label: 'Page' },
    { value: 'partial', label: 'Partial' },
    { value: 'layout', label: 'Layout' },
  ];

  let tplId    = $derived(parseInt($page.params.id));

  let loading   = $state(true);
  let saving    = $state(false);
  let notFound  = $state(false);
  let showDelete = $state(false);

  let name      = $state('');
  let slug      = $state('');
  let type      = $state('page');
  let source    = $state('');
  let updatedAt = $state(null);
  let slugEdited = false;

  // Unsaved-changes tracking
  let savedSnap = $state('');
  let isDirty = $derived(
    !loading && savedSnap !== '' &&
    JSON.stringify({ name, slug, type, source }) !== savedSnap
  );

  beforeNavigate(({ cancel }) => {
    if (isDirty && !confirm('You have unsaved changes. Leave anyway?')) cancel();
  });

  let editorEl = $state();
  let view;
  let fromView = false;

  $effect(() => { void tplId; load(); });

  async function load() {
    loading = true;
    try {
      const res = await api.get(`templates/${tplId}`);
      if (!res.data) { notFound = true; return; }
      const t = res.data;
      name      = t.name;
      slug      = t.slug;
      type      = t.type;
      source    = t.source ?? '';
      updatedAt = t.updated_at;
      savedSnap = JSON.stringify({ name, slug, type, source });
      // Sync into editor if it's already mounted
      if (view && source !== view.state.doc.toString()) {
        view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: source } });
      }
    } catch (e) {
      if (e.status === 404) notFound = true;
      else notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  // Mount editor once editorEl enters the DOM (after loading → false)
  $effect(() => {
    if (!editorEl) return;
    const v = new EditorView({
      state: EditorState.create({
        doc: untrack(() => source),
        extensions: [
          basicSetup,
          oneDark,
          html(),
          EditorView.updateListener.of(u => {
            if (u.docChanged) {
              fromView = true;
              source = u.state.doc.toString();
              fromView = false;
            }
          }),
        ],
      }),
      parent: editorEl,
    });
    view = v;
    return () => { v.destroy(); view = null; };
  });

  onDestroy(() => view?.destroy());

  $effect(() => {
    if (!fromView && view && source !== view.state.doc.toString()) {
      view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: source || '' } });
    }
  });

  async function save() {
    saving = true;
    try {
      await api.put(`templates/${tplId}`, { name, slug, type, source });
      savedSnap = JSON.stringify({ name, slug, type, source });
      notifications.success('Template saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function deleteTemplate() {
    showDelete = false;
    try {
      await api.delete(`templates/${tplId}`);
      notifications.success('Template deleted');
      goto('/admin/templates');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<svelte:window onbeforeunload={e => { if (isDirty) { e.preventDefault(); return ''; } }} />

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}<p class="muted"><a href="/admin/templates">Back to Templates</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : name}>
    {#snippet actions()}
      <a href="/admin/templates" class="btn btn--ghost">← Templates</a>
      {#if isDirty}<span class="dirty-badge">Unsaved changes</span>{/if}
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
          <!-- Editor (full width) -->
          <div class="editor-wrap">
            <div bind:this={editorEl} class="editor"></div>
          </div>

          <!-- Sidebar -->
          <aside class="sidebar">
            <div class="card">
              <h3 class="card-title">Details</h3>
              <label class="label">Name</label>
              <input class="input" type="text" bind:value={name} />
              <label class="label" style="margin-top:10px">Slug</label>
              <input class="input" type="text" bind:value={slug} oninput={() => slugEdited = true} />
              <label class="label" style="margin-top:10px">Type</label>
              <Select bind:value={type} options={TEMPLATE_TYPE_OPTS} />
            </div>
            {#if updatedAt}
              <div class="card card--meta">
                <p class="meta-row">Updated: <span>{formatDate(updatedAt)}</span></p>
              </div>
            {/if}
            <div class="card card--help">
              <h3 class="card-title">Variables</h3>
              <p class="help-text">Always available:</p>
              <table class="var-table">
                <tbody>
                  <tr><td><code>{'{{ title }}'}</code></td><td>Page title</td></tr>
                  <tr><td><code>{'{{ slug }}'}</code></td><td>URL slug</td></tr>
                  <tr><td><code>{'{{ meta_title }}'}</code></td><td>SEO title</td></tr>
                  <tr><td><code>{'{{ meta_desc }}'}</code></td><td>Meta desc</td></tr>
                </tbody>
              </table>
              <p class="help-text" style="margin-top:10px">Custom fields are top-level: <code>{'{{ hero_text }}'}</code></p>
              <p class="help-text">Raw HTML (richtext): <code>{'{{{ body }}}'}</code></p>
            </div>
            <div class="card card--help">
              <h3 class="card-title">Syntax</h3>
              <p class="help-text help-text--mono"><span class="hl">{'{{ field }}'}</span> — escaped output</p>
              <p class="help-text help-text--mono"><span class="hl">{'{{{ field }}}'}</span> — raw HTML</p>
              <p class="help-text help-text--mono" style="margin-top:6px"><span class="hl">{'{% for item in items %}'}</span><br>{'  {{ item.name }}'}<br><span class="hl">{'{% endfor %}'}</span></p>
              <p class="help-text help-text--mono" style="margin-top:6px"><span class="hl">{'{% if title %}'}</span><br>{'  …'}<br><span class="hl">{'{% endif %}'}</span></p>
            </div>
          </aside>
        </div>
      {/if}
    {/snippet}
  </AdminShell>
{/if}

<ConfirmDialog
  open={showDelete}
  title="Delete template"
  message="Delete '{name}'?"
  confirmLabel="Delete"
  danger={true}
  onconfirm={deleteTemplate}
  oncancel={() => showDelete = false}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .dirty-badge { font-size: 11px; color: var(--sc-text-muted); padding: 4px 8px; }
  .layout { display: grid; grid-template-columns: 1fr 240px; gap: 20px; align-items: start; }
  .editor-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .editor { min-height: 500px; }
  :global(.cm-editor) { font-size: 13px; }
  :global(.cm-editor.cm-focused) { outline: none; }
  :global(.cm-scroller) { min-height: 500px; }
  .sidebar { display: flex; flex-direction: column; gap: 14px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 18px; }
  .card--meta { padding: 14px 18px; }
  .card--help { padding: 14px 18px; }
  .card-title { margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .label { display: block; font-size: 12px; font-weight: 600; color: var(--sc-text-muted); margin-bottom: 4px; }
  .input { width: 100%; padding: 7px 10px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .meta-row { margin: 0; font-size: 12px; color: var(--sc-text-muted); }
  .meta-row span { color: var(--sc-text); }
  .help-text { margin: 0; font-size: 12px; color: var(--sc-text-muted); line-height: 1.6; }
  .help-text code { font-family: var(--sc-font-mono); color: var(--sc-accent); font-size: 11px; }
  .help-text--mono { font-family: var(--sc-font-mono); font-size: 11px; line-height: 1.8; }
  .hl { color: var(--sc-accent); }
  .var-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  .var-table td { font-size: 11px; padding: 2px 0; color: var(--sc-text-muted); }
  .var-table td:first-child { width: 55%; }
  .var-table td code { font-family: var(--sc-font-mono); color: var(--sc-accent); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
</style>
