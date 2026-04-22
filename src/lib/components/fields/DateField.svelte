<script>
  let { value = $bindable(null), label = '', required = false } = $props();

  // Normalise stored value (ISO string or unix timestamp) → datetime-local format
  let localVal = $derived.by(() => {
    if (!value) return '';
    if (typeof value === 'number') return new Date(value * 1000).toISOString().slice(0, 16);
    return String(value).slice(0, 16);
  });

  function onChange(e) {
    // Store as ISO 8601 string
    value = e.target.value ? e.target.value + ':00.000Z' : null;
  }
</script>

<div class="field">
  {#if label}
    <label class="label">{label}{#if required}<span class="req"> *</span>{/if}</label>
  {/if}
  <input class="input" type="datetime-local" value={localVal} onchange={onChange} />
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 14px; width: 100%; transition: border-color .15s; color-scheme: dark; }
  .input:focus { outline: none; border-color: var(--sc-accent); }
</style>
