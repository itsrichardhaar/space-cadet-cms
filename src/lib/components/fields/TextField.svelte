<script>
  let { value = $bindable(''), label = '', required = false, placeholder = '' } = $props();
</script>

<div class="field">
  {#if label}
    <label class="label">{label}{#if required}<span class="req"> *</span>{/if}</label>
  {/if}
  <input class="input" type="text" bind:value {placeholder} />
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .input { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 14px; width: 100%; transition: border-color .15s; }
  .input:focus { outline: none; border-color: var(--sc-accent); }
</style>
