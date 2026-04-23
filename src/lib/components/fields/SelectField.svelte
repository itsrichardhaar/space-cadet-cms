<script>
  import Select from '$lib/components/common/Select.svelte';

  let { value = $bindable(''), label = '', required = false, options = {} } = $props();

  let choices = $derived(
    (options?.choices ?? []).map(c => typeof c === 'object' ? c : { label: c, value: c })
  );
</script>

<div class="field">
  {#if label}
    <label class="label">{label}{#if required}<span class="req"> *</span>{/if}</label>
  {/if}
  <Select bind:value options={choices} placeholder="— Select —" />
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
</style>
