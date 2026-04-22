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

<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
        <circle cx="18" cy="18" r="18" fill="#7c6af7"/>
        <path d="M10 24 L18 12 L26 24" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <circle cx="18" cy="12" r="2.5" fill="white"/>
      </svg>
      <span>Space Cadet CMS</span>
    </div>

    <h1>Welcome back</h1>
    <p class="subtitle">Sign in to your admin panel</p>

    {#if error}
      <div class="alert alert--error">{error}</div>
    {/if}

    <form onsubmit={handleSubmit}>
      <label class="field">
        <span>Email address</span>
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
        <span>Password</span>
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
        {loading ? 'Signing in…' : 'Sign in'}
      </button>
    </form>
  </div>
</div>

<style>
  .login-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--sc-bg);
    padding: 24px 16px;
  }

  .login-card {
    background: var(--sc-surface);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius-lg);
    padding: 40px;
    width: 100%;
    max-width: 400px;
  }

  .login-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 28px;
    font-size: 16px;
    font-weight: 600;
    color: var(--sc-text);
  }

  h1 {
    margin: 0 0 6px;
    font-size: 22px;
    font-weight: 700;
    color: var(--sc-text);
  }

  .subtitle {
    margin: 0 0 28px;
    color: var(--sc-text-muted);
    font-size: 14px;
  }

  .alert--error {
    background: rgba(248,113,113,.1);
    border: 1px solid rgba(248,113,113,.3);
    border-radius: var(--sc-radius);
    padding: 10px 14px;
    color: var(--sc-danger);
    font-size: 13px;
    margin-bottom: 20px;
  }

  .field {
    display: block;
    margin-bottom: 16px;
  }

  .field span {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--sc-text);
  }

  .field input {
    width: 100%;
    padding: 10px 12px;
    background: var(--sc-surface-2);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius);
    color: var(--sc-text);
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s;
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
    padding: 11px;
    margin-top: 8px;
    background: var(--sc-accent);
    color: #fff;
    border: none;
    border-radius: var(--sc-radius);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
  }

  .btn-primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
