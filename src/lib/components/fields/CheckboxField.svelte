<script>
  let { value = $bindable([]), label = '', required = false, options = {} } = $props();

  let choices = $derived(
    (options?.choices ?? []).map(c => typeof c === 'object' ? c : { label: c, value: c })
  );

  function toggle(v) {
    const arr = Array.isArray(value) ? [...value] : [];
    const i = arr.indexOf(v);
    if (i >= 0) arr.splice(i, 1); else arr.push(v);
    value = arr;
  }

  function isChecked(v) {
    return Array.isArray(value) && value.includes(v);
  }
</script>

<div class="field">
  {#if label}
    <span class="label">{label}{#if required}<span class="req"> *</span>{/if}</span>
  {/if}
  <div class="list">
    {#each choices as c}
      <label class="item">
        <input type="checkbox" checked={isChecked(c.value)} onchange={() => toggle(c.value)} />
        <span>{c.label}</span>
      </label>
    {/each}
    {#if !choices.length}
      <span class="empty">No choices defined in schema.</span>
    {/if}
  </div>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .list { display: flex; flex-direction: column; gap: 8px; }
  .item { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: var(--sc-text); }
  .item input { accent-color: var(--sc-accent); width: 15px; height: 15px; cursor: pointer; }
  .empty { font-size: 13px; color: var(--sc-text-muted); }
</style>
