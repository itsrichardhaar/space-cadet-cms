<script>
  import { onMount, onDestroy } from 'svelte';
  import { goto } from '$app/navigation';
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

  let editorEl;
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

  onMount(() => {
    view = new EditorView({
      state: EditorState.create({
        doc: source,
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
      goto('/templates');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}<p class="muted"><a href="/templates">Back to Templates</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : name}>
    {#snippet actions()}
      <a href="/templates" class="btn btn--ghost">← Templates</a>
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
              <select class="input" bind:value={type}>
                <option value="page">Page</option>
                <option value="partial">Partial</option>
                <option value="layout">Layout</option>
              </select>
            </div>
            {#if updatedAt}
              <div class="card card--meta">
                <p class="meta-row">Updated: <span>{formatDate(updatedAt)}</span></p>
              </div>
            {/if}
            <div class="card card--help">
              <h3 class="card-title">Syntax</h3>
              <p class="help-text">Use <code>{'{{ variable }}'}</code> for output, <code>data-sc-repeat="items"</code> for loops, and <code>data-sc-if="condition"</code> for conditionals.</p>
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
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
</style>
