<script>
  import { goto } from '$app/navigation';
  import { userStore } from '$lib/stores/user.svelte.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import api from '$lib/api.js';

  let email    = $state('');
  let password = $state('');
  let loading  = $state(false);
  let error    = $state('');

  async function handleSubmit(e) {
    e.preventDefault();
    if (loading) return;
    error   = '';
    loading = true;

    try {
      const res = await api.post('login', { email, password });
      userStore.set(res.data);
      goto('/admin/dashboard');
    } catch (err) {
      error = err.message;
    } finally {
      loading = false;
    }
  }
</script>

<svelte:head><title>Login — Space Cadet CMS</title></svelte:head>

<div class="login-titlebar">
  <div class="login-titlebar-logo">SC</div>
  <span class="login-titlebar-name">SPACE CADET · CMS</span>
</div>

<div class="login-wrap">
  <div class="login-card">
    <div class="login-session-label">SESSION · AUTH</div>
    <h1>Sign in to Space Cadet</h1>
    <p class="subtitle">Precision CMS for teams who ship.</p>

    {#if error}
      <div class="alert alert--error">{error}</div>
    {/if}

    <form onsubmit={handleSubmit}>
      <label class="field">
        <span>EMAIL</span>
        <input
          type="email"
          bind:value={email}
          required
          autocomplete="email"
          placeholder="you@example.com"
          disabled={loading}
        />
      </label>

      <label class="field">
        <span>PASSWORD</span>
        <input
          type="password"
          bind:value={password}
          required
          autocomplete="current-password"
          placeholder="••••••••"
          disabled={loading}
        />
      </label>

      <button type="submit" class="btn-primary" disabled={loading}>
        {loading ? 'Signing in…' : 'Continue →'}
      </button>
    </form>

    <div class="login-status">
      <span class="login-status-dot"></span>
      SECURE · SESSION AUTH
    </div>
  </div>
</div>

<style>
  .login-titlebar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 32px;
    background: var(--sc-surface);
    border-bottom: 1px solid var(--sc-border);
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 14px;
    z-index: 100;
  }

  .login-titlebar-logo {
    width: 18px;
    height: 18px;
    border-radius: 3px;
    background: linear-gradient(180deg, var(--sc-accent), var(--sc-chip-peach));
    color: #1a1814;
    font-size: 8px;
    font-weight: 800;
    font-family: var(--sc-font-mono);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .login-titlebar-name {
    font-family: var(--sc-font-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.1em;
    color: var(--sc-text-muted);
  }

  .login-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--sc-bg);
    padding: 56px 16px 24px;
  }

  .login-card {
    background: var(--sc-surface);
    border: 1px solid var(--sc-border-strong);
    border-radius: var(--sc-radius-lg);
    padding: 36px 32px 24px;
    width: 100%;
    max-width: 380px;
  }

  .login-session-label {
    font-family: var(--sc-font-mono);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0.12em;
    color: var(--sc-accent);
    text-transform: uppercase;
    margin-bottom: 10px;
  }

  h1 {
    margin: 0 0 5px;
    font-size: 20px;
    font-weight: 700;
    color: var(--sc-text);
    letter-spacing: -0.01em;
  }

  .subtitle {
    margin: 0 0 24px;
    color: var(--sc-text-muted);
    font-size: 13px;
  }

  .alert--error {
    background: rgba(192,72,72,.1);
    border: 1px solid rgba(192,72,72,.3);
    border-radius: var(--sc-radius);
    padding: 9px 12px;
    color: var(--sc-danger);
    font-size: 12.5px;
    margin-bottom: 18px;
  }

  .field {
    display: block;
    margin-bottom: 14px;
  }

  .field span {
    display: block;
    font-family: var(--sc-font-mono);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0.1em;
    margin-bottom: 5px;
    color: var(--sc-text-dim);
    text-transform: uppercase;
  }

  .field input {
    width: 100%;
    padding: 8px 10px;
    background: var(--sc-inset);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius);
    color: var(--sc-text);
    font-size: 13.5px;
    outline: none;
    transition: border-color 0.12s;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.12);
  }

  .field input:focus {
    border-color: var(--sc-accent);
  }

  .field input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .btn-primary {
    width: 100%;
    padding: 10px;
    margin-top: 6px;
    background: var(--sc-accent);
    color: #1a1814;
    border: none;
    border-radius: var(--sc-radius);
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.12s;
    letter-spacing: 0.01em;
    box-shadow: 0 1px 3px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.15);
  }

  .btn-primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

  .login-status {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--sc-border);
    font-family: var(--sc-font-mono);
    font-size: 9px;
    letter-spacing: 0.1em;
    color: var(--sc-text-dim);
  }

  .login-status-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--sc-success);
    flex-shrink: 0;
  }
</style>
