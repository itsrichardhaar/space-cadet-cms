<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import Modal from '$lib/components/common/Modal.svelte';
  import StatusBadge from '$lib/components/common/StatusBadge.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  let hooks      = $state([]);
  let loading    = $state(true);
  let deleteItem = $state(null);
  let showCreate = $state(false);

  let newName    = $state('');
  let newUrl     = $state('');
  let creating   = $state(false);

  onMount(loadHooks);

  async function loadHooks() {
    loading = true;
    try {
      const res = await api.get('webhooks');
      hooks = res.data ?? [];
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  async function create() {
    if (!newName.trim() || !newUrl.trim()) { notifications.error('Name and URL are required'); return; }
    creating = true;
    try {
      const res = await api.post('webhooks', { name: newName.trim(), url: newUrl.trim(), events: [], is_active: 1 });
      notifications.success('Webhook created');
      goto(`/admin/webhooks/${res.data.id}`);
    } catch (e) {
      notifications.error(e.message);
    } finally {
      creating = false;
    }
  }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`webhooks/${item.id}`);
      hooks = hooks.filter(h => h.id !== item.id);
      notifications.success('Webhook deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  function statusColor(code) {
    if (!code) return 'var(--sc-text-muted)';
    if (code >= 200 && code < 300) return 'var(--sc-success)';
    return 'var(--sc-danger)';
  }
</script>

<AdminShell title="Webhooks">
  {#snippet actions()}
    <button class="btn btn--primary" onclick={() => showCreate = true}>+ New Webhook</button>
  {/snippet}

  {#snippet children()}
    {#if loading}
      <p class="muted">Loading…</p>
    {:else if hooks.length === 0}
      <EmptyState
        title="No webhooks yet"
        message="Receive HTTP POST notifications when content changes."
        action="New Webhook"
        onaction={() => showCreate = true}
      />
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>URL</th><th>Status</th><th>Last fired</th><th></th></tr>
          </thead>
          <tbody>
            {#each hooks as h (h.id)}
              <tr>
                <td>
                  <a href="/admin/webhooks/{h.id}" class="item-link">{h.name}</a>
                  {#if !h.is_active}<span class="badge badge--off">Disabled</span>{/if}
                </td>
                <td class="muted-cell url-cell">{h.url}</td>
                <td>
                  {#if h.last_status}
                    <span class="status-code" style="color:{statusColor(h.last_status)}">{h.last_status}</span>
                  {:else}
                    <span class="muted-cell">—</span>
                  {/if}
                </td>
                <td class="muted-cell">{h.last_fired_at ? formatDate(h.last_fired_at) : '—'}</td>
                <td class="actions-cell">
                  <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = h} title="Delete">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10"/>
                    </svg>
                  </button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  {/snippet}
</AdminShell>

<Modal open={showCreate} title="New Webhook" onclose={() => showCreate = false}>
  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Name <span class="req">*</span></label>
        <input class="input" type="text" bind:value={newName} placeholder="e.g. Deploy trigger" />
      </div>
      <div class="field">
        <label class="label">Endpoint URL <span class="req">*</span></label>
        <input class="input" type="url" bind:value={newUrl} placeholder="https://…" />
      </div>
    </div>
  {/snippet}
  {#snippet footer()}
    <button class="btn btn--ghost" onclick={() => showCreate = false}>Cancel</button>
    <button class="btn btn--primary" onclick={create} disabled={creating}>
      {creating ? 'Creating…' : 'Create'}
    </button>
  {/snippet}
</Modal>

<ConfirmDialog
  open={!!deleteItem}
  title="Delete webhook"
  message="Delete '{deleteItem?.name}'? Delivery history will also be deleted."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .table-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; }
  .table thead th { padding: 10px 16px; background: var(--sc-surface-2); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); text-align: left; border-bottom: 1px solid var(--sc-border); }
  .table tbody tr { border-bottom: 1px solid var(--sc-border); }
  .table tbody tr:last-child { border-bottom: none; }
  .table tbody tr:hover { background: var(--sc-surface-2); }
  .table td { padding: 10px 16px; font-size: 13px; }
  .item-link { color: var(--sc-text); font-weight: 500; }
  .item-link:hover { color: var(--sc-accent); }
  .url-cell { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
  .muted-cell { color: var(--sc-text-muted); font-size: 12px; }
  .badge { font-size: 11px; padding: 2px 7px; border-radius: 20px; font-weight: 700; margin-left: 6px; }
  .badge--off { background: rgba(136,136,153,.15); color: var(--sc-text-muted); }
  .status-code { font-size: 12px; font-weight: 700; font-family: var(--sc-font-mono); }
  .actions-cell { text-align: right; width: 40px; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .form { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
</style>
