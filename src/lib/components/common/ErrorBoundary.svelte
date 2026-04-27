<script>
  let { children, fallback = null } = $props();
  let error = $state(null);

  export function reset() { error = null; }
</script>

{#if error}
  {#if fallback}
    {@render fallback({ error, reset })}
  {:else}
    <div class="eb">
      <div class="eb__icon">⚠</div>
      <p class="eb__msg">{error?.message ?? 'Something went wrong'}</p>
      <button class="eb__btn" onclick={() => error = null}>Dismiss</button>
    </div>
  {/if}
{:else}
  {@render children()}
{/if}

<style>
  .eb { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 32px 20px; text-align: center; color: var(--sc-text-muted); }
  .eb__icon { font-size: 28px; margin-bottom: 10px; opacity: 0.6; }
  .eb__msg { font-size: 13px; margin: 0 0 16px; }
  .eb__btn { padding: 6px 14px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); color: var(--sc-text); border-radius: var(--sc-radius); font-size: 12px; cursor: pointer; }
  .eb__btn:hover { border-color: var(--sc-text-muted); }
</style>
