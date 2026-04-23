<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import api        from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { onMount } from 'svelte';
  import Select from '$lib/components/common/Select.svelte';

  const PROVIDER_OPTS = [
    { value: 'claude', label: 'Claude (Anthropic)' },
    { value: 'openai', label: 'GPT-4o (OpenAI)' },
    { value: 'gemini', label: 'Gemini 1.5 Pro (Google)' },
  ];

  const applyOptions = $derived([
    { value: 'page', label: 'New Page' },
    ...collections.map(c => ({ value: c.slug, label: `Collection: ${c.name}` })),
  ]);

  // ── State ─────────────────────────────────────────────────────────────
  let html         = $state('');
  let instructions = $state('');
  let provider     = $state('claude');
  let loading      = $state(false);
  let result       = $state(null);  // AI result: { title, fields, suggested_field_defs }
  let error        = $state('');

  // Apply-to target
  let applyTarget  = $state('page');   // 'page' or a collection slug
  let collections  = $state([]);
  let applying     = $state(false);
  let applied      = $state(null);     // { type, id, url }

  onMount(async () => {
    try {
      const res = await api.get('collections');
      collections = res.data ?? [];
    } catch {}
  });

  let jobId = $state(null);

  // ── Submit ─────────────────────────────────────────────────────────────
  async function submit() {
    if (!html.trim()) { error = 'Paste some HTML first.'; return; }
    error = ''; result = null; applied = null; jobId = null;
    loading = true;
    try {
      const res = await api.post('blueprint/analyze', { html, provider, instructions });
      const d = res.data ?? {};
      jobId  = d.job_id ?? null;
      result = d.result ?? d;
    } catch (e) {
      error = e.message ?? 'Analysis failed.';
    } finally {
      loading = false;
    }
  }

  // ── Apply ──────────────────────────────────────────────────────────────
  async function applyResult() {
    if (!result || !jobId) return;
    applying = true;
    error = '';
    try {
      const target = applyTarget === 'page' ? 'page' : applyTarget;
      const res = await api.post(`blueprint/jobs/${jobId}/apply`, { target });
      const d = res.data ?? {};
      if (d.type === 'page') {
        applied = { type: 'page', id: d.id, url: `/admin/pages/${d.id}` };
      } else {
        applied = { type: 'collection_item', id: d.id, url: `/admin/collections/${d.collection}/${d.id}` };
      }
      notifications.success('Content applied successfully!');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      applying = false;
    }
  }

  // ── Helpers ────────────────────────────────────────────────────────────
  function fieldTypeColor(type) {
    const map = { text: 'info', textarea: 'info', richtext: 'warning',
                  image: 'success', number: 'accent', toggle: 'accent' };
    return map[type] ?? 'muted';
  }

  function copyJson() {
    navigator.clipboard.writeText(JSON.stringify(result, null, 2));
    notifications.success('JSON copied to clipboard.');
  }

  // Reset
  function reset() { html = ''; instructions = ''; result = null; error = ''; applied = null; jobId = null; }
</script>

