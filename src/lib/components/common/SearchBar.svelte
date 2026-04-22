<script>
  let {
    value       = $bindable(''),
    onchange    = null,
    placeholder = 'Search…',
    debounce    = 350,
  } = $props();

  let timer;

  function handleInput(e) {
    value = e.target.value;
    clearTimeout(timer);
    timer = setTimeout(() => {
      if (onchange) onchange(value);
    }, debounce);
  }

  function handleClear() {
    value = '';
    clearTimeout(timer);
    if (onchange) onchange('');
  }
</script>

<div class="searchbar">
  <span class="searchbar__icon" aria-hidden="true">
    <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
      <circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5"/>
      <path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
  </span>
  <input
    type="search"
    class="searchbar__input"
    {placeholder}
    value={value}
    oninput={handleInput}
    aria-label={placeholder}
  />
  {#if value}
    <button class="searchbar__clear" onclick={handleClear} aria-label="Clear search">×</button>
  {/if}
</div>

<style>
  .searchbar {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 220px;
  }
  .searchbar__icon {
    position: absolute;
    left: 10px;
    color: var(--sc-text-muted);
    pointer-events: none;
    display: flex;
  }
  .searchbar__input {
    width: 100%;
    padding: 7px 32px 7px 32px;
    background: var(--sc-surface-2);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius);
    color: var(--sc-text);
    font-size: 13px;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
  }
  .searchbar__input::-webkit-search-cancel-button { display: none; }
  .searchbar__input::placeholder { color: var(--sc-text-muted); }
  .searchbar__input:focus { border-color: var(--sc-accent); }
  .searchbar__clear {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    color: var(--sc-text-muted);
    font-size: 16px;
    padding: 0;
    line-height: 1;
    cursor: pointer;
  }
  .searchbar__clear:hover { color: var(--sc-text); }
</style>
