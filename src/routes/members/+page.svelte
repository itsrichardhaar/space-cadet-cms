<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import EmptyState from '$lib/components/common/EmptyState.svelte';
  import StatusBadge from '$lib/components/common/StatusBadge.svelte';
  import Pagination from '$lib/components/common/Pagination.svelte';
  import SearchBar from '$lib/components/common/SearchBar.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  let members    = $state([]);
  let loading    = $state(true);
  let q          = $state('');
  let roleFilter = $state('');
  let pageNum    = $state(1);
  let total      = $state(0);
  let perPage    = 20;
  let deleteItem = $state(null);

  onMount(loadMembers);

  async function loadMembers() {
    loading = true;
    try {
      const params = { page: pageNum, per_page: perPage };
      if (q)          params.q    = q;
      if (roleFilter) params.role = roleFilter;
      const res = await api.get('members', params);
      members = res.data ?? [];
      total   = res.meta?.total ?? 0;
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function handleSearch(val) { q = val; pageNum = 1; loadMembers(); }
  function handlePage(p)     { pageNum = p; loadMembers(); }

  async function confirmDelete() {
    const item = deleteItem;
    deleteItem = null;
    try {
      await api.delete(`members/${item.id}`);
      members = members.filter(m => m.id !== item.id);
      total--;
      notifications.success('Member deleted');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  function roleBadge(role) {
    return role === 'paid_member' ? 'paid' : 'free';
  }
</script>

<AdminShell title="Members">
  {#snippet actions()}
    <a href="/members/new" class="btn btn--primary">+ New Member</a>
  {/snippet}

  {#snippet children()}
    <div class="toolbar">
      <SearchBar value={q} onchange={handleSearch} placeholder="Search members…" />
      <select class="input input--sm" bind:value={roleFilter} onchange={() => { pageNum = 1; loadMembers(); }}>
        <option value="">All roles</option>
        <option value="free_member">Free</option>
        <option value="paid_member">Paid</option>
      </select>
    </div>

    {#if loading}
      <p class="muted">Loading…</p>
    {:else if members.length === 0}
      <EmptyState
        title="No members found"
        message="Members are front-end portal users (free or paid)."
        action="New Member"
        onaction={() => goto('/members/new')}
      />
    {:else}
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr>
          </thead>
          <tbody>
            {#each members as m (m.id)}
              <tr>
                <td><a href="/members/{m.id}" class="item-link">{m.display_name}</a></td>
                <td class="muted-cell">{m.email}</td>
                <td><span class="role-badge role-badge--{roleBadge(m.role)}">{m.role}</span></td>
                <td><StatusBadge status={m.status} /></td>
                <td class="muted-cell">{formatDate(m.created_at)}</td>
                <td class="actions-cell">
                  <button class="btn-icon btn-icon--danger" onclick={() => deleteItem = m} title="Delete">
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
      <Pagination page={pageNum} {total} {perPage} onpage={handlePage} />
    {/if}
  {/snippet}
</AdminShell>

<ConfirmDialog
  open={!!deleteItem}
  title="Delete member"
  message="Delete '{deleteItem?.display_name}'? This cannot be undone."
  confirmLabel="Delete"
  danger={true}
  onconfirm={confirmDelete}
  oncancel={() => deleteItem = null}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .toolbar { display: flex; gap: 10px; margin-bottom: 16px; }
  .input--sm { padding: 7px 10px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; }
  .table-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .table { width: 100%; border-collapse: collapse; }
  .table thead th { padding: 10px 16px; background: var(--sc-surface-2); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); text-align: left; border-bottom: 1px solid var(--sc-border); }
  .table tbody tr { border-bottom: 1px solid var(--sc-border); }
  .table tbody tr:last-child { border-bottom: none; }
  .table tbody tr:hover { background: var(--sc-surface-2); }
  .table td { padding: 10px 16px; font-size: 13px; }
  .item-link { color: var(--sc-text); font-weight: 500; }
  .item-link:hover { color: var(--sc-accent); }
  .muted-cell { color: var(--sc-text-muted); font-size: 12px; }
  .role-badge { font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 700; }
  .role-badge--paid { background: rgba(52,211,153,.15); color: var(--sc-success); }
  .role-badge--free { background: rgba(96,165,250,.12); color: var(--sc-info); }
  .actions-cell { text-align: right; width: 40px; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px; cursor: pointer; border-radius: var(--sc-radius); display: inline-flex; }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover { background: var(--sc-accent-hover); }
</style>
