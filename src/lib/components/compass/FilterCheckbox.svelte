<script>
  /**
   * Compass — multi-select checkbox group filter
   * Props: field, label, options ([{value, label}]), value (string[] $bindable), onchange
   */
  let { field, label, options = [], value = $bindable([]), onchange } = $props();

  function toggle(v) {
    const next = value.includes(v) ? value.filter(x => x !== v) : [...value, v];
    value = next;
    onchange?.(value);
  }
</script>

<div class="filter-group">
  <div class="filter-label">{label}</div>
  <div class="options">
    {#each options as opt}
      <label class="option-row">
        <input
          type="checkbox"
          checked={value.includes(opt.value)}
          onchange={() => toggle(opt.value)}
        />
        <span class="option-label">{opt.label}</span>
      </label>
    {/each}
  </div>
</div>

<style>
  .filter-group { display: flex; flex-direction: column; gap: 6px; }
  .filter-label { font-size: 11px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .05em; }
  .options { display: flex; flex-direction: column; gap: 4px; }
  .option-row { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--sc-text); padding: 3px 0; }
  .option-row input[type="checkbox"] { accent-color: var(--sc-accent); width: 14px; height: 14px; cursor: pointer; }
  .option-label { flex: 1; }
</style>
