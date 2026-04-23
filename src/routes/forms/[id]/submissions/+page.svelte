<script>
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import Pagination from '$lib/components/common/Pagination.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import Modal from '$lib/components/common/Modal.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  let formId = $derived(parseInt($page.params.id));

  let form        = $state(null);
  let submissions = $state([]);
  let loading     = $state(true);
  let filter      = $state('all');   // 'all' | 'unread' | 'spam'
  let pageNum     = $state(1);
  let total       = $state(0);
  let perPage     = 25;
  let viewItem    = $state(null);
  let deleteItem  = $state(null);

  $effect(() => { void formId; load(); });

  async function load() {
    loading = true;
    try {
      const [fRes, sRes] = await Promise.all([
        api.get(`forms/${formId}`),
        loadSubs(),
      ]);
      form = fRes.data;
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  async function loadSubs() {
    const params = { page: pageNum, per_page: perPage };
    if (filter === 'unread') params.is_read = 0;
    if (filter === 'spam')   params.is_spam  = 1;
    const res = await api.get(`forms/${formId}/submissions`, params);
    submissions = res.data ?? [];
    total = res.meta?.total ?? 0;
  }

  function handleFilter(f) { filter = f; pageNum = 1; loadSubs(); }
  function handlePage(p)   { pageNum = p; loadSubs(); }

  async function markRead(sub) {
    try {
      await api.put(`forms/${formId}/submissions/${sub.id}`, { is_read: 1 });
      submissions = submissions.map(s => s.id === sub.id ? { ...s, is_read: 1 } : s);
    } catch (e) {
      notifications.error(e.message);
    }
  }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`forms/${formId}/submissions/${item.id}`);
      submissions = submissions.filter(s => s.id !== item.id);
      total--;
      notifications.success('Submission deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  function exportCsv() {
    window.open(`/api.php?action=forms/${formId}/submissions/export&method=GET`, '_blank');
  }

  function parsedData(sub) {
    if (typeof sub.data === 'string') { try { return JSON.parse(sub.data); } catch { return {}; } }
    return sub.data ?? {};
  }
</script>

<AdminShell title={loading ? 'Loading…' : `${form?.name ?? 'Form'} — Submissions`}>
  {#snippet actions()}
    <a href="/admin/forms/{formId}" class="btn btn--ghost">← Form Builder</a>
    <button class="btn btn--secondary" onclick={exportCsv}>Export CSV</button>
  {/snippet}

  {#snippet children()}
    <!-- Filter tabs -->
    <div class="tabs">
      {#each [['all','All'],['unread','Unread'],['spam','Spam']] as [key, label]}
        <button class="tab" class:tab--active={filter === key} onclick={() => handleFilter(key)}>{label}</button>
      {/each}
    </div>

    {#if loading}
      <p class="muted">Loading…</p>
    {:else if submissions.length === 0}
      <p class="muted">No submissions found.</p>
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th></th><th>Date</th><th>IP</th><th>Preview</th><th></th></tr>
          </thead>
          <tbody>
            {#each submissions as sub (sub.id)}
              <tr class:unread={!sub.is_read && !sub.is_spam}>
                <td class="status-cell">
                  {#if sub.is_spam}
                    <span class="badge badge--spam">Spam</span>
                  {:else if !sub.is_read}
                    <span class="dot-unread"></span>
                  {/if}
                </td>
                <td class="muted-cell">{formatDate(sub.created_at)}</td>
                <td class="muted-cell">{sub.ip_address ?? '—'}</td>
                <td>
                  <button class="preview-btn" onclick={() => viewItem = sub}>
                    {Object.values(parsedData(sub)).slice(0, 2).join(' · ').slice(0, 60)}…
                  </button>
                </td>
                <td class="actions-cell">
                  {#if !sub.is_read}
                    <button class="btn-text" onclick={() => markRead(sub)}>Mark read</button>
                  {/if}
                  <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = sub} title="Delete">×</button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
      <Pagination page={pageNum} {total} {perPage} onpage={handlePage} />
    {/if}
  {/snippet}
</AdminShell>

<!-- Submission detail modal -->
<Modal open={!!viewItem} title="Submission Detail" onclose={() => viewItem = null}>
  {#snippet children()}
    {#if viewItem}
      <div class="sub-detail">
        <p class="sub-meta">Submitted {formatDate(viewItem.created_at)} from {viewItem.ip_address ?? 'unknown IP'}</p>
        <dl class="sub-data">
          {#each Object.entries(parsedData(viewItem)) as [k, v]}
            <dt>{k}</dt>
            <dd>{typeof v === 'object' ? JSON.stringify(v) : v}</dd>
          {/each}
        </dl>
      </div>
    {/if}
  {/snippet}
</Modal>

<ConfirmDialog
  open={!!deleteItem}
  title="Delete submission"
  message="Delete this submission? This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .tabs { display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid var(--sc-border); }
  .tab { background: none; border: none; padding: 8px 16px; font-size: 13px; color: var(--sc-text-muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; }
  .tab--active { color: var(--sc-accent); border-bottom-color: var(--sc-accent); }
  .tab:hover { color: var(--sc-text); }
  .table-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; }
  .table thead th { padding: 10px 16px; background: var(--sc-surface-2); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); text-align: left; border-bottom: 1px solid var(--sc-border); }
  .table tbody tr { border-bottom: 1px solid var(--sc-border); }
  .table tbody tr:last-child { border-bottom: none; }
  .table tbody tr:hover { background: var(--sc-surface-2); }
  .table.unread { background: rgba(var(--sc-accent-rgb), .04); }
  .table td { padding: 10px 16px; font-size: 13px; }
  .status-cell { width: 30px; }
  .dot-unread { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--sc-accent); }
  .badge { font-size: 11px; padding: 2px 7px; border-radius: 20px; font-weight: 700; }
  .badge--spam { background: rgba(248,113,113,.15); color: var(--sc-danger); }
  .muted-cell { color: var(--sc-text-muted); font-size: 12px; }
  .preview-btn { background: none; border: none; color: var(--sc-text-muted); font-size: 12px; text-align: left; cursor: pointer; padding: 0; }
  .preview-btn:hover { color: var(--sc-text); }
  .actions-cell { text-align: right; display: flex; gap: 8px; justify-content: flex-end; align-items: center; }
  .btn-text { font-size: 12px; color: var(--sc-accent); background: none; border: none; cursor: pointer; padding: 0; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px 6px; cursor: pointer; border-radius: var(--sc-radius); font-size: 15px; }
  .btn-icon--danger:hover { color: var(--sc-danger); }
  .sub-detail { display: flex; flex-direction: column; gap: 12px; }
  .sub-meta { margin: 0; font-size: 12px; color: var(--sc-text-muted); }
  .sub-data { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0; }
  .sub-data dt { font-size: 12px; font-weight: 700; color: var(--sc-text-muted); }
  .sub-data dd { font-size: 13px; color: var(--sc-text); margin: 0; word-break: break-word; }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--secondary { background: var(--sc-surface-2); border: 1px solid var(--sc-border); color: var(--sc-text); }
  .btn--secondary:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
