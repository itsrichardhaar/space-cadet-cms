<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { onMount } from 'svelte';

  let loading = $state(true);
  let saving  = $state(false);

  // Security settings (map to settings table keys)
  let sessionTtl      = $state('86400');    // seconds (default 24h)
  let contentRateLimit = $state('120');     // requests per minute for public API
  let adminRateLimit  = $state('60');       // requests per minute for admin API
  let allowedOrigins  = $state('');         // comma-separated CORS origins for content API
  let csrfEnabled     = $state(true);       // CSRF token enforcement
  let maxUploadMb     = $state('20');       // Max file upload size in MB
  let loginAttempts   = $state('5');        // Max failed login attempts before lockout
  let lockoutMinutes  = $state('15');       // Lockout duration in minutes

  onMount(loadSettings);

  async function loadSettings() {
    loading = true;
    try {
      const res = await api.get('settings');
      const s = res.data ?? {};
      sessionTtl       = s.session_ttl         ?? '86400';
      contentRateLimit = s.content_rate_limit   ?? '120';
      adminRateLimit   = s.admin_rate_limit      ?? '60';
      allowedOrigins   = s.allowed_origins       ?? '';
      csrfEnabled      = s.csrf_enabled !== 'false';
      maxUploadMb      = s.max_upload_mb         ?? '20';
      loginAttempts    = s.max_login_attempts    ?? '5';
      lockoutMinutes   = s.lockout_minutes       ?? '15';
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  async function save() {
    saving = true;
    try {
      await api.put('settings', {
        session_ttl:         sessionTtl,
        content_rate_limit:  contentRateLimit,
        admin_rate_limit:    adminRateLimit,
        allowed_origins:     allowedOrigins,
        csrf_enabled:        csrfEnabled ? 'true' : 'false',
        max_upload_mb:       maxUploadMb,
        max_login_attempts:  loginAttempts,
        lockout_minutes:     lockoutMinutes,
      });
      notifications.success('Security settings saved.');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  function formatTtl(secs) {
    const n = parseInt(secs);
    if (!n || isNaN(n)) return '';
    if (n < 3600) return `${Math.round(n / 60)} minutes`;
    if (n < 86400) return `${Math.round(n / 3600)} hours`;
    return `${Math.round(n / 86400)} days`;
  }
</script>

<AdminShell title="Settings — Security">
  <!-- Sub-nav -->
  <div class="subnav">
    <a href="/admin/settings" class="subnav-link">General</a>
    <a href="/admin/settings/roles" class="subnav-link">Roles</a>
    <a href="/admin/settings/security" class="subnav-link subnav-link--active">Security</a>
  </div>

  {#if loading}
    <p class="muted">Loading…</p>
  {:else}
    <div class="form">

      <div class="section">
        <h3 class="section-title">Sessions</h3>

        <div class="field">
          <label class="label" for="session-ttl">Session TTL (seconds)</label>
          <div class="input-row">
            <input id="session-ttl" class="input" type="number" min="300" max="2592000" bind:value={sessionTtl} />
            <span class="hint">{formatTtl(sessionTtl)}</span>
          </div>
          <p class="field-hint">How long an authenticated session stays valid without activity.</p>
        </div>
      </div>

      <div class="section">
        <h3 class="section-title">Rate Limits</h3>

        <div class="field">
          <label class="label" for="content-rl">Public content API (requests / minute)</label>
          <input id="content-rl" class="input" type="number" min="1" max="10000" bind:value={contentRateLimit} />
          <p class="field-hint">Applied to unauthenticated GET requests (collections, pages, etc.).</p>
        </div>

        <div class="field">
          <label class="label" for="admin-rl">Admin API (requests / minute)</label>
          <input id="admin-rl" class="input" type="number" min="1" max="10000" bind:value={adminRateLimit} />
          <p class="field-hint">Applied per user session to admin-only endpoints.</p>
        </div>

        <div class="field">
          <label class="label" for="login-attempts">Max login attempts</label>
          <input id="login-attempts" class="input" type="number" min="1" max="100" bind:value={loginAttempts} />
        </div>

        <div class="field">
          <label class="label" for="lockout-min">Lockout duration (minutes)</label>
          <input id="lockout-min" class="input" type="number" min="1" max="1440" bind:value={lockoutMinutes} />
        </div>
      </div>

      <div class="section">
        <h3 class="section-title">CORS</h3>

        <div class="field">
          <label class="label" for="allowed-origins">Allowed origins (public content API)</label>
          <input id="allowed-origins" class="input" type="text" placeholder="https://example.com, https://app.example.com" bind:value={allowedOrigins} />
          <p class="field-hint">Comma-separated list of origins allowed to call the public read-only API. Leave blank to allow any origin (<code>*</code>).</p>
        </div>
      </div>

      <div class="section">
        <h3 class="section-title">Uploads</h3>

        <div class="field">
          <label class="label" for="max-upload">Max file upload size (MB)</label>
          <input id="max-upload" class="input" type="number" min="1" max="500" bind:value={maxUploadMb} />
        </div>
      </div>

      <div class="section">
        <h3 class="section-title">CSRF Protection</h3>

        <label class="toggle-row">
          <span class="label">Enforce CSRF token on write requests</span>
          <div class="toggle" class:toggle--on={csrfEnabled} onclick={() => csrfEnabled = !csrfEnabled} role="switch" aria-checked={csrfEnabled} tabindex="0" onkeydown={e => e.key === ' ' && (csrfEnabled = !csrfEnabled)}>
            <div class="toggle-knob"></div>
          </div>
        </label>
        <p class="field-hint">Disable only if requests originate from an external API client that cannot provide a CSRF token.</p>
      </div>

      <button class="btn btn--primary" onclick={save} disabled={saving}>
        {saving ? 'Saving…' : 'Save Security Settings'}
      </button>
    </div>
  {/if}
</AdminShell>

<style>
  .subnav { display: flex; gap: 4px; margin-bottom: 20px; }
  .subnav-link { padding: 6px 14px; border-radius: var(--sc-radius); font-size: 13px; color: var(--sc-text-muted); text-decoration: none; border: 1px solid transparent; }
  .subnav-link:hover { background: var(--sc-surface-2); color: var(--sc-text); }
  .subnav-link--active { background: rgba(var(--sc-accent-rgb), .1); color: var(--sc-accent); border-color: rgba(var(--sc-accent-rgb), .2); }

  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .hint { font-size: 12px; color: var(--sc-text-muted); }

  .form { max-width: 560px; display: flex; flex-direction: column; gap: 28px; }
  .section { display: flex; flex-direction: column; gap: 16px; }
  .section-title { margin: 0 0 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); border-bottom: 1px solid var(--sc-border); padding-bottom: 6px; }

  .field { display: flex; flex-direction: column; gap: 5px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .input-row { display: flex; align-items: center; gap: 12px; }
  .input-row .input { flex: 1; }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .field-hint { font-size: 12px; color: var(--sc-text-muted); margin: 0; line-height: 1.4; }
  .field-hint code { font-family: var(--sc-font-mono); font-size: 11.5px; background: var(--sc-surface-2); padding: 1px 4px; border-radius: 3px; }

  .toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; cursor: pointer; }
  .toggle { width: 36px; height: 20px; border-radius: 10px; background: var(--sc-border); position: relative; flex-shrink: 0; transition: background .15s; cursor: pointer; }
  .toggle--on { background: var(--sc-accent); }
  .toggle-knob { width: 14px; height: 14px; border-radius: 50%; background: #fff; position: absolute; top: 3px; left: 3px; transition: transform .15s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
  .toggle--on .toggle-knob { transform: translateX(16px); }

  .btn { padding: 8px 18px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
</style>
