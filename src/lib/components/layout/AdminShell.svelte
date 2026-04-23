<script>
  import Sidebar from './Sidebar.svelte';

  let { children, title = '', actions = null } = $props();
</script>

<div class="shell">
  <Sidebar />
  <div class="shell__main">
    <header class="shell__header">
      <div class="shell__header-left">
        <span class="shell__breadcrumb-sep">/</span>
        {#if title}
          <span class="shell__title">{title}</span>
        {/if}
      </div>
      <div class="shell__header-right">
        {#if actions}
          <div class="shell__actions">
            {@render actions()}
          </div>
        {/if}
        <span class="shell__live">● LIVE</span>
      </div>
    </header>
    <main class="shell__content">
      {@render children()}
    </main>
  </div>
</div>

<style>
  .shell {
    display: flex;
    height: 100vh;
    overflow: hidden;
  }

  .shell__main {
    flex: 1;
    margin-left: var(--sc-sidebar-w);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .shell__header {
    height: var(--sc-topbar-h);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    background: var(--sc-surface);
    border-bottom: 1px solid var(--sc-border);
    flex-shrink: 0;
  }

  .shell__header-left {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .shell__breadcrumb-sep {
    font-family: var(--sc-font-mono);
    font-size: 12px;
    color: var(--sc-text-dim);
  }

  .shell__title {
    font-size: 12px;
    font-weight: 600;
    color: var(--sc-text);
  }

  .shell__header-right {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .shell__actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .shell__live {
    font-family: var(--sc-font-mono);
    font-size: 9px;
    letter-spacing: 0.08em;
    color: var(--sc-text-dim);
  }

  .shell__content {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px 40px;
  }
</style>
