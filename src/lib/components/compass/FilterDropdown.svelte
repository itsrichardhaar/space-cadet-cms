<script>
  import Select from '$lib/components/common/Select.svelte';
  /**
   * Compass — single-select dropdown filter
   * Props: field (string), label (string), options ([{value, label}]), value (string), onchange
   */
  let { field, label, options = [], value = $bindable(''), onchange } = $props();

  const allOptions = $derived([{ value: '', label: 'Any' }, ...options]);
</script>

<div class="filter-group">
  <label class="filter-label" for="fd-{field}">{label}</label>
  <Select id="fd-{field}" bind:value options={allOptions} onchange={onchange} />
</div>

<style>
  .filter-group { display: flex; flex-direction: column; gap: 6px; }
  .filter-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .05em; }
</style>
