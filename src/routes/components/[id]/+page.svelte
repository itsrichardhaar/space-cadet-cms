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
  import { formatDate } from '$lib/utils/formatDate.js';

  let componentId = $derived(parseInt($page.params.id));

  let loading    = $state(true);
  let saving     = $state(false);
  let notFound   = $state(false);
  let showDelete = $state(false);

  let name      = $state('');
  let slug      = $state('');
  let source    = $state('');
  let updatedAt = $state(null);
  let slugEdited = false;

  // Unsaved-changes tracking
  let savedSnap = $state('');
  let isDirty = $derived(
    !loading && savedSnap !== '' &&
    JSON.stringify({ name, slug, source }) !== savedSnap
  );

  beforeNavigate(({ cancel }) => {
    if (isDirty && !confirm('You have unsaved changes. Leave anyway?')) cancel();
  });

  // CodeMirror
  let editorEl = $state();
  let view;
  let fromView = false;

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

  $effect(() => { void componentId; load(); });

  async function load() {
    loading = true;
    try {
      const res = await api.get(`templates/${componentId}`);
      if (!res.data) { notFound = true; return; }
      const c = res.data;
      // Guard: only edit partials from this route
      if (c.type !== 'partial') { notFound = true; return; }
      name      = c.name;
      slug      = c.slug;
      source    = c.source ?? '';
      updatedAt = c.updated_at;
      savedSnap = JSON.stringify({ name, slug, source });
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
      await api.put(`templates/${componentId}`, { name, slug, type: 'partial', source });
      savedSnap = JSON.stringify({ name, slug, source });
      notifications.success('Component saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function deleteComponent() {
    showDelete = false;
    try {
      await api.delete(`templates/${componentId}`);
      savedSnap = '';
      notifications.success('Component deleted');
      goto('/admin/components');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<svelte:window onbeforeunload={e => { if (isDirty) { e.preventDefault(); return ''; } }} />

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}<p class="muted"><a href="/admin/components">Back to Components</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : name}>
    {#snippet actions()}
      <a href="/admin/components" class="btn btn--ghost">← Components</a>
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
          <div class="editor-wrap">
            <div bind:this={editorEl} class="editor"></div>
          </div>

          <aside class="sidebar">
            <div class="card">
              <h3 class="card-title">Details</h3>
              <label class="label">Name</label>
              <input class="input" type="text" bind:value={name} />
              <label class="label" style="margin-top:10px">Slug</label>
              <input class="input" type="text" bind:value={slug} oninput={() => slugEdited = true} />
            </div>

            <div class="card">
              <h3 class="card-title">Include tag</h3>
              <code class="snippet">{'{% include "'}{slug}{'" %}'}</code>
            </div>

            <div class="card card--help">
              <h3 class="card-title">Variables</h3>
              <p class="help-text">The parent template's context is available: <code>{'{{ title }}'}</code>, <code>{'{{ slug }}'}</code>, and all page fields.</p>
            </div>

            {#if updatedAt}
              <div class="card card--meta">
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
  title="Delete component"
  message="Delete '{name}'? This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={deleteComponent}
  oncancel={() => showDelete = false}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .dirty-badge { font-size: 11px; color: var(--sc-text-muted); padding: 4px 8px; }
  .layout { display: grid; grid-template-columns: 1fr 220px; gap: 20px; align-items: start; }
  .editor-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .editor { min-height: 500px; }
  :global(.cm-editor) { font-size: 13px; }
  :global(.cm-editor.cm-focused) { outline: none; }
  :global(.cm-scroller) { min-height: 500px; }
  .sidebar { display: flex; flex-direction: column; gap: 14px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 16px; }
  .card--meta { padding: 12px 16px; }
  .card--help { padding: 14px 16px; }
  .card-title { margin: 0 0 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .label { display: block; font-size: 12px; font-weight: 600; color: var(--sc-text-muted); margin-bottom: 4px; }
  .input { width: 100%; padding: 7px 10px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .snippet { display: block; font-family: var(--sc-font-mono); font-size: 11px; color: var(--sc-accent); background: var(--sc-surface-2); padding: 8px 10px; border-radius: var(--sc-radius); word-break: break-all; }
  .help-text { margin: 0; font-size: 12px; color: var(--sc-text-muted); line-height: 1.6; }
  .help-text code { font-family: var(--sc-font-mono); color: var(--sc-accent); font-size: 11px; }
  .meta-row { margin: 0; font-size: 12px; color: var(--sc-text-muted); }
  .meta-row span { color: var(--sc-text); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
</style>
