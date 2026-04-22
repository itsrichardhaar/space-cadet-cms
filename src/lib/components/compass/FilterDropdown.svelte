<script>
  /**
   * Compass — single-select dropdown filter
   * Props: field (string), label (string), options ([{value, label}]), value (string), onchange
   */
  let { field, label, options = [], value = $bindable(''), onchange } = $props();
</script>

<div class="filter-group">
  <label class="filter-label" for="fd-{field}">{label}</label>
  <select
    id="fd-{field}"
    class="filter-select"
    bind:value
    onchange={() => onchange?.(value)}
  >
    <option value="">Any</option>
    {#each options as opt}
      <option value={opt.value}>{opt.label}</option>
    {/each}
  </select>
</div>

<style>
  .filter-group { display: flex; flex-direction: column; gap: 6px; }
  .filter-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .05em; }
  .filter-select {
    appearance: none;
    background: var(--sc-surface-2) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23888'/%3E%3C/svg%3E") no-repeat right 10px center;
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius);
    padding: 7px 28px 7px 10px;
    font-size: 13px;
    color: var(--sc-text);
    cursor: pointer;
    width: 100%;
  }
  .filter-select:focus { outline: none; border-color: var(--sc-accent); }
</style>
