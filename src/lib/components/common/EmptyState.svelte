<script>
  let { icon = '◈', title = 'Nothing here yet', description = '', action = null } = $props();
</script>
<div class="empty">
  <div class="empty__icon">{icon}</div>
  <h3 class="empty__title">{title}</h3>
  {#if description}<p class="empty__desc">{description}</p>{/if}
  {#if action}{@render action()}{/if}
</div>
<style>
  .empty { text-align: center; padding: 60px 20px; color: var(--sc-text-muted); }
  .empty__icon { font-size: 40px; margin-bottom: 16px; opacity: 0.5; }
  .empty__title { margin: 0 0 8px; font-size: 16px; font-weight: 600; color: var(--sc-text); }
  .empty__desc { margin: 0 0 24px; font-size: 14px; }
</style>
