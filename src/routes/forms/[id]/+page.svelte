<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import SortableList from '$lib/components/common/SortableList.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { slugify } from '$lib/utils/slugify.js';

  import Select from '$lib/components/common/Select.svelte';

  const FIELD_TYPES = ['text','email','textarea','select','checkbox','radio','number','date'];
  const FIELD_TYPE_OPTS = FIELD_TYPES.map(t => ({ value: t, label: t }));

  let formId   = $derived(parseInt($page.params.id));

  let loading  = $state(true);
  let saving   = $state(false);
  let deleting = $state(false);
  let notFound = $state(false);

  // Form meta
  let name           = $state('');
  let slug           = $state('');
  let description    = $state('');
  let successMessage = $state('Thank you!');
  let notifyEmails   = $state('');
  let honeypotField  = $state('website');
  let rateLimitMax   = $state(5);

  // Fields
  let fields       = $state([]);
  let _uid         = 0;
  let showDelete   = $state(false);

  $effect(() => { void formId; load(); });

  async function load() {
    loading = true;
    try {
      const res = await api.get(`forms/${formId}`);
      if (!res.data) { notFound = true; return; }
      const f = res.data;
      name           = f.name;
      slug           = f.slug;
      description    = f.description ?? '';
      successMessage = f.success_message ?? 'Thank you!';
      notifyEmails   = Array.isArray(f.notify_emails) ? f.notify_emails.join(', ') : (f.notify_emails ?? '');
      honeypotField  = f.honeypot_field ?? 'website';
      rateLimitMax   = f.rate_limit_max ?? 5;
      fields         = (f.fields ?? []).map(fd => ({ ...fd, _uid: ++_uid, _open: false }));
    } catch (e) {
      if (e.status === 404) notFound = true;
      else notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function addField() {
    fields = [...fields, {
      _uid: ++_uid, _open: true,
      name: '', key: '', type: 'text', placeholder: '', required: false, options: { choices: [] }
    }];
  }

  function removeField(uid) { fields = fields.filter(f => f._uid !== uid); }

  function onFieldNameInput(f) {
    if (!f._keyEdited) f.key = slugify(f.name).replace(/-/g, '_');
    fields = [...fields]; // trigger reactivity
  }

  function handleReorder(newFields) { fields = newFields; }

  function addChoice(f) { f.options.choices = [...(f.options.choices ?? []), '']; fields = [...fields]; }
  function removeChoice(f, i) { f.options.choices = f.options.choices.filter((_, j) => j !== i); fields = [...fields]; }

  async function save() {
    saving = true;
    try {
      const emails = notifyEmails.split(',').map(e => e.trim()).filter(Boolean);
      await api.put(`forms/${formId}`, {
        name, slug, description: description || null,
        success_message: successMessage,
        notify_emails: emails,
        honeypot_field: honeypotField,
        rate_limit_max: parseInt(rateLimitMax),
      });
      await api.put(`forms/${formId}/fields`, {
        fields: fields.map((f, i) => ({
          name: f.name, key: f.key, type: f.type,
          placeholder: f.placeholder || null,
          required: f.required ? 1 : 0,
          options: f.options ?? {},
          sort_order: i,
        })),
      });
      notifications.success('Form saved');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function deleteForm() {
    showDelete = false;
    try {
      await api.delete(`forms/${formId}`);
      notifications.success('Form deleted');
      goto('/admin/forms');
    } catch (e) {
      notifications.error(e.message);
    }
  }
</script>

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}<p class="muted"><a href="/admin/forms">Back to Forms</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : name}>
    {#snippet actions()}
      <a href="/admin/forms/{formId}/submissions" class="btn btn--ghost">Submissions</a>
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
          <!-- Fields builder -->
          <div class="main">
            <div class="card">
              <h3 class="card-title">Form Fields</h3>
              {#if fields.length > 0}
                <SortableList items={fields} onreorder={handleReorder} handle=".drag-handle">
                  {#snippet children(f)}
                    <div class="field-block">
                      <div class="field-header">
                        <span class="drag-handle" title="Drag to reorder">
                          <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
                            <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                            <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                            <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                          </svg>
                        </span>
                        <button class="field-toggle" onclick={() => { f._open = !f._open; fields = [...fields]; }}>
                          <span class="field-name-preview">{f.name || 'Unnamed field'}</span>
                          <span class="field-type-badge">{f.type}</span>
                        </button>
                        <button class="btn-icon btn-icon--danger" onclick={() => removeField(f._uid)}>×</button>
                      </div>

                      {#if f._open}
                        <div class="field-body">
                          <div class="row2">
                            <div class="mini-field">
                              <label class="label">Label</label>
                              <input class="input" type="text" bind:value={f.name} oninput={() => onFieldNameInput(f)} placeholder="Field label" />
                            </div>
                            <div class="mini-field">
                              <label class="label">Key</label>
                              <input class="input" type="text" bind:value={f.key} oninput={() => { f._keyEdited = true; fields = [...fields]; }} placeholder="field_key" />
                            </div>
                          </div>
                          <div class="row2">
                            <div class="mini-field">
                              <label class="label">Type</label>
                              <Select bind:value={f.type} options={FIELD_TYPE_OPTS} />
                            </div>
                            <div class="mini-field">
                              <label class="label">Placeholder</label>
                              <input class="input" type="text" bind:value={f.placeholder} placeholder="Optional placeholder" />
                            </div>
                          </div>
                          <label class="check-label">
                            <input type="checkbox" bind:checked={f.required} />
                            Required
                          </label>
                          {#if f.type === 'select' || f.type === 'checkbox' || f.type === 'radio'}
                            <div class="choices">
                              <label class="label">Choices</label>
                              {#each (f.options.choices ?? []) as _, ci}
                                <div class="choice-row">
                                  <input class="input" type="text" bind:value={f.options.choices[ci]} placeholder="Choice" oninput={() => { fields = [...fields]; }} />
                                  <button class="btn-icon btn-icon--danger" onclick={() => removeChoice(f, ci)}>×</button>
                                </div>
                              {/each}
                              <button class="btn-sm" onclick={() => addChoice(f)}>+ Add choice</button>
                            </div>
                          {/if}
                        </div>
                      {/if}
                    </div>
                  {/snippet}
                </SortableList>
              {:else}
                <p class="muted">No fields yet.</p>
              {/if}
              <button class="btn btn--secondary add-field-btn" onclick={addField}>+ Add Field</button>
            </div>
          </div>

          <!-- Settings sidebar -->
          <aside class="sidebar">
            <div class="card">
              <h3 class="card-title">Settings</h3>
              <label class="label">Form name</label>
              <input class="input" type="text" bind:value={name} />
              <label class="label" style="margin-top:10px">Slug</label>
              <input class="input" type="text" bind:value={slug} />
              <label class="label" style="margin-top:10px">Description</label>
              <textarea class="input input--ta" bind:value={description} rows="2" placeholder="Optional description"></textarea>
            </div>
            <div class="card">
              <h3 class="card-title">Notifications</h3>
              <label class="label">Notify emails</label>
              <input class="input" type="text" bind:value={notifyEmails} placeholder="a@b.com, c@d.com" />
              <label class="label" style="margin-top:10px">Success message</label>
              <input class="input" type="text" bind:value={successMessage} />
            </div>
            <div class="card">
              <h3 class="card-title">Anti-Spam</h3>
              <label class="label">Honeypot field name</label>
              <input class="input" type="text" bind:value={honeypotField} />
              <label class="label" style="margin-top:10px">Rate limit (per hour)</label>
              <input class="input" type="number" bind:value={rateLimitMax} min="1" max="100" />
            </div>
          </aside>
        </div>
      {/if}
    {/snippet}
  </AdminShell>
{/if}

<ConfirmDialog
  open={showDelete}
  title="Delete form"
  message="Delete '{name}'? All submissions will also be deleted."
  confirmLabel="Delete"
  danger={true}
  onconfirm={deleteForm}
  oncancel={() => showDelete = false}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .layout { display: grid; grid-template-columns: 1fr 260px; gap: 24px; align-items: start; }
  .main { display: flex; flex-direction: column; gap: 16px; }
  .sidebar { display: flex; flex-direction: column; gap: 14px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 18px; }
  .card-title { margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .field-block { border: 1px solid var(--sc-border); border-radius: var(--sc-radius); margin-bottom: 8px; overflow: hidden; }
  .field-header { display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: var(--sc-surface-2); }
  .drag-handle { color: var(--sc-text-muted); cursor: grab; display: flex; padding: 2px; flex-shrink: 0; }
  .field-toggle { flex: 1; display: flex; align-items: center; gap: 8px; background: none; border: none; text-align: left; cursor: pointer; padding: 0; color: inherit; }
  .field-name-preview { font-size: 13px; font-weight: 600; color: var(--sc-text); }
  .field-type-badge { font-size: 11px; background: rgba(var(--sc-accent-rgb), .15); color: var(--sc-accent); padding: 2px 7px; border-radius: 20px; }
  .field-body { padding: 14px 12px; display: flex; flex-direction: column; gap: 12px; }
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .mini-field { display: flex; flex-direction: column; gap: 5px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); display: block; }
  .input { padding: 7px 10px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .input--ta { resize: vertical; font-family: inherit; }
  .check-label { display: flex; align-items: center; gap: 7px; font-size: 13px; color: var(--sc-text-muted); cursor: pointer; }
  .choices { display: flex; flex-direction: column; gap: 6px; }
  .choice-row { display: flex; gap: 6px; align-items: center; }
  .btn-icon { background: none; border: none; color: var(--sc-text-muted); padding: 4px 6px; cursor: pointer; border-radius: var(--sc-radius); font-size: 15px; display: inline-flex; }
  .btn-icon--danger:hover { color: var(--sc-danger); }
  .btn-sm { background: none; border: 1px solid var(--sc-border); color: var(--sc-text-muted); padding: 4px 10px; border-radius: var(--sc-radius); font-size: 12px; cursor: pointer; }
  .btn-sm:hover { color: var(--sc-text); }
  .add-field-btn { width: 100%; margin-top: 10px; justify-content: center; }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
  .btn--secondary { background: var(--sc-surface-2); border: 1px solid var(--sc-border); color: var(--sc-text); }
</style>
