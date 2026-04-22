<script>
  import { onMount, onDestroy } from 'svelte';
  import { EditorState }       from '@codemirror/state';
  import { EditorView, basicSetup } from 'codemirror';
  import { html }       from '@codemirror/lang-html';
  import { javascript } from '@codemirror/lang-javascript';
  import { css }        from '@codemirror/lang-css';
  import { php }        from '@codemirror/lang-php';
  import { oneDark }    from '@codemirror/theme-one-dark';

  let { value = $bindable(''), label = '', required = false, options = {} } = $props();

  let language = $derived(options?.language ?? 'html');
  let viewEl;
  let view = null;
  let fromView = false; // guard against update loop

  function getLang(lang) {
    switch (lang) {
      case 'javascript': case 'js': return javascript();
      case 'css':  return css();
      case 'php':  return php();
      default:     return html();
    }
  }

  onMount(() => {
    view = new EditorView({
      state: EditorState.create({
        doc: value || '',
        extensions: [
          basicSetup,
          oneDark,
          getLang(language),
          EditorView.updateListener.of(u => {
            if (u.docChanged) {
              fromView = true;
              value = u.state.doc.toString();
              fromView = false;
            }
          }),
        ],
      }),
      parent: viewEl,
    });
  });

  onDestroy(() => view?.destroy());

  // Sync external value changes into the editor (e.g. programmatic reset)
  $effect(() => {
    if (!fromView && view && value !== view.state.doc.toString()) {
      view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: value || '' } });
    }
  });
</script>

<div class="field">
  {#if label}
    <span class="label">{label}{#if required}<span class="req"> *</span>{/if}</span>
  {/if}
  <div class="editor" bind:this={viewEl}></div>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }
  .editor { border: 1px solid var(--sc-border); border-radius: var(--sc-radius); overflow: hidden; }
  /* CodeMirror overrides */
  :global(.cm-editor) { font-size: 13px; }
  :global(.cm-editor.cm-focused) { outline: none; }
  :global(.cm-scroller) { min-height: 160px; max-height: 500px; overflow-y: auto; }
</style>
