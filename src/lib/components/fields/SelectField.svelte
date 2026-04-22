<script>
  let { value = $bindable(''), label = '', required = false, options = {} } = $props();

  let choices = $derived(
    (options?.choices ?? []).map(c => typeof c === 'object' ? c : { label: c, value: c })
  );
</script>

<div class="field">
  {#if label}
    <label class="label">{label}{#if required}<span class="req"> *</span>{/if}</label>
  {/if}
  <select class="select" bind:value>
    <option value="">— Select —</option>
    {#each choices as c}
      <option value={c.value}>{c.label}</option>
    {/each}
  </select>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .select { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 14px; width: 100%; cursor: pointer; transition: border-color .15s; }
  .select:focus { outline: none; border-color: var(--sc-accent); }
</style>
