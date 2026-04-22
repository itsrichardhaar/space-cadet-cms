<script>
  import { notifications } from '$lib/stores/notifications.svelte.js';
</script>

<div class="toast-container" aria-live="polite" aria-atomic="false">
  {#each notifications.items as toast (toast.id)}
    <div class="toast toast--{toast.type}" role="alert">
      <span class="toast__icon">
        {#if toast.type === 'success'}✓{:else if toast.type === 'error'}✕{:else if toast.type === 'warning'}⚠{:else}ℹ{/if}
      </span>
      <span class="toast__msg">{toast.message}</span>
      <button class="toast__close" onclick={() => notifications.remove(toast.id)} aria-label="Dismiss">×</button>
    </div>
  {/each}
</div>

<style>
  .toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 380px;
    width: calc(100vw - 48px);
  }

  .toast {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--sc-radius);
    border: 1px solid transparent;
    font-size: 13px;
    line-height: 1.4;
    animation: slide-in 0.2s ease;
  }

  @keyframes slide-in {
    from { transform: translateX(110%); opacity: 0; }
    to   { transform: translateX(0);   opacity: 1; }
  }

  .toast--success { background: rgba(52,211,153,.12); border-color: rgba(52,211,153,.3); color: #34d399; }
  .toast--error   { background: rgba(248,113,113,.12); border-color: rgba(248,113,113,.3); color: #f87171; }
  .toast--warning { background: rgba(251,191,36,.12); border-color: rgba(251,191,36,.3); color: #fbbf24; }
  .toast--info    { background: rgba(96,165,250,.12); border-color: rgba(96,165,250,.3); color: #60a5fa; }

  .toast__icon { font-size: 15px; flex-shrink: 0; }
  .toast__msg  { flex: 1; }

  .toast__close {
    background: none;
    border: none;
    color: inherit;
    opacity: 0.7;
    font-size: 18px;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    flex-shrink: 0;
  }
  .toast__close:hover { opacity: 1; }
</style>
