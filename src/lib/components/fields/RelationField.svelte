<script>
  import { onMount } from 'svelte';
  import api from '$lib/api.js';
  import { collectionsStore } from '$lib/stores/collections.svelte.js';

  let { value = $bindable(null), label = '', required = false, options = {} } = $props();

  let multiple    = $derived(!!(options?.multiple));
  let collSlug    = $derived(options?.collection ?? '');

  let items       = $state([]);
  let loading     = $state(false);
  let search      = $state('');
  let open        = $state(false);
  let collId      = $state(null);

  // Resolve collection slug → ID
  $effect(() => {
    if (!collSlug) return;
    collectionsStore.load().then(() => {
      const c = collectionsStore.findBySlug(collSlug);
      if (c) collId = c.id;
    });
  });

  async function openPicker() {
    if (!collId) return;
    open    = true;
    loading = true;
    try {
      items = (await api.get(`collections/${collId}/items`, { per_page: 200 })).data ?? [];
    } catch { items = []; }
    finally { loading = false; }
  }

  function pick(item) {
    if (multiple) {
      const arr = Array.isArray(value) ? [...value] : [];
      const i   = arr.indexOf(item.id);
      if (i >= 0) arr.splice(i, 1); else arr.push(item.id);
      value = arr;
    } else {
      value = item.id;
      open  = false;
    }
  }

  function removeId(id) {
    if (multiple) value = (Array.isArray(value) ? value : []).filter(v => v !== id);
    else value = null;
  }

  function isSelected(id) {
    if (multiple) return Array.isArray(value) && value.includes(id);
    return value === id;
  }

  let filtered = $derived(
    search
      ? items.filter(i => i.title?.toLowerCase().includes(search.toLowerCase()))
      : items
  );

  let selectedItems = $derived(
    items.filter(i => isSelected(i.id))
  );
</script>

<div class="field">
  {#if label}
    <span class="label">{label}{#if required}<span class="req"> *</span>{/if}</span>
  {/if}

  <div class="wrap">
    {#if !collSlug}
      <p class="hint">No collection configured for this field.</p>
    {:else}
      <!-- Selected values -->
      <div class="tags">
        {#each selectedItems as item}
          <span class="tag">
            {item.title}
            <button type="button" onclick={() => removeId(item.id)}>✕</button>
          </span>
        {/each}
        {#if !multiple && !value}
          <button type="button" class="pick-btn" onclick={openPicker}>Pick item…</button>
        {/if}
        {#if multiple}
          <button type="button" class="pick-btn" onclick={openPicker}>+ Add</button>
        {/if}
      </div>

      <!-- Inline dropdown picker -->
      {#if open}
        <div class="picker">
          <div class="picker-head">
            <input class="picker-search" type="search" placeholder="Search…" bind:value={search} />
            <button type="button" class="picker-close" onclick={() => open = false}>✕</button>
          </div>
          {#if loading}
            <p class="picker-hint">Loading…</p>
          {:else if !filtered.length}
            <p class="picker-hint">No items found.</p>
          {:else}
            <ul class="picker-list">
              {#each filtered as item}
                <li>
                  <button
                    type="button"
                    class="picker-item"
                    class:active={isSelected(item.id)}
                    onclick={() => pick(item)}
                  >
                    {item.title}
                    {#if isSelected(item.id)}<span>✓</span>{/if}
                  </button>
                </li>
              {/each}
            </ul>
          {/if}
        </div>
      {/if}
    {/if}
  </div>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .hint { font-size: 13px; color: var(--sc-text-muted); margin: 0; }

  .wrap { position: relative; }

  .tags { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; min-height: 36px; padding: 6px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); }
  .tag { display: inline-flex; align-items: center; gap: 5px; background: rgba(124,106,247,.15); color: var(--sc-accent); border-radius: 99px; padding: 3px 8px 3px 10px; font-size: 12.5px; }
  .tag button { background: none; border: none; color: inherit; cursor: pointer; font-size: 11px; padding: 0; line-height: 1; opacity: .7; }
  .tag button:hover { opacity: 1; }
  .pick-btn { background: none; border: 1px dashed var(--sc-border); border-radius: var(--sc-radius); padding: 4px 10px; color: var(--sc-text-muted); font-size: 12.5px; cursor: pointer; transition: border-color .15s, color .15s; }
  .pick-btn:hover { border-color: var(--sc-accent); color: var(--sc-accent); }

  .picker { position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 50; background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
  .picker-head { display: flex; align-items: center; gap: 8px; padding: 8px; border-bottom: 1px solid var(--sc-border); }
  .picker-search { flex: 1; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); padding: 6px 10px; color: var(--sc-text); font-size: 13px; }
  .picker-search:focus { outline: none; border-color: var(--sc-accent); }
  .picker-close { background: none; border: none; color: var(--sc-text-muted); cursor: pointer; font-size: 16px; padding: 2px 6px; }
  .picker-hint { color: var(--sc-text-muted); font-size: 13px; text-align: center; padding: 16px 0; margin: 0; }
  .picker-list { list-style: none; margin: 0; padding: 4px; max-height: 260px; overflow-y: auto; }
  .picker-item { width: 100%; text-align: left; background: none; border: none; color: var(--sc-text); font-size: 13.5px; padding: 7px 10px; border-radius: 4px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
  .picker-item:hover { background: var(--sc-surface-2); }
  .picker-item.active { color: var(--sc-accent); }
</style>
