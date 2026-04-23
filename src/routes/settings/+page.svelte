<script>
  import { onMount } from 'svelte';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import { themeStore } from '$lib/stores/theme.svelte.js';

  let loading    = $state(true);
  let saving     = $state(false);
  let activeTab  = $state('general');
  let auditLog   = $state([]);
  let auditLoading = $state(false);

  // General settings
  let siteName   = $state('');
  let siteUrl    = $state('');
  let timezone   = $state('UTC');
  let fromEmail  = $state('');

  // AI provider keys
  let claudeKey  = $state('');
  let openaiKey  = $state('');
  let geminiKey  = $state('');

  // Appearance — local mirrors of themeStore (synced via $effect after init)
  let themeHue = $state(themeStore.hue);
  let themeBri = $state(themeStore.brightness);
  let themeInt = $state(themeStore.intensity);

  // Sync local state when store initialises (layout onMount runs first)
  $effect(() => {
    themeHue = themeStore.hue;
    themeBri = themeStore.brightness;
    themeInt = themeStore.intensity;
  });

  let isDefaultAppearance = $derived(themeHue === 38 && themeBri === 100 && themeInt === 0);

  function resetAppearance() {
    themeStore.reset();
  }

  onMount(loadSettings);

  async function loadSettings() {
    loading = true;
    try {
      const res = await api.get('settings');
      const s = res.data ?? {};
      siteName  = s.site_name   ?? '';
      siteUrl   = s.site_url    ?? '';
      timezone  = s.timezone    ?? 'UTC';
      fromEmail = s.from_email  ?? '';
      claudeKey = s.claude_api_key  ? '••••••••' : '';
      openaiKey = s.openai_api_key  ? '••••••••' : '';
      geminiKey = s.gemini_api_key  ? '••••••••' : '';
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  async function saveGeneral() {
    saving = true;
    try {
      await api.put('settings', {
        site_name: siteName,
        site_url: siteUrl,
        timezone,
        from_email: fromEmail,
      });
      notifications.success('Settings saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function saveAiKeys() {
    saving = true;
    try {
      const body = {};
      if (claudeKey && claudeKey !== '••••••••') body.claude_api_key = claudeKey;
      if (openaiKey && openaiKey !== '••••••••') body.openai_api_key = openaiKey;
      if (geminiKey && geminiKey !== '••••••••') body.gemini_api_key = geminiKey;
      if (Object.keys(body).length) await api.put('settings', body);
      notifications.success('API keys saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function loadAuditLog() {
    auditLoading = true;
    try {
      const res = await api.get('audit-log');
      auditLog = res.data ?? [];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      auditLoading = false;
    }
  }

  function switchTab(tab) {
    activeTab = tab;
    if (tab === 'audit' && auditLog.length === 0) loadAuditLog();
  }
</script>

<AdminShell title="Settings">
  {#snippet children()}
    <!-- Sub-nav -->
    <div class="subnav">
      <a href="/admin/settings" class="subnav-link subnav-link--active">General</a>
      <a href="/admin/settings/roles" class="subnav-link">Roles</a>
      <a href="/admin/settings/security" class="subnav-link">Security</a>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      {#each [['general','General'],['ai','AI Keys'],['audit','Audit Log']] as [key, label]}
        <button class="tab" class:tab--active={activeTab === key} onclick={() => switchTab(key)}>{label}</button>
      {/each}
    </div>

    {#if loading}
      <p class="muted">Loading…</p>
    {:else if activeTab === 'general'}
      <div class="form">
        <div class="section">
          <h3 class="section-title">Site</h3>
          <div class="field">
            <label class="label">Site name</label>
            <input class="input" type="text" bind:value={siteName} placeholder="My Website" />
          </div>
          <div class="field">
            <label class="label">Site URL</label>
            <input class="input" type="url" bind:value={siteUrl} placeholder="https://example.com" />
          </div>
          <div class="field">
            <label class="label">Timezone</label>
            <input class="input" type="text" bind:value={timezone} placeholder="UTC" />
          </div>
          <div class="field">
            <label class="label">From email</label>
            <input class="input" type="email" bind:value={fromEmail} placeholder="noreply@example.com" />
          </div>
        </div>
        <button class="btn btn--primary" onclick={saveGeneral} disabled={saving}>
          {saving ? 'Saving…' : 'Save Settings'}
        </button>
      </div>

      <!-- Appearance -->
      <div class="form">
        <div class="section">
          <h3 class="section-title">Appearance</h3>

          <!-- Live palette preview strip -->
          <div class="palette-preview" aria-hidden="true">
            <div class="pp-chip" style="background:var(--sc-inset)"></div>
            <div class="pp-chip" style="background:var(--sc-bg)"></div>
            <div class="pp-chip" style="background:var(--sc-surface)"></div>
            <div class="pp-chip" style="background:var(--sc-surface-2)"></div>
            <div class="pp-chip pp-chip--accent" style="background:var(--sc-accent)"></div>
          </div>

          <div class="field">
            <label class="label">Theme</label>
            <div class="theme-picker">
              {#each [['mid', 'Mid'], ['dark', 'Dark'], ['light', 'Light']] as [val, lbl]}
                <button
                  class="theme-btn"
                  class:theme-btn--active={themeStore.current === val}
                  onclick={() => themeStore.set(val)}
                >
                  <div class="theme-swatch theme-swatch--{val}"></div>
                  <span>{lbl}</span>
                </button>
              {/each}
            </div>
          </div>

          <div class="field">
            <div class="slider-header">
              <label class="label">Brightness</label>
              <span class="slider-val">{themeBri}%</span>
            </div>
            <input
              class="slider slider--brightness"
              type="range" min="50" max="150"
              bind:value={themeBri}
              oninput={() => themeStore.setBri(themeBri)}
            />
          </div>

          <div class="field">
            <div class="slider-header">
              <label class="label">Color Intensity</label>
              <span class="slider-val">{themeInt}%</span>
            </div>
            <input
              class="slider slider--intensity"
              type="range" min="0" max="100"
              bind:value={themeInt}
              oninput={() => themeStore.setInt(themeInt)}
            />
          </div>

          <div class="field">
            <div class="slider-header">
              <label class="label">Color Hue</label>
              <span class="slider-val">{themeHue}°</span>
            </div>
            <input
              class="slider slider--hue"
              type="range" min="0" max="359"
              bind:value={themeHue}
              oninput={() => themeStore.setHue(themeHue)}
            />
          </div>

          {#if !isDefaultAppearance}
            <button class="btn-reset" onclick={resetAppearance}>Reset to defaults</button>
          {/if}
        </div>
      </div>

    {:else if activeTab === 'ai'}
      <div class="form">
        <p class="hint">Enter your API keys for the Blueprint AI feature. Keys are stored encrypted at rest.</p>
        <div class="section">
          <div class="field">
            <label class="label">Claude (Anthropic) API key</label>
            <input class="input" type="password" bind:value={claudeKey} placeholder="sk-ant-…" />
          </div>
          <div class="field">
            <label class="label">OpenAI API key</label>
            <input class="input" type="password" bind:value={openaiKey} placeholder="sk-…" />
          </div>
          <div class="field">
            <label class="label">Google Gemini API key</label>
            <input class="input" type="password" bind:value={geminiKey} placeholder="AIza…" />
          </div>
        </div>
        <button class="btn btn--primary" onclick={saveAiKeys} disabled={saving}>
          {saving ? 'Saving…' : 'Save Keys'}
        </button>
      </div>

    {:else if activeTab === 'audit'}
      {#if auditLoading}
        <p class="muted">Loading…</p>
      {:else if auditLog.length === 0}
        <p class="muted">No audit log entries yet.</p>
      {:else}
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th></tr>
            </thead>
            <tbody>
              {#each auditLog as entry (entry.id)}
                <tr>
                  <td class="muted-cell">{formatDate(entry.created_at)}</td>
                  <td class="muted-cell">{entry.user_name ?? '—'}</td>
                  <td><span class="action-badge">{entry.action}</span></td>
                  <td class="muted-cell">{entry.entity_type}:{entry.entity_id}</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    {/if}
  {/snippet}
</AdminShell>

<style>
  .subnav { display: flex; gap: 4px; margin-bottom: 20px; }
  .subnav-link { padding: 6px 14px; border-radius: var(--sc-radius); font-size: 13px; color: var(--sc-text-muted); text-decoration: none; border: 1px solid transparent; }
  .subnav-link:hover { background: var(--sc-surface-2); color: var(--sc-text); }
  .subnav-link--active { background: rgba(var(--sc-accent-rgb), .1); color: var(--sc-accent); border-color: rgba(var(--sc-accent-rgb), .2); }

  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid var(--sc-border); }
  .tab { background: none; border: none; padding: 8px 16px; font-size: 13px; color: var(--sc-text-muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; }
  .tab--active { color: var(--sc-accent); border-bottom-color: var(--sc-accent); }
  .tab:hover { color: var(--sc-text); }
  .form { max-width: 520px; display: flex; flex-direction: column; gap: 20px; }
  .section { display: flex; flex-direction: column; gap: 14px; }
  .section-title { margin: 0 0 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .hint { font-size: 12px; color: var(--sc-text-muted); margin: 0; }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .table-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; }
  .table thead th { padding: 10px 16px; background: var(--sc-surface-2); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); text-align: left; border-bottom: 1px solid var(--sc-border); }
  .table tbody tr { border-bottom: 1px solid var(--sc-border); }
  .table tbody tr:last-child { border-bottom: none; }
  .table tbody tr:hover { background: var(--sc-surface-2); }
  .table td { padding: 10px 16px; font-size: 13px; }
  .muted-cell { color: var(--sc-text-muted); font-size: 12px; }
  .action-badge { font-size: 11px; background: rgba(var(--sc-accent-rgb), .12); color: var(--sc-accent); padding: 2px 7px; border-radius: 20px; font-family: var(--sc-font-mono); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #1a1814; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }

  /* ── Palette preview strip ───────────────────── */
  .palette-preview {
    display: flex; gap: 0; height: 6px;
    border-radius: var(--sc-radius); overflow: hidden;
    border: 1px solid var(--sc-border);
    margin-bottom: 4px;
  }
  .pp-chip { flex: 1; }
  .pp-chip--accent { flex: 0 0 28%; }

  /* ── Theme picker ────────────────────────────── */
  .theme-picker { display: flex; gap: 8px; }
  .theme-btn {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 8px 14px; background: var(--sc-surface-2);
    border: 1px solid var(--sc-border); border-radius: var(--sc-radius);
    cursor: pointer; font-size: 11px; font-weight: 500;
    font-family: var(--sc-font-mono); color: var(--sc-text-muted);
    transition: border-color 0.1s, color 0.1s; letter-spacing: 0.04em;
  }
  .theme-btn:hover { border-color: var(--sc-border-strong); color: var(--sc-text); }
  .theme-btn--active { border-color: var(--sc-accent); color: var(--sc-accent); }
  .theme-swatch { width: 44px; height: 22px; border-radius: 2px; border: 1px solid rgba(0,0,0,0.2); }
  .theme-swatch--mid   { background: #888480; }
  .theme-swatch--dark  { background: #2a2825; }
  .theme-swatch--light { background: #c6c2b6; }

  /* ── Appearance sliders ──────────────────────── */
  .slider-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 7px;
  }
  .slider-val {
    font-size: 11px; font-family: var(--sc-font-mono);
    color: var(--sc-text-muted); min-width: 34px; text-align: right;
  }

  .slider {
    -webkit-appearance: none;
    appearance: none;
    width: 100%; height: 5px;
    border-radius: 3px; outline: none; border: none;
    cursor: ew-resize; display: block;
  }
  .slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--sc-text);
    border: 2px solid var(--sc-bg);
    cursor: ew-resize;
    box-shadow: 0 1px 4px rgba(0,0,0,0.30);
  }
  .slider::-moz-range-thumb {
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--sc-text);
    border: 2px solid var(--sc-bg);
    cursor: ew-resize;
    box-shadow: 0 1px 4px rgba(0,0,0,0.30);
  }
  .slider:focus-visible::-webkit-slider-thumb {
    outline: 2px solid var(--sc-accent); outline-offset: 2px;
  }

  /* Brightness: dark inset → light surface gradient */
  .slider--brightness {
    background: linear-gradient(to right, #3a3733, #e8e3db);
  }
  /* Intensity: neutral surface → live accent color */
  .slider--intensity {
    background: linear-gradient(to right, var(--sc-surface-2), var(--sc-accent));
  }
  /* Hue: full rainbow */
  .slider--hue {
    background: linear-gradient(to right,
      hsl(0,80%,55%), hsl(30,80%,55%), hsl(60,80%,55%), hsl(90,80%,55%),
      hsl(120,80%,55%), hsl(150,80%,55%), hsl(180,80%,55%), hsl(210,80%,55%),
      hsl(240,80%,55%), hsl(270,80%,55%), hsl(300,80%,55%), hsl(330,80%,55%),
      hsl(360,80%,55%));
  }

  .btn-reset {
    background: none; border: none; padding: 0;
    font-size: 12px; font-family: var(--sc-font);
    color: var(--sc-text-muted); cursor: pointer;
    text-decoration: underline; text-underline-offset: 2px;
    align-self: flex-start;
  }
  .btn-reset:hover { color: var(--sc-text); }
</style>
