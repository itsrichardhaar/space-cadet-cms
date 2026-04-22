<script>
  import Breadcrumb from './Breadcrumb.svelte';

  /**
   * @type {{
   *   title?: string,
   *   crumbs?: { label: string, href?: string }[],
   *   actions?: import('svelte').Snippet
   * }}
   */
  let { title = '', crumbs = [], actions = null } = $props();
</script>

<header class="topbar">
  <div class="topbar__left">
    {#if crumbs.length > 0}
      <Breadcrumb {crumbs} />
    {:else if title}
      <h1 class="topbar__title">{title}</h1>
    {/if}
  </div>
  {#if actions}
    <div class="topbar__actions">
      {@render actions()}
    </div>
  {/if}
</header>

<style>
  .topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
    height: var(--sc-topbar-h);
    border-bottom: 1px solid var(--sc-border);
    flex-shrink: 0;
    background: var(--sc-surface);
  }
  .topbar__left {
    display: flex;
    align-items: center;
    min-width: 0;
  }
  .topbar__title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--sc-text);
  }
  .topbar__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
  }
</style>
