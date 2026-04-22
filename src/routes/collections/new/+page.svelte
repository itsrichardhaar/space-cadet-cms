<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { goto } from '$app/navigation';
  import api from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  let name        = $state('');
  let slug        = $state('');
  let description = $state('');
  let icon        = $state('⊞');
  let supports_status = $state(true);
  let saving      = $state(false);
  let errors      = $state({});

  // Auto-generate slug from name
  let slugEdited = false;
  function onNameInput() {
    if (!slugEdited) slug = toSlug(name);
  }
  function onSlugInput() { slugEdited = true; }

  function toSlug(s) {
    return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  async function submit(e) {
    e.preventDefault();
    errors = {};
    if (!name.trim()) { errors.name = 'Required'; return; }
    if (!slug.trim()) { errors.slug = 'Required'; return; }
    saving = true;
    try {
      const res = await api.post('collections', {
        name: name.trim(),
        slug: slug.trim(),
        description: description.trim() || null,
        icon: icon.trim() || '⊞',
        supports_status,
      });
      notifications.success(`"${res.data.name}" created.`);
      goto(`/admin/collections/${res.data.slug}/schema`);
    } catch (err) {
      if (err.errors) errors = err.errors;
      else notifications.error(err.message);
    } finally {
      saving = false;
    }
  }
</script>

<AdminShell title="New Collection">
  {#snippet actions()}
    <a href="/admin/collections" class="btn-ghost">Cancel</a>
  {/snippet}

  <div class="form-wrap">
    <form onsubmit={submit}>
      <div class="card">
        <div class="section-head">Collection details</div>

        <div class="row">
          <div class="field" class:has-error={errors.name}>
            <label class="label" for="coll-name">Name <span class="req">*</span></label>
            <input id="coll-name" class="input" type="text" bind:value={name} oninput={onNameInput} placeholder="Blog posts" />
            {#if errors.name}<span class="error">{errors.name}</span>{/if}
          </div>
          <div class="field" class:has-error={errors.slug}>
            <label class="label" for="coll-slug">Slug <span class="req">*</span></label>
            <input id="coll-slug" class="input" type="text" bind:value={slug} oninput={onSlugInput} placeholder="blog-posts" />
            {#if errors.slug}<span class="error">{errors.slug}</span>{/if}
          </div>
        </div>

        <div class="field">
          <label class="label" for="coll-desc">Description</label>
          <textarea id="coll-desc" class="input textarea" bind:value={description} placeholder="Optional description…" rows="2"></textarea>
        </div>

        <div class="row">
          <div class="field">
            <label class="label" for="coll-icon">Icon</label>
            <input id="coll-icon" class="input icon-input" type="text" bind:value={icon} placeholder="⊞" />
            <span class="hint">Emoji or short text shown in the sidebar.</span>
          </div>
        </div>

        <div class="toggle-row">
          <label class="toggle">
            <input type="checkbox" bind:checked={supports_status} />
            <span class="track"><span class="thumb"></span></span>
            <span class="toggle-label">Supports draft / published / archived status</span>
          </label>
        </div>
      </div>

      <div class="actions">
        <button class="btn-primary" type="submit" disabled={saving}>
          {saving ? 'Creating…' : 'Create collection'}
        </button>
      </div>
    </form>
  </div>
</AdminShell>

<style>
  .form-wrap { max-width: 680px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: hidden; }
  .section-head { padding: 14px 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--sc-text-muted); border-bottom: 1px solid var(--sc-border); }

  .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 20px 0; }
  .field { display: flex; flex-direction: column; gap: 6px; padding: 20px 20px 0; }
  .row .field { padding: 0; }
  .field:last-of-type:not(.row .field) { padding-bottom: 20px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 14px; width: 100%; transition: border-color .15s; }
  .input:focus { outline: none; border-color: var(--sc-accent); }
  .textarea { resize: vertical; font-family: inherit; line-height: 1.5; }
  .icon-input { width: 120px; font-size: 18px; }
  .hint { font-size: 12px; color: var(--sc-text-muted); }
  .has-error .input { border-color: var(--sc-danger); }
  .error { font-size: 12px; color: var(--sc-danger); }

  .toggle-row { padding: 16px 20px 20px; border-top: 1px solid var(--sc-border); margin-top: 20px; }
  .toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
  input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; }
  .track { position: relative; width: 36px; height: 20px; background: var(--sc-border); border-radius: 10px; transition: background .2s; flex-shrink: 0; }
  input:checked ~ .track { background: var(--sc-accent); }
  .thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: white; border-radius: 50%; transition: transform .2s; }
  input:checked ~ .track .thumb { transform: translateX(16px); }
  .toggle-label { font-size: 13.5px; color: var(--sc-text); }

  .actions { margin-top: 20px; display: flex; gap: 10px; }
  .btn-primary { display: inline-flex; align-items: center; padding: 9px 20px; background: var(--sc-accent); color: #fff; border-radius: var(--sc-radius); font-size: 14px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
  .btn-primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn-primary:disabled { opacity: .6; cursor: default; }
  .btn-ghost { display: inline-flex; align-items: center; padding: 9px 16px; border: 1px solid var(--sc-border); color: var(--sc-text-muted); border-radius: var(--sc-radius); font-size: 14px; text-decoration: none; }
  .btn-ghost:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
