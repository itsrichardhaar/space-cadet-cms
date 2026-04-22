<script>
  import { onMount, onDestroy } from 'svelte';
  import Sortable from 'sortablejs';

  let {
    items      = [],
    onreorder  = null,
    handle     = '.drag-handle',
    animation  = 150,
    children,
  } = $props();

  let listEl;
  let sortable;

  onMount(() => {
    sortable = Sortable.create(listEl, {
      animation,
      handle,
      onEnd(evt) {
        if (evt.oldIndex === evt.newIndex) return;
        const arr = [...items];
        const [moved] = arr.splice(evt.oldIndex, 1);
        arr.splice(evt.newIndex, 0, moved);
        if (onreorder) onreorder(arr);
      },
    });
  });

  onDestroy(() => sortable?.destroy());
</script>

<div bind:this={listEl} class="sortable-list">
  {#each items as item, i (item.id ?? item._uid ?? i)}
    {@render children(item, i)}
  {/each}
</div>
