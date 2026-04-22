<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import MediaLibrary from '$lib/components/media/MediaLibrary.svelte';
</script>

<AdminShell title="Media Library">
  {#snippet children()}
    <div class="media-page">
      <MediaLibrary mode="library" />
    </div>
  {/snippet}
</AdminShell>

<style>
  .media-page {
    height: calc(100vh - var(--sc-topbar-h) - 60px);
    border: 1px solid var(--sc-border);
    border-radius: var(--sc-radius-lg);
    overflow: hidden;
    background: var(--sc-surface);
  }
</style>
