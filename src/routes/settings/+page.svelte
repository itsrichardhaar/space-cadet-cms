<script>
  import { onMount } from 'svelte';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

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

    {:else if activeTab === 'ai'}
      <div class="form">
        <p class="hint">Enter your API keys for the Smart Forge feature. Keys are stored encrypted at rest.</p>
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
  .subnav-link--active { background: rgba(124,106,247,.1); color: var(--sc-accent); border-color: rgba(124,106,247,.2); }

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
  .action-badge { font-size: 11px; background: rgba(124,106,247,.12); color: var(--sc-accent); padding: 2px 7px; border-radius: 20px; font-family: var(--sc-font-mono); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
</style>
