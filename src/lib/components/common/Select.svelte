<script>
  /**
   * Space Cadet CMS — Custom Select
   * Replaces all native <select> elements. Dropdown is position:fixed so it
   * escapes any overflow:hidden ancestor without needing a portal.
   *
   * Usage:
   *   <Select bind:value={myVar} options={[{ value, label }, ...]} />
   *
   * Props:
   *   value      — bindable, the current value
   *   options    — [{ value, label }]
   *   placeholder — shown when no match is found (default "—")
   *   disabled
   *   id         — forwarded to the trigger button (for <label for>)
   *   class      — extra classes on the trigger button
   *   onchange   — optional callback(newValue) fired after value changes
   */
  let {
    value      = $bindable(''),
    options    = [],
    placeholder = '—',
    disabled   = false,
    id         = undefined,
    class: cls = '',
    onchange   = undefined,
  } = $props();

  let open       = $state(false);
  let focusIdx   = $state(0);
  let triggerEl  = $state(null);
  let dropEl     = $state(null);
  let pos        = $state({ top: 0, left: 0, width: 0 });

  const selectedLabel = $derived(
    options.find(o => String(o.value) === String(value))?.label ?? placeholder
  );

  function place() {
    if (!triggerEl) return;
    const r       = triggerEl.getBoundingClientRect();
    const dropH   = Math.min(options.length * 32 + 8, 240);
    const fitBelow = window.innerHeight - r.bottom >= dropH + 4;
    pos = {
      left:  r.left,
      width: r.width,
      top:   fitBelow ? r.bottom + 3 : r.top - dropH - 3,
    };
  }

  function openDrop() {
    if (disabled) return;
    place();
    focusIdx = Math.max(0, options.findIndex(o => String(o.value) === String(value)));
    open = true;
  }

  function closeDrop() {
    open = false;
  }

  function pick(opt) {
    value = opt.value;
    onchange?.(opt.value);
    closeDrop();
    triggerEl?.focus();
  }

  function onTriggerKey(e) {
    const nav = ['ArrowDown','ArrowUp','Enter',' '];
    if (nav.includes(e.key)) e.preventDefault();
    if (!open) {
      if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(e.key)) openDrop();
      return;
    }
    if (e.key === 'ArrowDown')       focusIdx = Math.min(focusIdx + 1, options.length - 1);
    else if (e.key === 'ArrowUp')    focusIdx = Math.max(focusIdx - 1, 0);
    else if (e.key === 'Enter' || e.key === ' ') pick(options[focusIdx]);
    else if (e.key === 'Escape' || e.key === 'Tab') closeDrop();
  }

  // Click outside: use pointerdown so we fire before option onclick
  function onOutside(e) {
    if (open && !triggerEl?.contains(e.target) && !dropEl?.contains(e.target)) {
      closeDrop();
    }
  }

  // Scroll focused option into view when keyboard-navigating
  $effect(() => {
    if (open && dropEl && focusIdx >= 0) {
      dropEl.children[focusIdx]?.scrollIntoView({ block: 'nearest' });
    }
  });
</script>

<svelte:window
  onpointerdown={onOutside}
  onscroll={closeDrop}
  onresize={closeDrop}
/>

<!-- Trigger button -->
<button
  bind:this={triggerEl}
  {id}
  class="sc-sel {cls}"
  class:sc-sel--open={open}
  class:sc-sel--disabled={disabled}
  type="button"
  {disabled}
  aria-haspopup="listbox"
  aria-expanded={open}
  onclick={openDrop}
  onkeydown={onTriggerKey}
>
  <span class="sc-sel__val">{selectedLabel}</span>
  <svg class="sc-sel__arr" viewBox="0 0 10 6" fill="none"
       stroke="currentColor" stroke-width="1.5"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M1 1l4 4 4-4"/>
  </svg>
</button>

<!-- Dropdown portal — position:fixed, escapes all overflow:hidden ancestors -->
{#if open}
  <div
    bind:this={dropEl}
    class="sc-sel__drop"
    role="listbox"
    style="top:{pos.top}px;left:{pos.left}px;width:{pos.width}px"
  >
    {#each options as opt, i}
      <button
        class="sc-sel__opt"
        class:sc-sel__opt--sel={String(opt.value) === String(value)}
        class:sc-sel__opt--hi={i === focusIdx}
        role="option"
        aria-selected={String(opt.value) === String(value)}
        type="button"
        onpointerdown={(e) => { e.stopPropagation(); pick(opt); }}
        onmouseenter={() => focusIdx = i}
      >{opt.label}</button>
    {/each}
  </div>
{/if}

<style>
  /* ── Trigger ─────────────────────────────────── */
  .sc-sel {
    display: inline-flex; align-items: center; justify-content: space-between;
    gap: 8px; width: 100%; padding: 8px 10px;
    background: var(--sc-surface-2); border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius); color: var(--sc-text);
    font-size: 13px; font-family: var(--sc-font); font-weight: 400;
    text-align: left; cursor: pointer; box-sizing: border-box;
    transition: border-color 0.1s;
  }
  .sc-sel:hover:not(.sc-sel--disabled) { border-color: var(--sc-border-strong); }
  .sc-sel--open   { border-color: var(--sc-accent); }
  .sc-sel--disabled { opacity: 0.5; cursor: not-allowed; }
  .sc-sel:focus-visible { outline: none; border-color: var(--sc-accent); box-shadow: 0 0 0 2px rgba(var(--sc-accent-rgb), 0.2); }

  .sc-sel__val {
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .sc-sel__arr {
    width: 10px; height: 6px; flex-shrink: 0;
    color: var(--sc-text-muted); transition: transform 0.15s;
  }
  .sc-sel--open .sc-sel__arr { transform: rotate(180deg); }

  /* ── Dropdown ────────────────────────────────── */
  .sc-sel__drop {
    position: fixed; z-index: 9000;
    background: var(--sc-surface-2);
    border: 1px solid var(--sc-border-strong);
    border-radius: var(--sc-radius-lg);
    padding: 3px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.28), 0 1px 4px rgba(0,0,0,0.14);
    overflow-y: auto; max-height: 240px;
  }

  /* ── Options ─────────────────────────────────── */
  .sc-sel__opt {
    display: block; width: 100%; text-align: left;
    padding: 6px 10px; border: none; border-radius: 2px;
    background: none; color: var(--sc-text);
    font-size: 13px; font-family: var(--sc-font);
    cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .sc-sel__opt--hi  { background: var(--sc-surface); }
  .sc-sel__opt--sel { color: var(--sc-accent); }
  .sc-sel__opt--sel.sc-sel__opt--hi { background: rgba(var(--sc-accent-rgb), 0.1); }
</style>
