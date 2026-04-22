<script>
  let { value = $bindable(false), label = '', required = false } = $props();
</script>

<div class="field">
  <label class="toggle">
    <input type="checkbox" bind:checked={value} />
    <span class="track"><span class="thumb"></span></span>
    {#if label}
      <span class="label">{label}{#if required}<span class="req"> *</span>{/if}</span>
    {/if}
  </label>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
  input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; }
  .track { position: relative; width: 36px; height: 20px; background: var(--sc-border); border-radius: 10px; transition: background .2s; flex-shrink: 0; }
  input:checked ~ .track { background: var(--sc-accent); }
  .thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: white; border-radius: 50%; transition: transform .2s; }
  input:checked ~ .track .thumb { transform: translateX(16px); }
  .label { font-size: 14px; color: var(--sc-text); }
  .req { color: var(--sc-danger); }
</style>
