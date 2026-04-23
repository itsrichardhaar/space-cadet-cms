<script>
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import Modal from '$lib/components/common/Modal.svelte';
  import SortableList from '$lib/components/common/SortableList.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import Select from '$lib/components/common/Select.svelte';

  const OPEN_IN_OPTS = [
    { value: '_self', label: 'Same tab' },
    { value: '_blank', label: 'New tab' },
  ];

  let menuId   = $derived(parseInt($page.params.id));

  let menu     = $state(null);
  let items    = $state([]);   // flat list (no nesting for v1)
  let loading  = $state(true);
  let saving   = $state(false);
  let notFound = $state(false);

  // Item modal state
  let showItemModal = $state(false);
  let editingItem   = $state(null);  // null = new item

  let iLabel    = $state('');
  let iUrl      = $state('');
  let iTarget   = $state('_self');
  let iLinkType = $state('custom');

  $effect(() => { void menuId; load(); });

  async function load() {
    loading = true;
    try {
      const res = await api.get(`menus/${menuId}`);
      if (!res.data) { notFound = true; return; }
      menu  = res.data;
      items = flattenItems(res.data.items ?? []);
    } catch (e) {
      if (e.status === 404) notFound = true;
      else notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  // Flatten nested items to a flat list for the builder
  function flattenItems(nested, parentId = null) {
    const out = [];
    for (const item of nested) {
      out.push({ ...item, parent_id: parentId, children: undefined, _uid: item.id });
      if (item.children?.length) out.push(...flattenItems(item.children, item.id));
    }
    return out;
  }

  function handleReorder(newItems) {
    items = newItems;
  }

  function openNewItem() {
    editingItem = null;
    iLabel = ''; iUrl = ''; iTarget = '_self'; iLinkType = 'custom';
    showItemModal = true;
  }

  function openEditItem(item) {
    editingItem = item;
    iLabel    = item.label;
    iUrl      = item.url ?? '';
    iTarget   = item.target ?? '_self';
    iLinkType = item.link_type ?? 'custom';
    showItemModal = true;
  }

  function saveItem() {
    if (!iLabel.trim()) { notifications.error('Label is required'); return; }
    if (editingItem) {
      items = items.map(i => i._uid === editingItem._uid
        ? { ...i, label: iLabel, url: iUrl, target: iTarget, link_type: iLinkType }
        : i
      );
    } else {
      items = [...items, { _uid: Date.now(), label: iLabel, url: iUrl, target: iTarget, link_type: iLinkType, parent_id: null }];
    }
    showItemModal = false;
  }

  function removeItem(uid) {
    items = items.filter(i => i._uid !== uid);
  }

  async function save() {
    saving = true;
    try {
      // Build nested structure for API
      const nested = items
        .filter(i => !i.parent_id)
        .map(i => ({
          label: i.label, url: i.url, target: i.target, link_type: i.link_type,
          children: items.filter(c => c.parent_id === i._uid).map(c => ({
            label: c.label, url: c.url, target: c.target, link_type: c.link_type, children: [],
          })),
        }));
      await api.put(`menus/${menuId}/items`, { items: nested });
      notifications.success('Menu saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }
</script>

{#if notFound}
  <AdminShell title="Menu not found">
    {#snippet children()}<p class="muted"><a href="/admin/menus">Back to Menus</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : (menu?.name ?? 'Menu Builder')}>
    {#snippet actions()}
      <a href="/admin/menus" class="btn btn--ghost">← All Menus</a>
      <button class="btn btn--secondary" onclick={openNewItem}>+ Add Item</button>
      <button class="btn btn--primary" onclick={save} disabled={saving || loading}>
        {saving ? 'Saving…' : 'Save Menu'}
      </button>
    {/snippet}

    {#snippet children()}
      {#if loading}
        <p class="muted">Loading…</p>
      {:else}
        <div class="builder">
          {#if items.length === 0}
            <div class="empty">
              <p>No items yet. <button class="link-btn" onclick={openNewItem}>Add the first item</button></p>
            </div>
          {:else}
            <SortableList {items} onreorder={handleReorder} handle=".drag-handle">
              {#snippet children(item)}
                <div class="item" style="margin-left: {item.parent_id ? '32px' : '0'}">
                  <span class="drag-handle" title="Drag to reorder">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                      <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                      <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                      <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                    </svg>
                  </span>
                  <div class="item-info">
                    <span class="item-label">{item.label}</span>
                    {#if item.url}<span class="item-url">{item.url}</span>{/if}
                    {#if item.target === '_blank'}<span class="item-badge">opens in new tab</span>{/if}
                  </div>
                  <div class="item-actions">
                    <button class="btn-icon" onclick={() => openEditItem(item)} title="Edit">
                      <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11.5 2.5l2 2L5 13H3v-2L11.5 2.5z"/>
                      </svg>
                    </button>
                    <button class="btn-icon btn-icon--danger" onclick={() => removeItem(item._uid)} title="Remove">×</button>
                  </div>
                </div>
              {/snippet}
            </SortableList>
          {/if}
        </div>
      {/if}
    {/snippet}
  </AdminShell>
{/if}

<!-- Item edit modal -->
<Modal open={showItemModal} title={editingItem ? 'Edit Item' : 'Add Item'} onclose={() => showItemModal = false}>
  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Label <span class="req">*</span></label>
        <input class="input" type="text" bind:value={iLabel} placeholder="Menu label" />
      </div>
      <div class="field">
        <label class="label">URL</label>
        <input class="input" type="text" bind:value={iUrl} placeholder="/about or https://…" />
      </div>
      <div class="field">
        <label class="label">Open in</label>
        <Select bind:value={iTarget} options={OPEN_IN_OPTS} />
      </div>
    </div>
  {/snippet}
  {#snippet footer()}
    <button class="btn btn--ghost" onclick={() => showItemModal = false}>Cancel</button>
    <button class="btn btn--primary" onclick={saveItem}>
      {editingItem ? 'Update' : 'Add'}
    </button>
  {/snippet}
</Modal>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .builder { max-width: 620px; }
  .empty { background: var(--sc-surface); border: 1px dashed var(--sc-border); border-radius: var(--sc-radius-lg); padding: 32px; text-align: center; color: var(--sc-text-muted); font-size: 13px; }
  .link-btn { background: none; border: none; color: var(--sc-accent); cursor: pointer; font-size: 13px; padding: 0; }
  .item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); margin-bottom: 6px; }
  .drag-handle { color: var(--sc-text-muted); cursor: grab; flex-shrink: 0; display: flex; padding: 4px; }
  .drag-handle:active { cursor: grabbing; }
  .item-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
  .item-label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .item-url { font-size: 12px; color: var(--sc-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .item-badge { font-size: 11px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); padding: 1px 6px; border-radius: 20px; color: var(--sc-text-muted); width: fit-content; }
  .item-actions { display: flex; gap: 4px; flex-shrink: 0; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px 6px; cursor: pointer; border-radius: var(--sc-radius); font-size: 14px; display: inline-flex; align-items: center; }
  .btn-icon:hover { color: var(--sc-accent); background: rgba(var(--sc-accent-rgb), .1); }
  .btn-icon--danger:hover { color: var(--sc-danger); background: rgba(248,113,113,.1); }
  .form { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--secondary { background: var(--sc-surface-2); border: 1px solid var(--sc-border); color: var(--sc-text); }
  .btn--secondary:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
