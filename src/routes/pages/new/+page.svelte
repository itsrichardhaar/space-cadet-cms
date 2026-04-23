<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { slugify } from '$lib/utils/slugify.js';
  import Select from '$lib/components/common/Select.svelte';

  const STATUS_OPTS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
  ];

  let title     = $state('');
  let slug      = $state('');
  let parentId  = $state('');
  let status    = $state('draft');
  let saving    = $state(false);
  let pages     = $state([]);
  let slugEdited = false;

  onMount(async () => {
    try {
      const res = await api.get('pages');
      pages = res.data ?? [];
    } catch { /* non-critical */ }
  });

  function onTitleInput() {
    if (!slugEdited) slug = slugify(title);
  }

  async function save() {
    if (!title.trim()) { notifications.error('Title is required'); return; }
    saving = true;
    try {
      const body = { title: title.trim(), slug: slug || slugify(title), status };
      if (parentId) body.parent_id = parseInt(parentId);
      const res = await api.post('pages', body);
      notifications.success('Page created');
      goto(`/admin/pages/${res.data.id}`);
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }
</script>

<AdminShell title="New Page">
  {#snippet actions()}
    <a href="/admin/pages" class="btn btn--ghost">Cancel</a>
    <button class="btn btn--primary" onclick={save} disabled={saving}>
      {saving ? 'Creating…' : 'Create Page'}
    </button>
  {/snippet}

  {#snippet children()}
    <div class="form">
      <div class="field">
        <label class="label">Title <span class="req">*</span></label>
        <input class="input" type="text" bind:value={title} oninput={onTitleInput} placeholder="Page title" />
      </div>
      <div class="field">
        <label class="label">Slug</label>
        <input class="input" type="text" bind:value={slug} oninput={() => slugEdited = true} placeholder="auto-generated" />
      </div>
      <div class="field">
        <label class="label">Parent page</label>
        <Select bind:value={parentId}
          options={[{ value: '', label: 'None (top-level)' }, ...pages.map(p => ({ value: p.id, label: p.title }))]}
        />
      </div>
      <div class="field">
        <label class="label">Status</label>
        <Select bind:value={status} options={STATUS_OPTS} />
      </div>
    </div>
  {/snippet}
</AdminShell>

<style>
  .form { max-width: 560px; display: flex; flex-direction: column; gap: 20px; }
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
