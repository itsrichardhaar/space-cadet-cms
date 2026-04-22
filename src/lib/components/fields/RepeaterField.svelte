<script>
  import FieldRenderer from './FieldRenderer.svelte';

  let { value = $bindable([]), label = '', required = false, options = {} } = $props();

  let subFields = $derived(options?.fields ?? []);

  function addRow() {
    const row = {};
    for (const f of subFields) row[f.key] = null;
    value = [...(Array.isArray(value) ? value : []), row];
  }

  function removeRow(i) {
    value = (Array.isArray(value) ? value : []).filter((_, idx) => idx !== i);
  }

  let rows = $derived(Array.isArray(value) ? value : []);
</script>

<div class="field">
  {#if label}
    <span class="label">{label}{#if required}<span class="req"> *</span>{/if}</span>
  {/if}

  {#if !subFields.length}
    <p class="hint">No sub-fields defined in schema.</p>
  {:else}
    <div class="rows">
      {#each rows as _row, i}
        <div class="row">
          <div class="row-header">
            <span class="row-num">Row {i + 1}</span>
            <button type="button" class="del-btn" onclick={() => removeRow(i)}>Remove</button>
          </div>
          <div class="row-body">
            {#each subFields as f}
              <FieldRenderer fieldDef={f} bind:value={value[i][f.key]} />
            {/each}
          </div>
        </div>
      {/each}
    </div>
  {/if}

  <button type="button" class="add-btn" onclick={addRow}>+ Add row</button>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 8px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .hint { font-size: 13px; color: var(--sc-text-muted); margin: 0; }

  .rows { display: flex; flex-direction: column; gap: 8px; }
  .row { border: 1px solid var(--sc-border); border-radius: var(--sc-radius); overflow: hidden; }
  .row-header { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--sc-surface); border-bottom: 1px solid var(--sc-border); }
  .row-num { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); }
  .del-btn { background: none; border: none; color: var(--sc-danger); font-size: 12px; cursor: pointer; padding: 2px 6px; }
  .del-btn:hover { text-decoration: underline; }
  .row-body { padding: 14px 12px; display: flex; flex-direction: column; gap: 14px; background: var(--sc-surface-2); }

  .add-btn { background: none; border: 1px dashed var(--sc-border); border-radius: var(--sc-radius); padding: 8px 16px; color: var(--sc-text-muted); font-size: 13.5px; cursor: pointer; transition: border-color .15s, color .15s; width: 100%; }
  .add-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
