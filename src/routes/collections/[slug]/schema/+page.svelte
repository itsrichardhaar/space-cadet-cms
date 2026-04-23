<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import api from '$lib/api.js';
  import { onMount, onDestroy } from 'svelte';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { FIELD_TYPES, defaultFieldOptions } from '$lib/utils/fieldTypes.js';
  import Select from '$lib/components/common/Select.svelte';

  const FIELD_TYPE_OPTS = FIELD_TYPES.map(t => ({ value: t.type, label: t.label }));
  const CODE_LANG_OPTS = [
    { value: 'html', label: 'HTML' },
    { value: 'css', label: 'CSS' },
    { value: 'javascript', label: 'JavaScript' },
    { value: 'php', label: 'PHP' },
  ];
  import Sortable from 'sortablejs';

  let slug       = $derived($page.params.slug);
  let collection = $state(null);
  let fields     = $state([]);
  let loading    = $state(true);
  let saving     = $state(false);
  let notFound   = $state(false);

  let listEl = $state(null);
  let sortable;
  let _uid = 0;

  onMount(async () => {
    await load();
    if (listEl) initSortable();
  });
  onDestroy(() => sortable?.destroy());

  async function load() {
    loading = true;
    try {
      const allRes = await api.get('collections');
      const c = (allRes.data ?? []).find(c => c.slug === slug);
      if (!c) { notFound = true; return; }
      const res = await api.get(`collections/${c.id}`);
      collection = res.data;
      fields = (res.data.fields ?? []).map(f => ({ ...f, _uid: ++_uid, _open: false }));
    } catch (e) {
      notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function initSortable() {
    sortable = Sortable.create(listEl, {
      animation: 150,
      handle: '.drag-handle',
      onEnd(evt) {
        const arr = [...fields];
        const [item] = arr.splice(evt.oldIndex, 1);
        arr.splice(evt.newIndex, 0, item);
        fields = arr.map((f, i) => ({ ...f, sort_order: i }));
      },
    });
  }

  function toKey(name) {
    return name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
  }

  function addField() {
    fields = [...fields, {
      _uid: ++_uid,
      _open: true,
      name: '',
      key: '',
      type: 'text',
      options: {},
      required: false,
      sort_order: fields.length,
    }];
    // Scroll to bottom after next tick
    setTimeout(() => {
      const rows = listEl?.querySelectorAll('.field-row');
      rows?.[rows.length - 1]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 50);
  }

  function removeField(uid) {
    fields = fields.filter(f => f._uid !== uid);
  }

  function onFieldNameInput(f) {
    if (!f._keyEdited) f.key = toKey(f.name);
  }

  function onTypeChange(f) {
    f.options = defaultFieldOptions(f.type);
  }

  async function save() {
    // Validate
    for (const f of fields) {
      if (!f.name.trim() || !f.key.trim()) {
        notifications.error('All fields must have a name and key.');
        return;
      }
    }
    saving = true;
    try {
      await api.put(`collections/${collection.id}/fields`, {
        fields: fields.map((f, i) => ({
          name:       f.name.trim(),
          key:        f.key.trim(),
          type:       f.type,
          options:    f.options ?? {},
          required:   !!f.required,
          sort_order: i,
        })),
      });
      notifications.success('Schema saved.');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  function addChoice(f) {
    f.options = { ...f.options, choices: [...(f.options?.choices ?? []), ''] };
  }

  function removeChoice(f, i) {
    const c = [...(f.options?.choices ?? [])];
    c.splice(i, 1);
    f.options = { ...f.options, choices: c };
  }

  function updateChoice(f, i, val) {
    const c = [...(f.options?.choices ?? [])];
    c[i] = val;
    f.options = { ...f.options, choices: c };
  }
</script>

<AdminShell title="{collection?.name ?? 'Loading…'} — Schema">
  {#snippet actions()}
    {#if collection}
      <a href="/admin/collections/{slug}" class="btn-ghost">← Items</a>
      <button class="btn-primary" onclick={save} disabled={saving}>
        {saving ? 'Saving…' : 'Save schema'}
      </button>
    {/if}
  {/snippet}

  {#if notFound}
    <p class="err">Collection not found.</p>
  {:else if loading}
    <p class="muted">Loading…</p>
  {:else}
    <div class="schema-wrap">
      {#if fields.length === 0}
        <div class="empty-schema">
          <p>No fields yet. Add your first field below.</p>
        </div>
      {/if}

      <ul class="field-list" bind:this={listEl}>
        {#each fields as f (f._uid)}
          <li class="field-row">
            <div class="field-row-header">
              <button class="drag-handle" type="button" title="Drag to reorder">⠿</button>

              <span class="field-type-badge">{f.type}</span>

              <div class="field-meta">
                <input
                  class="name-input"
                  type="text"
                  bind:value={f.name}
                  oninput={() => onFieldNameInput(f)}
                  placeholder="Field name…"
                />
                <span class="key-sep">→</span>
                <input
                  class="key-input"
                  type="text"
                  bind:value={f.key}
                  oninput={() => f._keyEdited = true}
                  placeholder="field_key"
                />
              </div>

              <Select bind:value={f.type} options={FIELD_TYPE_OPTS} onchange={() => onTypeChange(f)} />

              <label class="req-toggle" title="Required">
                <input type="checkbox" bind:checked={f.required} />
                <span class="req-label">Required</span>
              </label>

              <button class="expand-btn" type="button" onclick={() => f._open = !f._open}>
                {f._open ? '▴' : '▾'}
              </button>
              <button class="del-btn" type="button" onclick={() => removeField(f._uid)} title="Remove field">✕</button>
            </div>

            {#if f._open}
              <div class="field-options">
                <!-- Select / Checkbox: choices list -->
                {#if f.type === 'select' || f.type === 'checkbox'}
                  <div class="opt-section">
                    <span class="opt-label">Choices</span>
                    {#each (f.options?.choices ?? []) as choice, ci}
                      <div class="choice-row">
                        <input class="choice-input" type="text" value={choice}
                          oninput={(e) => updateChoice(f, ci, e.target.value)} placeholder="Option…" />
                        <button class="choice-del" type="button" onclick={() => removeChoice(f, ci)}>✕</button>
                      </div>
                    {/each}
                    <button class="add-choice" type="button" onclick={() => addChoice(f)}>+ Add choice</button>
                  </div>

                <!-- Relation: collection + multiple -->
                {:else if f.type === 'relation'}
                  <div class="opt-row">
                    <label class="opt-label" for="opt-rel-{f._uid ?? f.id}">Collection slug</label>
                    <input id="opt-rel-{f._uid ?? f.id}" class="opt-input" type="text" bind:value={f.options.collection} placeholder="collection-slug" />
                  </div>
                  <div class="opt-row">
                    <label class="opt-label">
                      <input type="checkbox" bind:checked={f.options.multiple} />
                      Allow multiple
                    </label>
                  </div>

                <!-- Number: min, max, step -->
                {:else if f.type === 'number'}
                  <div class="opt-row-inline">
                    <label class="opt-label">Min<input class="opt-num" type="number" bind:value={f.options.min} /></label>
                    <label class="opt-label">Max<input class="opt-num" type="number" bind:value={f.options.max} /></label>
                    <label class="opt-label">Step<input class="opt-num" type="number" bind:value={f.options.step} /></label>
                  </div>

                <!-- Code: language -->
                {:else if f.type === 'code'}
                  <div class="opt-row">
                    <label class="opt-label" for="opt-lang-{f._uid ?? f.id}">Language</label>
                    <Select id="opt-lang-{f._uid ?? f.id}" bind:value={f.options.language} options={CODE_LANG_OPTS} />
                  </div>

                {:else}
                  <p class="no-opts">No options for this field type.</p>
                {/if}
              </div>
            {/if}
          </li>
        {/each}
      </ul>

      <button class="add-field-btn" type="button" onclick={addField}>
        + Add field
      </button>
    </div>
  {/if}
</AdminShell>

<style>
  .muted { color: var(--sc-text-muted); }
  .err   { color: var(--sc-danger); }

  .schema-wrap { max-width: 860px; }

  .empty-schema { border: 2px dashed var(--sc-border); border-radius: var(--sc-radius-lg); padding: 32px; text-align: center; color: var(--sc-text-muted); margin-bottom: 12px; }

  .field-list { list-style: none; padding: 0; margin: 0 0 12px; display: flex; flex-direction: column; gap: 8px; }

  .field-row { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); overflow: hidden; }

  .field-row-header { display: flex; align-items: center; gap: 8px; padding: 10px 12px; }

  .drag-handle { background: none; border: none; color: var(--sc-text-muted); cursor: grab; font-size: 16px; padding: 2px 4px; flex-shrink: 0; }
  .drag-handle:active { cursor: grabbing; }

  .field-type-badge { font-size: 10px; font-weight: 700; background: rgba(var(--sc-accent-rgb), .15); color: var(--sc-accent); padding: 2px 7px; border-radius: 99px; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; flex-shrink: 0; }

  .field-meta { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; }
  .name-input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 10px; color: var(--sc-text); font-size: 13.5px; width: 180px; }
  .name-input:focus { outline: none; border-color: var(--sc-accent); }
  .key-sep { color: var(--sc-text-muted); font-size: 12px; flex-shrink: 0; }
  .key-input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 10px; color: var(--sc-text-muted); font-size: 12px; font-family: var(--sc-font-mono); width: 140px; }
  .key-input:focus { outline: none; border-color: var(--sc-accent); color: var(--sc-text); }

  .type-select { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 8px; color: var(--sc-text); font-size: 13px; cursor: pointer; flex-shrink: 0; }
  .type-select:focus { outline: none; border-color: var(--sc-accent); }

  .req-toggle { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--sc-text-muted); cursor: pointer; white-space: nowrap; flex-shrink: 0; }
  .req-toggle input { accent-color: var(--sc-accent); }
  .req-label { font-size: 12px; }

  .expand-btn { background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 14px; padding: 2px 6px; }
  .expand-btn:hover { color: var(--sc-text); }
  .del-btn { background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 14px; padding: 2px 6px; margin-left: auto; flex-shrink: 0; }
  .del-btn:hover { color: var(--sc-danger); }

  .field-options { padding: 14px 16px; border-top: 1px solid var(--sc-border); background: var(--sc-surface-2); display: flex; flex-direction: column; gap: 10px; }

  .opt-section { display: flex; flex-direction: column; gap: 6px; }
  .opt-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; display: flex; align-items: center; gap: 6px; }
  .opt-row { display: flex; align-items: center; gap: 10px; }
  .opt-row-inline { display: flex; gap: 16px; flex-wrap: wrap; }
  .opt-input { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 10px; color: var(--sc-text); font-size: 13px; width: 220px; }
  .opt-input:focus { outline: none; border-color: var(--sc-accent); }
  .opt-num { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 10px; color: var(--sc-text); font-size: 13px; width: 80px; margin-left: 6px; }
  .opt-num:focus { outline: none; border-color: var(--sc-accent); }
  .opt-select { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 8px; color: var(--sc-text); font-size: 13px; cursor: pointer; }
  .opt-select:focus { outline: none; border-color: var(--sc-accent); }

  .choice-row { display: flex; align-items: center; gap: 6px; }
  .choice-input { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 10px; color: var(--sc-text); font-size: 13px; flex: 1; }
  .choice-input:focus { outline: none; border-color: var(--sc-accent); }
  .choice-del { background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 14px; padding: 2px 6px; }
  .choice-del:hover { color: var(--sc-danger); }
  .add-choice { background: none; border: 1px dashed var(--sc-border); border-radius: var(--sc-radius); padding: 5px 12px; color: var(--sc-text-muted); font-size: 12px; cursor: pointer; align-self: flex-start; }
  .add-choice:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
  .no-opts { font-size: 12px; color: var(--sc-text-muted); margin: 0; }

  .add-field-btn { display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 12px; border: 2px dashed var(--sc-border); border-radius: var(--sc-radius-lg); background: none; color: var(--sc-text-muted); font-size: 14px; cursor: pointer; transition: border-color .15s, color .15s; }
  .add-field-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }

  .btn-primary { display: inline-flex; align-items: center; padding: 8px 18px; background: var(--sc-accent); color: #fff; border-radius: var(--sc-radius); font-size: 13.5px; font-weight: 600; border: none; cursor: pointer; }
  .btn-primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn-primary:disabled { opacity: .6; cursor: default; }
  .btn-ghost { display: inline-flex; align-items: center; padding: 8px 14px; border: 1px solid var(--sc-border); color: var(--sc-text-muted); border-radius: var(--sc-radius); font-size: 13.5px; text-decoration: none; }
  .btn-ghost:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
