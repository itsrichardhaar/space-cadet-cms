<script>
  // Supports two usage patterns:
  //   Simple:  <EmptyState title="…" message="…" action="Button label" onaction={fn} />
  //   Snippet: <EmptyState title="…" description="…">{#snippet action()}…{/snippet}</EmptyState>
  let { icon = '◈', title = 'Nothing here yet', description = '', message = '', action = null, onaction = null } = $props();
  const desc = description || message;
</script>

<div class="empty">
  <div class="empty__icon">{icon}</div>
  <h3 class="empty__title">{title}</h3>
  {#if desc}<p class="empty__desc">{desc}</p>{/if}
  {#if typeof action === 'function'}
    {@render action()}
  {:else if action && onaction}
    <button class="empty__btn" onclick={onaction}>{action}</button>
  {/if}
</div>

<style>
  .empty { text-align: center; padding: 60px 20px; color: var(--sc-text-muted); }
  .empty__icon { font-size: 40px; margin-bottom: 16px; opacity: 0.5; }
  .empty__title { margin: 0 0 8px; font-size: 16px; font-weight: 600; color: var(--sc-text); }
  .empty__desc { margin: 0 0 24px; font-size: 14px; }
  .empty__btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; background: var(--sc-accent); color: #fff;
    border: none; border-radius: var(--sc-radius); font-size: 13px;
    font-weight: 600; cursor: pointer; text-decoration: none;
  }
  .empty__btn:hover { background: var(--sc-accent-hover); }
</style>
