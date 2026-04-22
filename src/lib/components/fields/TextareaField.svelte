<script>
  let { value = $bindable(''), label = '', required = false, placeholder = '', rows = 4 } = $props();
</script>

<div class="field">
  {#if label}
    <label class="label">{label}{#if required}<span class="req"> *</span>{/if}</label>
  {/if}
  <textarea class="textarea" bind:value {placeholder} {rows}></textarea>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .textarea { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 14px; width: 100%; resize: vertical; font-family: inherit; line-height: 1.5; transition: border-color .15s; }
  .textarea:focus { outline: none; border-color: var(--sc-accent); }
</style>