<AdminShell title="Blueprint AI">
  <div class="blueprint-layout">
    <!-- Left: input panel -->
    <div class="input-panel">
      <div class="section-header">
        <h2 class="section-title">Paste HTML</h2>
        <p class="section-desc">Paste the raw HTML of any webpage. Blueprint AI will extract editable content regions and suggest a field schema.</p>
      </div>

      <textarea
        class="html-input"
        placeholder="<html>…</html>"
        bind:value={html}
        rows="16"
      ></textarea>

      <div class="options-row">
        <div class="field-group">
          <label class="field-label" for="provider">AI Provider</label>
          <Select id="provider" bind:value={provider} options={PROVIDER_OPTS} />
        </div>

        <div class="field-group" style="flex:2">
          <label class="field-label" for="instructions">Additional instructions (optional)</label>
          <input
            id="instructions"
            class="text-input"
            type="text"
            placeholder="e.g. Focus on hero section only, ignore sidebar…"
            bind:value={instructions}
          />
        </div>
      </div>

      {#if error}
        <div class="error-box">{error}</div>
      {/if}

      <div class="btn-row">
        <button class="btn-primary" onclick={submit} disabled={loading || !html.trim()}>
          {loading ? 'Analyzing…' : '✦ Analyze with AI'}
        </button>
        {#if result || error}
          <button class="btn-ghost" onclick={reset}>Reset</button>
        {/if}
      </div>
    </div>

    <!-- Right: result panel -->
    <div class="result-panel">
      {#if loading}
        <div class="placeholder">
          <div class="spinner"></div>
          <p>Sending to {provider === 'claude' ? 'Claude' : provider === 'openai' ? 'GPT-4o' : 'Gemini'}…</p>
        </div>
      {:else if !result}
        <div class="placeholder">
          <span class="placeholder-icon">✦</span>
          <p>Results will appear here after analysis.</p>
        </div>
      {:else}
        <!-- Detected title -->
        <div class="result-section">
          <div class="result-section-header">
            <span class="result-label">Detected title</span>
          </div>
          <div class="result-title">{result.title ?? '—'}</div>
        </div>

        <!-- Extracted fields -->
        {#if result.fields && Object.keys(result.fields).length > 0}
          <div class="result-section">
            <div class="result-section-header">
              <span class="result-label">Extracted content</span>
              <span class="result-count">{Object.keys(result.fields).length} fields</span>
            </div>
            <div class="fields-list">
              {#each Object.entries(result.fields) as [key, val]}
                <div class="field-row">
                  <span class="field-key">{key}</span>
                  <span class="field-val">{String(val).slice(0, 120)}{String(val).length > 120 ? '…' : ''}</span>
                </div>
              {/each}
            </div>
          </div>
        {/if}

        <!-- Suggested field definitions -->
        {#if result.suggested_field_defs?.length}
          <div class="result-section">
            <div class="result-section-header">
              <span class="result-label">Suggested field schema</span>
            </div>
            <div class="defs-list">
              {#each result.suggested_field_defs as fd}
                <div class="def-row">
                  <span class="def-name">{fd.name}</span>
                  <span class="def-key muted">{fd.key}</span>
                  <span class="type-badge type-badge--{fieldTypeColor(fd.type)}">{fd.type}</span>
                </div>
              {/each}
            </div>
          </div>
        {/if}

        <!-- Apply section -->
        {#if jobId}
          <div class="result-section apply-section">
            <div class="result-section-header">
              <span class="result-label">Apply to CMS</span>
            </div>
            {#if applied}
              <div class="applied-box">
                <span class="applied-icon">✓</span>
                <span>Content created as <strong>{applied.type === 'page' ? 'Page' : 'Collection Item'}</strong></span>
                <a href={applied.url} class="applied-link">Open →</a>
              </div>
            {:else}
              <div class="apply-row">
                <Select bind:value={applyTarget} options={applyOptions} />
                <button class="btn-primary" onclick={applyResult} disabled={applying}>
                  {applying ? 'Applying…' : 'Apply'}
                </button>
              </div>
            {/if}
          </div>
        {/if}

        <!-- Copy JSON -->
        <div class="result-footer">
          <button class="btn-ghost btn-sm" onclick={copyJson}>Copy JSON</button>
        </div>
      {/if}
    </div>
  </div>
</AdminShell>

<style>
  .blueprint-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }
  @media (max-width: 900px) { .blueprint-layout { grid-template-columns: 1fr; } }

  /* Input panel */
  .input-panel { display: flex; flex-direction: column; gap: 16px; }
  .section-header { }
  .section-title { font-size: 15px; font-weight: 600; color: var(--sc-text); margin: 0 0 4px; }
  .section-desc { font-size: 13px; color: var(--sc-text-muted); margin: 0; line-height: 1.5; }

  .html-input {
    width: 100%; box-sizing: border-box;
    background: var(--sc-surface); border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius); padding: 12px;
    font-family: var(--sc-font-mono); font-size: 12px;
    color: var(--sc-text); resize: vertical; line-height: 1.5;
  }
  .html-input:focus { outline: none; border-color: var(--sc-accent); }

  .options-row { display: flex; gap: 12px; flex-wrap: wrap; }
  .field-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
  .field-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .select, .text-input {
    background: var(--sc-surface); border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius); padding: 7px 10px; font-size: 13px;
    color: var(--sc-text); width: 100%; box-sizing: border-box;
  }
  .select { appearance: none; cursor: pointer; }
  .select:focus, .text-input:focus { outline: none; border-color: var(--sc-accent); }

  .error-box { background: rgba(220,60,60,.1); border: 1px solid var(--sc-danger); border-radius: var(--sc-radius); padding: 10px 14px; font-size: 13px; color: var(--sc-danger); }

  .btn-row { display: flex; gap: 10px; align-items: center; }
  .btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: var(--sc-accent); color: #fff; border-radius: var(--sc-radius); font-size: 13.5px; font-weight: 600; border: none; cursor: pointer; transition: background .15s; }
  .btn-primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn-ghost { display: inline-flex; align-items: center; padding: 8px 14px; border: 1px solid var(--sc-border); color: var(--sc-text-muted); border-radius: var(--sc-radius); font-size: 13.5px; background: none; cursor: pointer; }
  .btn-ghost:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .btn-sm { font-size: 12px; padding: 5px 12px; }

  /* Result panel */
  .result-panel {
    background: var(--sc-surface); border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius-lg); min-height: 400px;
    display: flex; flex-direction: column;
  }

  .placeholder {
    flex: 1; display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 12px; padding: 48px; color: var(--sc-text-muted);
    font-size: 13px; text-align: center;
  }
  .placeholder-icon { font-size: 36px; opacity: .3; }
  .spinner {
    width: 32px; height: 32px; border: 3px solid var(--sc-border);
    border-top-color: var(--sc-accent); border-radius: 50%;
    animation: spin .8s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .result-section { border-bottom: 1px solid var(--sc-border); padding: 14px 18px; }
  .result-section:last-child { border-bottom: none; }
  .result-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
  .result-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .05em; }
  .result-count { font-size: 11px; color: var(--sc-text-muted); }
  .result-title { font-size: 15px; font-weight: 600; color: var(--sc-text); }

  .fields-list { display: flex; flex-direction: column; gap: 6px; }
  .field-row { display: grid; grid-template-columns: 140px 1fr; gap: 10px; font-size: 13px; align-items: start; }
  .field-key { font-family: var(--sc-font-mono); font-size: 11.5px; color: var(--sc-accent); background: rgba(var(--sc-accent-rgb), .1); border-radius: 4px; padding: 2px 6px; white-space: nowrap; }
  .field-val { color: var(--sc-text); line-height: 1.4; word-break: break-word; }

  .defs-list { display: flex; flex-direction: column; gap: 6px; }
  .def-row { display: flex; align-items: center; gap: 10px; font-size: 13px; }
  .def-name { font-weight: 500; color: var(--sc-text); flex: 1; }
  .def-key { font-family: var(--sc-font-mono); font-size: 11.5px; }
  .muted { color: var(--sc-text-muted); }

  .type-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; text-transform: lowercase; }
  .type-badge--info    { background: rgba(60,140,220,.15); color: var(--sc-info); }
  .type-badge--warning { background: rgba(220,160,40,.15); color: var(--sc-warning); }
  .type-badge--success { background: rgba(40,180,100,.15); color: var(--sc-success); }
  .type-badge--accent  { background: rgba(var(--sc-accent-rgb), .15); color: var(--sc-accent); }
  .type-badge--muted   { background: var(--sc-surface-2); color: var(--sc-text-muted); }

  .apply-section { }
  .apply-row { display: flex; gap: 10px; align-items: center; }
  .apply-row .select { flex: 1; }

  .applied-box { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--sc-success); background: rgba(40,180,100,.1); border-radius: var(--sc-radius); padding: 10px 14px; }
  .applied-icon { font-size: 16px; }
  .applied-link { margin-left: auto; color: var(--sc-accent); text-decoration: none; font-weight: 600; }
  .applied-link:hover { text-decoration: underline; }

  .result-footer { padding: 12px 18px; display: flex; justify-content: flex-end; }
</style>
