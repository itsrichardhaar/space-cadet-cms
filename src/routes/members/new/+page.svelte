<script>
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import Select from '$lib/components/common/Select.svelte';

  const ROLE_OPTS = [
    { value: 'free_member', label: 'Free member' },
    { value: 'paid_member', label: 'Paid member' },
  ];

  let email    = $state('');
  let name     = $state('');
  let password = $state('');
  let role     = $state('free_member');
  let saving   = $state(false);

  async function save() {
    if (!email.trim() || !name.trim() || !password) {
      notifications.error('Email, name, and password are required');
      return;
    }
    saving = true;
    try {
      const res = await api.post('members', { email: email.trim(), display_name: name.trim(), password, role });
      notifications.success('Member created');
      goto(`/admin/members/${res.data.id}`);
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }
</script>

<AdminShell title="New Member">
  {#snippet actions()}
    <a href="/admin/members" class="btn btn--ghost">Cancel</a>
    <button class="btn btn--primary" onclick={save} disabled={saving}>
      {saving ? 'Creating…' : 'Create Member'}
    </button>
  {/snippet}

  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Display name <span class="req">*</span></label>
        <input class="input" type="text" bind:value={name} placeholder="Full name" />
      </div>
      <div class="field">
        <label class="label">Email <span class="req">*</span></label>
        <input class="input" type="email" bind:value={email} placeholder="user@example.com" />
      </div>
      <div class="field">
        <label class="label">Password <span class="req">*</span></label>
        <input class="input" type="password" bind:value={password} placeholder="Min 8 characters" />
      </div>
      <div class="field">
        <label class="label">Role</label>
        <Select bind:value={role} options={ROLE_OPTS} />
      </div>
    </div>
  {/snippet}
</AdminShell>

<style>
  .form { max-width: 480px; display: flex; flex-direction: column; gap: 20px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 14px; outline: none; }
  .input:focus { border-color: var(--sc-accent); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
</style>
