<script>
  import Modal from './Modal.svelte';

  let {
    open      = false,
    title     = 'Are you sure?',
    message   = '',
    confirmLabel = 'Confirm',
    cancelLabel  = 'Cancel',
    danger    = false,
    onconfirm = null,
    oncancel  = null,
  } = $props();

  function handleConfirm() { if (onconfirm) onconfirm(); }
  function handleCancel()  { if (oncancel)  oncancel();  }
</script>

<Modal {open} {title} onclose={handleCancel}>
  {#snippet children()}
    <p class="message">{message}</p>
  {/snippet}

  {#snippet footer()}
    <button class="btn btn--ghost" onclick={handleCancel}>{cancelLabel}</button>
    <button
      class="btn"
      class:btn--danger={danger}
      class:btn--primary={!danger}
      onclick={handleConfirm}
    >{confirmLabel}</button>
  {/snippet}
</Modal>

<style>
  .message {
    margin: 0;
    color: var(--sc-text-muted);
    line-height: 1.6;
  }

  .btn {
    padding: 8px 16px;
    border-radius: var(--sc-radius);
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: opacity .15s;
  }
  .btn:hover { opacity: .85; }

  .btn--primary {
    background: var(--sc-accent);
    color: #fff;
  }
  .btn--danger {
    background: var(--sc-danger);
    color: #fff;
  }
  .btn--ghost {
    background: transparent;
    border: 1px solid var(--sc-border);
    color: var(--sc-text-muted);
  }
  .btn--ghost:hover { color: var(--sc-text); border-color: var(--sc-text-muted); opacity: 1; }
</style>
