<script>
  let { value = $bindable('#000000'), label = '', required = false } = $props();

  let hexInput = $state(value || '#000000');

  function onPickerInput(e) {
    value = e.target.value;
    hexInput = e.target.value;
  }

  function onHexInput(e) {
    hexInput = e.target.value;
    if (/^#[0-9a-fA-F]{6}$/.test(hexInput)) value = hexInput;
  }
</script>

<div class="field">
  {#if label}
    <label class="label">{label}{#if required}<span class="req"> *</span>{/if}</label>
  {/if}
  <div class="row">
    <input class="picker" type="color" value={value || '#000000'} oninput={onPickerInput} />
    <input class="hex" type="text" value={hexInput} oninput={onHexInput} maxlength="7" placeholder="#000000" />
    <span class="swatch" style="background:{value || '#000000'}"></span>
  </div>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .row { display: flex; align-items: center; gap: 10px; }
  .picker { width: 38px; height: 36px; padding: 2px 3px; border: 1px solid var(--sc-border); border-radius: var(--sc-radius); background: var(--sc-surface-2); cursor: pointer; }
  .hex { background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 8px 12px; color: var(--sc-text); font-size: 13px; width: 110px; font-family: var(--sc-font-mono); }
  .hex:focus { outline: none; border-color: var(--sc-accent); }
  .swatch { width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--sc-border); }
</style>
