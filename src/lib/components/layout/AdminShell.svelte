<script>
  import Sidebar from './Sidebar.svelte';

  let { children, title = '', actions = null } = $props();
</script>

<div class="shell">
  <Sidebar />
  <div class="shell__main">
    {#if title || actions}
      <header class="shell__header">
        {#if title}
          <h1 class="shell__title">{title}</h1>
        {/if}
        {#if actions}
          <div class="shell__actions">
            {@render actions()}
          </div>
        {/if}
      </header>
    {/if}
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
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 28px 0;
    flex-shrink: 0;
  }

  .shell__title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: var(--sc-text);
  }

  .shell__actions {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .shell__content {
    flex: 1;
    overflow-y: auto;
    padding: 20px 28px 40px;
  }
</style>
