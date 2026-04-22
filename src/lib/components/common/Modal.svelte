<script>
  let { open = false, title = '', onclose = null, children, footer = null } = $props();

  function handleKey(e) { if (e.key === 'Escape' && onclose) onclose(); }
  function handleBackdrop(e) { if (e.target === e.currentTarget && onclose) onclose(); }
</script>

{#if open}
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div class="modal-backdrop" onclick={handleBackdrop} onkeydown={handleKey} role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal__header">
        <h2 class="modal__title">{title}</h2>
        {#if onclose}
          <button class="modal__close" onclick={onclose} aria-label="Close">×</button>
        {/if}
      </div>
      <div class="modal__body">{@render children()}</div>
      {#if footer}
        <div class="modal__footer">{@render footer()}</div>
      {/if}
    </div>
  </div>
{/if}

<style>
  .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 24px; }
  .modal { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); width: 100%; max-width: 520px; max-height: 90vh; display: flex; flex-direction: column; }
  .modal__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--sc-border); flex-shrink: 0; }
  .modal__title { margin: 0; font-size: 16px; font-weight: 700; }
  .modal__close { background: none; border: none; color: var(--sc-text-muted); font-size: 22px; cursor: pointer; padding: 0; line-height: 1; }
  .modal__close:hover { color: var(--sc-text); }
  .modal__body { padding: 20px 24px; overflow-y: auto; flex: 1; }
  .modal__footer { padding: 16px 24px; border-top: 1px solid var(--sc-border); display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }
</style>
