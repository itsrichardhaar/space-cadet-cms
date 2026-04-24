<script>
  import { onDestroy, untrack } from 'svelte';
  import { goto, beforeNavigate } from '$app/navigation';
  import { page } from '$app/stores';
  import { EditorState } from '@codemirror/state';
  import { EditorView, basicSetup } from 'codemirror';
  import { css } from '@codemirror/lang-css';
  import { javascript } from '@codemirror/lang-javascript';
  import { oneDark } from '@codemirror/theme-one-dark';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  let assetId = $derived(parseInt($page.params.id));

  let loading   = $state(true);
  let saving    = $state(false);
  let notFound  = $state(false);
  let showDelete = $state(false);

  let name      = $state('');
  let slug      = $state('');
  let type      = $state('css');
  let content   = $state('');
  let updatedAt = $state(null);

  // Unsaved-changes tracking
  let savedSnap = $state('');
  let isDirty = $derived(
    !loading && savedSnap !== '' &&
    JSON.stringify({ name, slug, content }) !== savedSnap
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
    const lang = untrack(() => type) === 'css' ? css() : javascript();
    const v = new EditorView({
      state: EditorState.create({
        doc: untrack(() => content),
        extensions: [
          basicSetup,
          oneDark,
          lang,
          EditorView.updateListener.of(u => {
            if (u.docChanged) {
              fromView = true;
              content = u.state.doc.toString();
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

  // Sync external content changes into the editor (e.g. after load)
  $effect(() => {
    if (!fromView && view && content !== view.state.doc.toString()) {
      view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: content || '' } });
    }
  });

  $effect(() => { void assetId; load(); });

  async function load() {
    loading = true;
    try {
      const res = await api.get(`assets/${assetId}`);
      if (!res.data) { notFound = true; return; }
      const a = res.data;
      name      = a.name;
      slug      = a.slug;
      type      = a.type;
      content   = a.content ?? '';
      updatedAt = a.updated_at;
      savedSnap = JSON.stringify({ name, slug, content });
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
      await api.put(`assets/${assetId}`, { name, slug, content });
      savedSnap = JSON.stringify({ name, slug, content });
      notifications.success('Asset saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function deleteAsset() {
    showDelete = false;
    try {
      await api.delete(`assets/${assetId}`);
      savedSnap = '';
      notifications.success('Asset deleted');
      goto('/admin/assets');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  // Build the public URL for this asset
  let publicUrl = $derived(`/assets/${slug}.${type}`);

  function copyUrl() {
    navigator.clipboard.writeText(publicUrl).then(() => notifications.success('URL copied'));
  }
</script>

<svelte:window onbeforeunload={e => { if (isDirty) { e.preventDefault(); return ''; } }} />

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}<p class="muted"><a href="/admin/assets">Back to Assets</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : name}>
    {#snippet actions()}
      <a href="/admin/assets" class="btn btn--ghost">← Assets</a>
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
          <!-- Editor -->
          <div class="editor-wrap">
            <div bind:this={editorEl} class="editor"></div>
          </div>

          <!-- Sidebar -->
          <aside class="sidebar">
            <div class="card">
              <h3 class="card-title">Details</h3>
              <label class="label">Name</label>
              <input class="input" type="text" bind:value={name} />
              <label class="label" style="margin-top:10px">Filename</label>
              <input class="input" type="text" bind:value={slug} />
              <div class="type-row">
                <span class="label" style="margin:0">Type</span>
                <span class="type-badge type-badge--{type}">{type.toUpperCase()}</span>
              </div>
            </div>

            <div class="card">
              <h3 class="card-title">Include in template</h3>
              {#if type === 'css'}
                <code class="snippet">&lt;link rel="stylesheet" href="{publicUrl}"&gt;</code>
              {:else}
                <code class="snippet">&lt;script src="{publicUrl}"&gt;&lt;/script&gt;</code>
              {/if}
              <button class="copy-btn" onclick={copyUrl}>Copy URL</button>
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
  title="Delete asset"
  message="Delete '{name}'? The file will be removed from the server."
  confirmLabel="Delete"
  danger={true}
  onconfirm={deleteAsset}
  oncancel={() => showDelete = false}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .dirty-badge { font-size: 11px; color: var(--sc-text-muted); padding: 4px 8px; }
  .layout { display: grid; grid-template-columns: 1fr 220px; gap: 20px; align-items: start; }
  .editor-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .editor { min-height: 560px; }
  :global(.cm-editor) { font-size: 13px; }
  :global(.cm-editor.cm-focused) { outline: none; }
  :global(.cm-scroller) { min-height: 560px; }
  .sidebar { display: flex; flex-direction: column; gap: 14px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 16px; }
  .card--meta { padding: 12px 16px; }
  .card-title { margin: 0 0 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .label { display: block; font-size: 12px; font-weight: 600; color: var(--sc-text-muted); margin-bottom: 4px; }
  .input { width: 100%; padding: 7px 10px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .type-row { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; }
  .type-badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
  .type-badge--css { background: rgba(96,165,250,.12); color: var(--sc-info); }
  .type-badge--js  { background: rgba(251,191,36,.12); color: #f59e0b; }
  .snippet { display: block; font-family: var(--sc-font-mono); font-size: 11px; color: var(--sc-text-muted); background: var(--sc-surface-2); padding: 8px 10px; border-radius: var(--sc-radius); word-break: break-all; line-height: 1.5; margin-bottom: 10px; }
  .copy-btn { width: 100%; padding: 6px; background: transparent; border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text-muted); font-size: 12px; font-weight: 600; cursor: pointer; }
  .copy-btn:hover { color: var(--sc-accent); border-color: var(--sc-accent); }
  .meta-row { margin: 0 0 4px; font-size: 12px; color: var(--sc-text-muted); }
  .meta-row span { color: var(--sc-text); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
</style>
