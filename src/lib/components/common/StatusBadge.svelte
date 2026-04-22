<script>
  let { status } = $props();
  const MAP = { published: 'success', draft: 'muted', archived: 'warning', active: 'success', suspended: 'danger', pending: 'warning' };
</script>
<span class="badge badge--{MAP[status] ?? 'muted'}">{status}</span>
<style>
  .badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; text-transform: capitalize; }
  .badge--success { background: rgba(52,211,153,.15); color: #34d399; }
  .badge--muted   { background: rgba(136,136,153,.15); color: #888899; }
  .badge--warning { background: rgba(251,191,36,.15);  color: #fbbf24; }
  .badge--danger  { background: rgba(248,113,113,.15); color: #f87171; }
</style>
