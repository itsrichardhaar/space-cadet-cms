<script>
  /** @type {{ label: string, href?: string }[]} */
  let { crumbs = [] } = $props();
</script>

<nav class="breadcrumb" aria-label="Breadcrumb">
  {#each crumbs as crumb, i}
    {#if i > 0}
      <span class="sep" aria-hidden="true">/</span>
    {/if}
    {#if crumb.href && i < crumbs.length - 1}
      <a href={crumb.href} class="link">{crumb.label}</a>
    {:else}
      <span class="current">{crumb.label}</span>
    {/if}
  {/each}
</nav>

<style>
  .breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
  }
  .sep     { color: var(--sc-text-muted); user-select: none; }
  .link    { color: var(--sc-text-muted); }
  .link:hover { color: var(--sc-text); }
  .current { color: var(--sc-text); font-weight: 500; }
</style>
