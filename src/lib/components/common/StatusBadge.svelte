<script>
  let { status } = $props();

  const COLOR_MAP = {
    published: 'mint',
    active:    'mint',
    draft:     'yellow',
    pending:   'yellow',
    archived:  'muted',
    suspended: 'coral',
  };

  const color = COLOR_MAP[status] ?? 'muted';
</script>
<span class="badge badge--{color}">{status}</span>
<style>
  .badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 3px;
    border: 1px solid rgba(0,0,0,0.15);
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.01em;
    text-transform: uppercase;
    white-space: nowrap;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.25), inset 0 -1px 0 rgba(0,0,0,0.12);
    color: #1a1814;
  }

  .badge--mint    { background: var(--sc-chip-mint); }
  .badge--yellow  { background: var(--sc-chip-yellow); }
  .badge--coral   { background: var(--sc-chip-coral); }
  .badge--muted   {
    background: transparent;
    border: 1px solid var(--sc-text-dim);
    color: var(--sc-text-dim);
    box-shadow: none;
  }
</style>
