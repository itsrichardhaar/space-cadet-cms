<script>
  let { meta, onpage } = $props();
  // meta: { page, per_page, total, total_pages, has_next }
</script>

{#if meta && meta.total_pages > 1}
<div class="pagination">
  <span class="pagination__info">{meta.total} items</span>
  <div class="pagination__controls">
    <button disabled={meta.page <= 1} onclick={() => onpage(meta.page - 1)}>‹ Prev</button>
    <span class="pagination__current">Page {meta.page} of {meta.total_pages}</span>
    <button disabled={!meta.has_next} onclick={() => onpage(meta.page + 1)}>Next ›</button>
  </div>
</div>
{/if}

<style>
  .pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 0 0; }
  .pagination__info { font-size: 13px; color: var(--sc-text-muted); }
  .pagination__controls { display: flex; align-items: center; gap: 10px; }
  .pagination__controls button { background: var(--sc-surface-2); border: 1px solid var(--sc-border); color: var(--sc-text); padding: 6px 12px; border-radius: var(--sc-radius); font-size: 13px; cursor: pointer; }
  .pagination__controls button:disabled { opacity: 0.4; cursor: not-allowed; }
  .pagination__controls button:hover:not(:disabled) { border-color: var(--sc-accent); }
  .pagination__current { font-size: 13px; color: var(--sc-text-muted); }
</style>
