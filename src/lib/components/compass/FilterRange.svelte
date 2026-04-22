<script>
  /**
   * Compass — numeric min/max range filter
   * Props: field, label, min (string $bindable), max (string $bindable), onchange
   */
  let { field, label, min = $bindable(''), max = $bindable(''), onchange } = $props();

  let debounce;
  function changed() {
    clearTimeout(debounce);
    debounce = setTimeout(() => onchange?.({ min, max }), 400);
  }
</script>

<div class="filter-group">
  <div class="filter-label">{label}</div>
  <div class="range-row">
    <input
      class="range-input"
      type="number"
      placeholder="Min"
      bind:value={min}
      oninput={changed}
    />
    <span class="sep">–</span>
    <input
      class="range-input"
      type="number"
      placeholder="Max"
      bind:value={max}
      oninput={changed}
    />
  </div>
</div>

<style>
  .filter-group { display: flex; flex-direction: column; gap: 6px; }
  .filter-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .05em; }
  .range-row { display: flex; align-items: center; gap: 6px; }
  .range-input {
    flex: 1; background: var(--sc-surface-2); border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius); padding: 7px 10px; font-size: 13px;
    color: var(--sc-text); width: 0; min-width: 0;
  }
  .range-input:focus { outline: none; border-color: var(--sc-accent); }
  .sep { color: var(--sc-text-muted); font-size: 12px; flex-shrink: 0; }
</style>
