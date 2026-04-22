<script>
  /**
   * Compass — sliding filter panel for collection items.
   *
   * Props:
   *   open (bool $bindable)       — controls slide-in visibility
   *   fieldDefs ([])              — collection field definitions
   *   filters ($bindable {})      — current filter values (field_key → value)
   *   onfilter (fn)               — called when any filter changes
   *   onreset (fn)                — clear all filters
   */
  import FilterDropdown from './FilterDropdown.svelte';
  import FilterCheckbox from './FilterCheckbox.svelte';
  import FilterRange    from './FilterRange.svelte';

  let { open = $bindable(false), fieldDefs = [], filters = $bindable({}), onfilter, onreset } = $props();

  // Status is always available
  const STATUS_OPTIONS = [
    { value: 'draft',     label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived',  label: 'Archived' },
  ];

  // Which field types get which Compass component
  const DROPDOWN_TYPES  = new Set(['select', 'relation', 'toggle', 'color']);
  const CHECKBOX_TYPES  = new Set(['checkbox']);
  const RANGE_TYPES     = new Set(['number']);

  // Filterable custom field definitions (exclude richtext/code/media/repeater — not filterable)
  const EXCLUDED_TYPES  = new Set(['richtext', 'code', 'media', 'repeater']);

  let filterableFields = $derived(
    fieldDefs.filter(f => !EXCLUDED_TYPES.has(f.type))
  );

  let activeCount = $derived(
    Object.values(filters).filter(v => v !== '' && v !== null && (Array.isArray(v) ? v.length > 0 : true)).length
  );

  function set(key, val) {
    filters = { ...filters, [key]: val };
    onfilter?.(filters);
  }

  function optionsFor(field) {
    const raw = field.options;
    if (!raw) return [];
    const opts = Array.isArray(raw) ? raw : (typeof raw === 'string' ? JSON.parse(raw) : []);
    return opts.map(o => typeof o === 'string' ? { value: o, label: o } : o);
  }
</script>

<!-- Backdrop -->
{#if open}
  <div class="backdrop" onclick={() => open = false} role="presentation"></div>
{/if}

<!-- Panel -->
<aside class="panel" class:open>
  <div class="panel-header">
    <span class="panel-title">Compass Filters</span>
    {#if activeCount > 0}
      <span class="active-badge">{activeCount} active</span>
    {/if}
    <button class="close-btn" onclick={() => open = false} aria-label="Close filters">✕</button>
  </div>

  <div class="panel-body">
    <!-- Status is always first -->
    <FilterDropdown
      field="status"
      label="Status"
      options={STATUS_OPTIONS}
      value={filters.status ?? ''}
      onchange={v => set('status', v)}
    />

    <!-- Custom field filters -->
    {#each filterableFields as fd}
      {#if DROPDOWN_TYPES.has(fd.type)}
        <FilterDropdown
          field={fd.key}
          label={fd.name}
          options={optionsFor(fd)}
          value={filters[fd.key] ?? ''}
          onchange={v => set(fd.key, v)}
        />
      {:else if CHECKBOX_TYPES.has(fd.type)}
        <FilterCheckbox
          field={fd.key}
          label={fd.name}
          options={optionsFor(fd)}
          value={filters[fd.key] ?? []}
          onchange={v => set(fd.key, v)}
        />
      {:else if RANGE_TYPES.has(fd.type)}
        <FilterRange
          field={fd.key}
          label={fd.name}
          min={filters[fd.key + '_min'] ?? ''}
          max={filters[fd.key + '_max'] ?? ''}
          onchange={({ min, max }) => {
            filters = { ...filters, [fd.key + '_min']: min, [fd.key + '_max']: max };
            onfilter?.(filters);
          }}
        />
      {:else}
        <!-- text/textarea: basic text search -->
        <div class="filter-group">
          <label class="filter-label" for="ct-{fd.key}">{fd.name}</label>
          <input
            id="ct-{fd.key}"
            class="filter-input"
            type="text"
            placeholder="Contains…"
            value={filters[fd.key] ?? ''}
            oninput={e => set(fd.key, e.target.value)}
          />
        </div>
      {/if}
    {/each}
  </div>

  {#if activeCount > 0}
    <div class="panel-footer">
      <button class="reset-btn" onclick={() => { filters = {}; onreset?.(); }}>
        Clear all filters
      </button>
    </div>
  {/if}
</aside>

<style>
  .backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.3); z-index: 200;
    animation: fade-in .15s ease;
  }
  @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }

  .panel {
    position: fixed; top: 0; right: 0; bottom: 0; width: 300px;
    background: var(--sc-surface); border-left: 1px solid var(--sc-border);
    z-index: 201; display: flex; flex-direction: column;
    transform: translateX(100%); transition: transform .22s ease;
  }
  .panel.open { transform: translateX(0); }

  .panel-header {
    display: flex; align-items: center; gap: 8px;
    padding: 16px 18px; border-bottom: 1px solid var(--sc-border); flex-shrink: 0;
  }
  .panel-title { font-size: 14px; font-weight: 600; color: var(--sc-text); flex: 1; }
  .active-badge {
    font-size: 11px; background: var(--sc-accent); color: #fff;
    border-radius: 10px; padding: 2px 8px; font-weight: 600;
  }
  .close-btn {
    background: none; border: none; cursor: pointer; color: var(--sc-text-muted);
    font-size: 14px; padding: 4px; line-height: 1;
  }
  .close-btn:hover { color: var(--sc-text); }

  .panel-body { flex: 1; overflow-y: auto; padding: 16px 18px; display: flex; flex-direction: column; gap: 20px; }

  .filter-group { display: flex; flex-direction: column; gap: 6px; }
  .filter-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .05em; }
  .filter-input {
    background: var(--sc-surface-2); border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius); padding: 7px 10px; font-size: 13px;
    color: var(--sc-text); width: 100%; box-sizing: border-box;
  }
  .filter-input:focus { outline: none; border-color: var(--sc-accent); }

  .panel-footer { padding: 12px 18px; border-top: 1px solid var(--sc-border); flex-shrink: 0; }
  .reset-btn {
    width: 100%; padding: 8px; background: none; border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius); font-size: 13px; color: var(--sc-text-muted); cursor: pointer;
  }
  .reset-btn:hover { border-color: var(--sc-danger); color: var(--sc-danger); }
</style>
