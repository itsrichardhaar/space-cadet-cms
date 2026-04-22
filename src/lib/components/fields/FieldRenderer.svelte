<script>
  import TextField     from './TextField.svelte';
  import TextareaField from './TextareaField.svelte';
  import RichTextField from './RichTextField.svelte';
  import NumberField   from './NumberField.svelte';
  import ToggleField   from './ToggleField.svelte';
  import DateField     from './DateField.svelte';
  import SelectField   from './SelectField.svelte';
  import CheckboxField from './CheckboxField.svelte';
  import ColorField    from './ColorField.svelte';
  import CodeField     from './CodeField.svelte';
  import MediaField    from './MediaField.svelte';
  import RelationField from './RelationField.svelte';
  import RepeaterField from './RepeaterField.svelte';

  /**
   * fieldDef  – one entry from collection.fields
   * value     – current value ($bindable) – propagates up through the chain
   */
  let { fieldDef, value = $bindable(null) } = $props();

  // Normalise options — might come back as a JSON string from older API responses
  let opts = $derived.by(() => {
    const o = fieldDef?.options;
    if (!o) return {};
    if (typeof o === 'string') { try { return JSON.parse(o); } catch { return {}; } }
    return o;
  });
</script>

{#if fieldDef?.type === 'text'}
  <TextField     bind:value label={fieldDef.name} required={!!fieldDef.required} />
{:else if fieldDef?.type === 'textarea'}
  <TextareaField bind:value label={fieldDef.name} required={!!fieldDef.required} />
{:else if fieldDef?.type === 'richtext'}
  <RichTextField bind:value label={fieldDef.name} required={!!fieldDef.required} />
{:else if fieldDef?.type === 'number'}
  <NumberField   bind:value label={fieldDef.name} required={!!fieldDef.required} options={opts} />
{:else if fieldDef?.type === 'toggle'}
  <ToggleField   bind:value label={fieldDef.name} required={!!fieldDef.required} />
{:else if fieldDef?.type === 'date'}
  <DateField     bind:value label={fieldDef.name} required={!!fieldDef.required} />
{:else if fieldDef?.type === 'select'}
  <SelectField   bind:value label={fieldDef.name} required={!!fieldDef.required} options={opts} />
{:else if fieldDef?.type === 'checkbox'}
  <CheckboxField bind:value label={fieldDef.name} required={!!fieldDef.required} options={opts} />
{:else if fieldDef?.type === 'color'}
  <ColorField    bind:value label={fieldDef.name} required={!!fieldDef.required} />
{:else if fieldDef?.type === 'code'}
  <CodeField     bind:value label={fieldDef.name} required={!!fieldDef.required} options={opts} />
{:else if fieldDef?.type === 'media'}
  <MediaField    bind:value label={fieldDef.name} required={!!fieldDef.required} />
{:else if fieldDef?.type === 'relation'}
  <RelationField bind:value label={fieldDef.name} required={!!fieldDef.required} options={opts} />
{:else if fieldDef?.type === 'repeater'}
  <RepeaterField bind:value label={fieldDef.name} required={!!fieldDef.required} options={opts} />
{:else if fieldDef?.type}
  <p class="unknown">Unknown field type: <code>{fieldDef.type}</code></p>
{/if}

<style>
  .unknown { font-size: 12px; color: var(--sc-text-muted); padding: 8px; border: 1px dashed var(--sc-border); border-radius: var(--sc-radius); margin: 0; }
</style>
