<script>
  import { onMount } from 'svelte';
  import { goto, beforeNavigate } from '$app/navigation';
  import { page } from '$app/stores';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import StatusBadge from '$lib/components/common/StatusBadge.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';
  import Select from '$lib/components/common/Select.svelte';

  const ROLE_OPTS = [
    { value: 'free_member', label: 'Free member' },
    { value: 'paid_member', label: 'Paid member' },
  ];
  const STATUS_OPTS = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
  ];

  let memberId = $derived(parseInt($page.params.id));

  let loading   = $state(true);
  let saving    = $state(false);
  let notFound  = $state(false);
  let showDelete = $state(false);

  let email     = $state('');
  let dispName  = $state('');
  let role      = $state('free_member');
  let status    = $state('active');
  let lastLogin = $state(null);
  let createdAt = $state(null);

  // Unsaved-changes tracking
  let savedSnap = $state('');
  let isDirty = $derived(
    !loading && savedSnap !== '' && JSON.stringify({ role, status }) !== savedSnap
  );

  beforeNavigate(({ cancel }) => {
    if (isDirty && !confirm('You have unsaved changes. Leave anyway?')) cancel();
  });

  $effect(() => { void memberId; load(); });

  async function load() {
    loading = true;
    try {
      const res = await api.get(`members/${memberId}`);
      if (!res.data) { notFound = true; return; }
      const m = res.data;
      email    = m.email;
      dispName = m.display_name;
      role     = m.role;
      status   = m.status ?? 'active';
      lastLogin = m.last_login_at;
      createdAt = m.created_at;
      savedSnap = JSON.stringify({ role, status });
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
      await api.put(`members/${memberId}`, { role, status });
      savedSnap = JSON.stringify({ role, status });
      notifications.success('Member updated');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function deleteMember() {
    showDelete = false;
    try {
      await api.delete(`members/${memberId}`);
      savedSnap = '';
      notifications.success('Member deleted');
      goto('/admin/members');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

<svelte:window onbeforeunload={e => { if (isDirty) { e.preventDefault(); return ''; } }} />

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}<p class="muted"><a href="/admin/members">Back to Members</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : dispName}>
    {#snippet actions()}
      <a href="/admin/members" class="btn btn--ghost">← All Members</a>
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
          <div class="card">
            <h3 class="card-title">Member Details</h3>
            <div class="field-row">
              <label class="label">Email</label>
              <input class="input input--readonly" type="email" value={email} readonly />
            </div>
            <div class="field-row">
              <label class="label">Display name</label>
              <input class="input input--readonly" type="text" value={dispName} readonly />
            </div>
            <div class="field-row">
              <label class="label">Role</label>
              <Select bind:value={role} options={ROLE_OPTS} />
            </div>
            <div class="field-row">
              <label class="label">Status</label>
              <Select bind:value={status} options={STATUS_OPTS} />
            </div>
          </div>

          <div class="card card--meta">
            <h3 class="card-title">Activity</h3>
            <p class="meta-row">Member since: <span>{formatDate(createdAt)}</span></p>
            <p class="meta-row">Last login: <span>{lastLogin ? formatDate(lastLogin) : 'Never'}</span></p>
          </div>
        </div>
      {/if}
    {/snippet}
  </AdminShell>
{/if}

<ConfirmDialog
  open={showDelete}
  title="Delete member"
  message="Delete '{dispName}'? This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={deleteMember}
  oncancel={() => showDelete = false}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .dirty-badge { font-size: 11px; color: var(--sc-text-muted); padding: 4px 8px; }
  .layout { display: flex; flex-direction: column; gap: 16px; max-width: 520px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 18px; }
  .card--meta { padding: 16px 18px; }
  .card-title { margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .field-row { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
  .field-row:last-child { margin-bottom: 0; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); display: block; }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .input--readonly { opacity: .7; cursor: not-allowed; }
  .meta-row { margin: 0 0 8px; font-size: 13px; color: var(--sc-text-muted); }
  .meta-row span { color: var(--sc-text); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
</style>
