<script>
  /**
   * FieldEditor — renders the appropriate input control for a block field definition
   * in the builder sidebar. Calls onchange(value) on every input event.
   *
   * Supported field types:
   *   text, textarea, number, toggle, color, select, richtext, media, code
   *
   * Note: richtext and media changes are passed through but trigger a full
   * iframe reload rather than DOM injection (handled by the parent page).
   */

  let { fieldDef, value = '', onchange } = $props();

  // Local mutable value tracked separately so bind:value works with the callback pattern
  let localValue = $state(value);

  // Keep local in sync if parent passes a new value (e.g. after a block switch)
  $effect(() => {
    localValue = value;
  });

  function emit(val) {
    localValue = val;
    onchange?.(val);
  }

  // Parse choices for select fields
  let choices = $derived.by(() => {
    const opts = fieldDef?.options;
    if (!opts) return [];
    let raw = opts;
    if (typeof raw === 'string') { try { raw = JSON.parse(raw); } catch { return []; } }
    const list = raw?.choices ?? raw ?? [];
    return Array.isArray(list)
      ? list.map(c => typeof c === 'object' ? c : { label: String(c), value: String(c) })
      : [];
  });
</script>

<div class="fe">
  {#if fieldDef?.label || fieldDef?.name}
    <label class="fe__label">{fieldDef.label ?? fieldDef.name}</label>
  {/if}

  {#if fieldDef?.type === 'text'}
    <input
      class="fe__input"
      type="text"
      value={localValue}
      oninput={(e) => emit(e.target.value)}
    />

  {:else if fieldDef?.type === 'textarea'}
    <textarea
      class="fe__textarea"
      oninput={(e) => emit(e.target.value)}
    >{localValue}</textarea>

  {:else if fieldDef?.type === 'number'}
    <input
      class="fe__input"
      type="number"
      value={localValue}
      oninput={(e) => emit(e.target.value === '' ? '' : Number(e.target.value))}
    />

  {:else if fieldDef?.type === 'toggle'}
    <label class="fe__toggle">
      <input
        type="checkbox"
        checked={!!localValue}
        onchange={(e) => emit(e.target.checked)}
      />
      <span class="fe__track"><span class="fe__thumb"></span></span>
      <span class="fe__toggle-label">{localValue ? 'On' : 'Off'}</span>
    </label>

  {:else if fieldDef?.type === 'color'}
    <div class="fe__color-row">
      <input
        class="fe__color-picker"
        type="color"
        value={localValue || '#000000'}
        oninput={(e) => emit(e.target.value)}
      />
      <input
        class="fe__color-hex"
        type="text"
        value={localValue || ''}
        maxlength="7"
        placeholder="#000000"
        oninput={(e) => {
          const v = e.target.value;
          if (/^#[0-9a-fA-F]{6}$/.test(v)) emit(v);
          else localValue = v;
        }}
      />
    </div>

  {:else if fieldDef?.type === 'select'}
    <select
      class="fe__select"
      value={localValue}
      onchange={(e) => emit(e.target.value)}
    >
      <option value="">— Select —</option>
      {#each choices as opt}
        <option value={opt.value} selected={localValue === opt.value}>{opt.label}</option>
      {/each}
    </select>

  {:else if fieldDef?.type === 'richtext'}
    <!-- Richtext — simple textarea; changes trigger full reload -->
    <textarea
      class="fe__textarea fe__textarea--rich"
      oninput={(e) => emit(e.target.value)}
    >{localValue}</textarea>
    <p class="fe__hint">Rich text — preview reloads on save</p>

  {:else if fieldDef?.type === 'media'}
    <!-- Media — URL input; changes trigger full reload -->
    <input
      class="fe__input"
      type="text"
      placeholder="Media URL or path"
      value={localValue}
      oninput={(e) => emit(e.target.value)}
    />
    <p class="fe__hint">Media — preview reloads on save</p>

  {:else if fieldDef?.type === 'code'}
    <textarea
      class="fe__textarea fe__textarea--code"
      oninput={(e) => emit(e.target.value)}
    >{localValue}</textarea>

  {:else if fieldDef?.type}
    <input
      class="fe__input"
      type="text"
      value={localValue}
      oninput={(e) => emit(e.target.value)}
    />
  {/if}
</div>

<style>
  .fe {
    display: flex;
    flex-direction: column;
    gap: 5px;
  }

  .fe__label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--sc-text-muted, #888);
    display: block;
  }

  .fe__input,
  .fe__textarea,
  .fe__select {
    background: var(--sc-surface-2, #f5f5f3);
    border: 1px solid var(--sc-border, #e8e8e6);
    border-radius: 4px;
    padding: 7px 10px;
    color: var(--sc-text, #1a1a1a);
    font-size: 13px;
    width: 100%;
    font-family: inherit;
    line-height: 1.4;
    transition: border-color 0.12s;
  }
  .fe__input:focus,
  .fe__textarea:focus,
  .fe__select:focus {
    outline: none;
    border-color: var(--sc-accent, #4f46e5);
  }

  .fe__textarea {
    resize: vertical;
    min-height: 72px;
  }

  .fe__textarea--rich {
    min-height: 90px;
    font-family: inherit;
  }

  .fe__textarea--code {
    min-height: 80px;
    font-family: var(--sc-font-mono, monospace);
    font-size: 12px;
  }

  /* Toggle */
  .fe__toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
  }
  .fe__toggle input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }
  .fe__track {
    position: relative;
    width: 34px;
    height: 18px;
    background: var(--sc-border, #e8e8e6);
    border-radius: 9px;
    transition: background 0.18s;
    flex-shrink: 0;
  }
  .fe__toggle input:checked ~ .fe__track { background: var(--sc-accent, #4f46e5); }
  .fe__thumb {
    position: absolute;
    top: 1px;
    left: 1px;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.18s;
  }
  .fe__toggle input:checked ~ .fe__track .fe__thumb { transform: translateX(16px); }
  .fe__toggle-label { font-size: 13px; color: var(--sc-text, #1a1a1a); }

  /* Color */
  .fe__color-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .fe__color-picker {
    width: 34px;
    height: 32px;
    padding: 2px;
    border: 1px solid var(--sc-border, #e8e8e6);
    border-radius: 4px;
    background: var(--sc-surface-2, #f5f5f3);
    cursor: pointer;
    flex-shrink: 0;
  }
  .fe__color-hex {
    background: var(--sc-surface-2, #f5f5f3);
    border: 1px solid var(--sc-border, #e8e8e6);
    border-radius: 4px;
    padding: 7px 10px;
    color: var(--sc-text, #1a1a1a);
    font-size: 13px;
    font-family: var(--sc-font-mono, monospace);
    width: 100px;
    transition: border-color 0.12s;
  }
  .fe__color-hex:focus { outline: none; border-color: var(--sc-accent, #4f46e5); }

  .fe__hint {
    font-size: 11px;
    color: var(--sc-text-dim, #bbb);
    margin: 0;
  }
</style>
