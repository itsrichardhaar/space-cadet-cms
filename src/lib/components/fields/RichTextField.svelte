<script>
  import { onMount, onDestroy } from 'svelte';
  import { Editor } from '@tiptap/core';
  import StarterKit from '@tiptap/starter-kit';
  import Underline from '@tiptap/extension-underline';
  import Link from '@tiptap/extension-link';

  let { value = $bindable(''), label = '', required = false } = $props();

  let editorEl;
  let editor = null;
  // Tick increments on every editor transaction to drive reactive toolbar state
  let tick = $state(0);

  let st = $derived.by(() => {
    void tick;
    if (!editor) return {};
    return {
      bold:        editor.isActive('bold'),
      italic:      editor.isActive('italic'),
      underline:   editor.isActive('underline'),
      strike:      editor.isActive('strike'),
      h1:          editor.isActive('heading', { level: 1 }),
      h2:          editor.isActive('heading', { level: 2 }),
      h3:          editor.isActive('heading', { level: 3 }),
      bulletList:  editor.isActive('bulletList'),
      orderedList: editor.isActive('orderedList'),
      link:        editor.isActive('link'),
      codeBlock:   editor.isActive('codeBlock'),
    };
  });

  onMount(() => {
    editor = new Editor({
      element: editorEl,
      extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false }),
      ],
      content: value || '',
      onTransaction: () => { tick++; },
      onUpdate: ({ editor: e }) => {
        const html = e.getHTML();
        value = html === '<p></p>' ? '' : html;
      },
    });
    tick = 1;
  });

  onDestroy(() => editor?.destroy());

  function cmd(name, ...args) {
    return () => editor?.chain().focus()[name](...args).run();
  }

  function setLink() {
    const prev = editor.getAttributes('link').href ?? '';
    const url  = prompt('URL:', prev || 'https://');
    if (url === null) return;
    if (!url) editor.chain().focus().unsetLink().run();
    else editor.chain().focus().setLink({ href: url }).run();
  }
</script>

<div class="field">
  {#if label}
    <span class="label">{label}{#if required}<span class="req"> *</span>{/if}</span>
  {/if}

  <div class="wrap">
    <div class="toolbar">
      <button type="button" class="tb" class:on={st.bold}        onclick={cmd('toggleBold')}                     title="Bold"><b>B</b></button>
      <button type="button" class="tb" class:on={st.italic}      onclick={cmd('toggleItalic')}                   title="Italic"><i>I</i></button>
      <button type="button" class="tb" class:on={st.underline}   onclick={cmd('toggleUnderline')}                title="Underline"><u>U</u></button>
      <button type="button" class="tb" class:on={st.strike}      onclick={cmd('toggleStrike')}                   title="Strike"><s>S</s></button>
      <span class="sep"></span>
      <button type="button" class="tb" class:on={st.h1}          onclick={cmd('toggleHeading', { level: 1 })}    title="H1">H1</button>
      <button type="button" class="tb" class:on={st.h2}          onclick={cmd('toggleHeading', { level: 2 })}    title="H2">H2</button>
      <button type="button" class="tb" class:on={st.h3}          onclick={cmd('toggleHeading', { level: 3 })}    title="H3">H3</button>
      <span class="sep"></span>
      <button type="button" class="tb" class:on={st.bulletList}  onclick={cmd('toggleBulletList')}               title="Bullets">• ―</button>
      <button type="button" class="tb" class:on={st.orderedList} onclick={cmd('toggleOrderedList')}              title="Numbers">1.</button>
      <span class="sep"></span>
      <button type="button" class="tb" class:on={st.link}        onclick={setLink}                               title="Link">🔗</button>
      <button type="button" class="tb" class:on={st.codeBlock}   onclick={cmd('toggleCodeBlock')}                title="Code">&lt;/&gt;</button>
    </div>

    <div class="editor" bind:this={editorEl}></div>
  </div>
</div>

<style>
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .04em; }
  .req { color: var(--sc-danger); }

  .wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius); overflow: hidden; }

  .toolbar { display: flex; align-items: center; gap: 1px; padding: 6px 8px; background: var(--sc-surface); border-bottom: 1px solid var(--sc-border); flex-wrap: wrap; }
  .tb { background: none; border: none; color: var(--sc-text-muted); font-size: 12.5px; font-family: inherit; padding: 3px 7px; border-radius: 4px; cursor: pointer; transition: background .12s, color .12s; line-height: 1; }
  .tb:hover { background: var(--sc-surface-2); color: var(--sc-text); }
  .tb.on { background: var(--sc-accent); color: #fff; }
  .sep { width: 1px; height: 16px; background: var(--sc-border); margin: 0 4px; flex-shrink: 0; }

  .editor { min-height: 180px; padding: 14px; background: var(--sc-surface-2); color: var(--sc-text); font-size: 14px; line-height: 1.65; }

  :global(.ProseMirror) { outline: none; min-height: 152px; }
  :global(.ProseMirror > * + *) { margin-top: 0.65em; }
  :global(.ProseMirror p) { margin: 0; }
  :global(.ProseMirror h1) { font-size: 1.6em; font-weight: 700; margin: 0; }
  :global(.ProseMirror h2) { font-size: 1.3em; font-weight: 700; margin: 0; }
  :global(.ProseMirror h3) { font-size: 1.1em; font-weight: 600; margin: 0; }
  :global(.ProseMirror ul, .ProseMirror ol) { padding-left: 1.4em; margin: 0; }
  :global(.ProseMirror a) { color: var(--sc-accent); text-decoration: underline; }
  :global(.ProseMirror code) { background: rgba(255,255,255,.08); border-radius: 3px; padding: 1px 5px; font-family: var(--sc-font-mono); font-size: .875em; }
  :global(.ProseMirror pre) { background: var(--sc-bg); border-radius: var(--sc-radius); padding: 12px 16px; overflow-x: auto; }
  :global(.ProseMirror pre code) { background: none; padding: 0; }
  :global(.ProseMirror p.is-editor-empty:first-child::before) { content: attr(data-placeholder); color: var(--sc-text-muted); float: left; height: 0; pointer-events: none; }
</style>
