<script>
  let {
    item,
    selected  = false,
    selectable = false,
    onselect  = null,
    ondelete  = null,
    onclick   = null,
  } = $props();

  function thumbSrc(m) {
    if (m.thumb_path) return `/storage/thumbnails/${m.thumb_path}`;
    if (m.webp_path)  return `/storage/uploads/${m.webp_path}`;
    return `/storage/uploads/${m.filename}`;
  }

  function isImage(mime) {
    return mime?.startsWith('image/');
  }

  function fmtSize(bytes) {
    if (bytes >= 1_000_000) return (bytes / 1_000_000).toFixed(1) + ' MB';
    if (bytes >= 1_000)     return Math.round(bytes / 1_000) + ' KB';
    return bytes + ' B';
  }

  function ext(name) {
    return name?.split('.').pop()?.toUpperCase() ?? '?';
  }
</script>

<!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
<div
  class="card"
  class:card--selected={selected}
  class:card--clickable={!!onclick}
  onclick={() => onclick?.(item)}
>
  <div class="card__thumb">
    {#if isImage(item.mime_type)}
      <img src={thumbSrc(item)} alt={item.alt_text || item.original_name} loading="lazy" />
    {:else}
      <div class="card__file-icon">{ext(item.original_name)}</div>
    {/if}

    {#if selectable}
      <label class="card__check" onclick={(e) => e.stopPropagation()}>
        <input
          type="checkbox"
          checked={selected}
          onchange={() => onselect?.(item)}
        />
      </label>
    {/if}
  </div>

  <div class="card__meta">
    <span class="card__name truncate" title={item.original_name}>{item.original_name}</span>
    <span class="card__size">{fmtSize(item.size_bytes)}</span>
  </div>

  {#if ondelete}
    <button
      class="card__del"
      onclick={(e) => { e.stopPropagation(); ondelete(item); }}
      aria-label="Delete"
      title="Delete"
    >×</button>
  {/if}
</div>

<style>
  .card {
    position: relative;
    background: var(--sc-surface-2);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius);
    overflow: hidden;
    transition: border-color .15s;
  }
  .card--clickable { cursor: pointer; }
  .card--clickable:hover { border-color: var(--sc-accent); }
  .card--selected { border-color: var(--sc-accent); box-shadow: 0 0 0 2px rgba(124,106,247,.25); }

  .card__thumb {
    aspect-ratio: 4/3;
    background: var(--sc-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  .card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .card__file-icon {
    font-size: 11px;
    font-weight: 700;
    color: var(--sc-text-muted);
    letter-spacing: .05em;
    padding: 8px;
    background: var(--sc-border);
    border-radius: 4px;
  }

  .card__check {
    position: absolute;
    top: 6px;
    left: 6px;
    cursor: pointer;
  }
  .card__check input {
    width: 16px;
    height: 16px;
    accent-color: var(--sc-accent);
    cursor: pointer;
  }

  .card__meta {
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .card__name {
    font-size: 12px;
    color: var(--sc-text);
    display: block;
  }
  .card__size {
    font-size: 11px;
    color: var(--sc-text-muted);
  }

  .card__del {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 22px;
    height: 22px;
    background: rgba(0,0,0,.6);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 14px;
    line-height: 1;
    padding: 0;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }
  .card:hover .card__del { display: flex; }
  .card__del:hover { background: var(--sc-danger); }
</style>
