<script>
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import ConfirmDialog from '$lib/components/common/ConfirmDialog.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { formatDate } from '$lib/utils/formatDate.js';

  const ALL_EVENTS = [
    'collection.created','collection.updated','collection.deleted',
    'item.created','item.updated','item.deleted',
    'page.created','page.updated','page.deleted',
    'media.uploaded','media.deleted',
    'form.submitted',
  ];

  let hookId   = $derived(parseInt($page.params.id));

  let loading   = $state(true);
  let saving    = $state(false);
  let testing   = $state(false);
  let notFound  = $state(false);
  let showDelete = $state(false);

  let name      = $state('');
  let url       = $state('');
  let secret    = $state('');
  let isActive  = $state(true);
  let events    = $state([]);
  let deliveries = $state([]);

  $effect(() => { void hookId; load(); });

  async function load() {
    loading = true;
    try {
      const [hRes, dRes] = await Promise.all([
        api.get(`webhooks/${hookId}`),
        api.get(`webhooks/${hookId}/deliveries`).catch(() => ({ data: [] })),
      ]);
      if (!hRes.data) { notFound = true; return; }
      const h = hRes.data;
      name     = h.name;
      url      = h.url;
      secret   = '';
      isActive = !!h.is_active;
      events   = Array.isArray(h.events) ? h.events : (typeof h.events === 'string' ? JSON.parse(h.events) : []);
      deliveries = dRes.data ?? [];
    } catch (e) {
      if (e.status === 404) notFound = true;
      else notifications.error(e.message);
    } finally {
      loading = false;
    }
  }

  function toggleEvent(evt) {
    events = events.includes(evt) ? events.filter(e => e !== evt) : [...events, evt];
  }

  async function save() {
    saving = true;
    try {
      const body = { name, url, is_active: isActive, events };
      if (secret) body.secret = secret;
      await api.put(`webhooks/${hookId}`, body);
      notifications.success('Webhook saved');
      secret = '';
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function test() {
    testing = true;
    try {
      await api.post(`webhooks/${hookId}/test`);
      notifications.success('Test ping sent');
      await api.get(`webhooks/${hookId}/deliveries`).then(r => { deliveries = r.data ?? []; });
    } catch (e) {
      notifications.error(e.message);
    } finally {
      testing = false;
    }
  }

  async function deleteHook() {
    showDelete = false;
    try {
      await api.delete(`webhooks/${hookId}`);
      notifications.success('Webhook deleted');
      goto('/admin/webhooks');
    } catch (e) {
      notifications.error(e.message);
    }
  }

  function statusColor(code) {
    if (code >= 200 && code < 300) return 'var(--sc-success)';
    return 'var(--sc-danger)';
  }
</script>

{#if notFound}
  <AdminShell title="Not found">
    {#snippet children()}<p class="muted"><a href="/admin/webhooks">Back to Webhooks</a></p>{/snippet}
  </AdminShell>
{:else}
  <AdminShell title={loading ? 'Loading…' : name}>
    {#snippet actions()}
      <a href="/admin/webhooks" class="btn btn--ghost">← All Webhooks</a>
      <button class="btn btn--secondary" onclick={test} disabled={testing}>{testing ? 'Sending…' : 'Test'}</button>
      <button class="btn btn--ghost btn--danger" onclick={() => showDelete = true}>Delete</button>
      <button class="btn btn--primary" onclick={save} disabled={saving || loading}>
        {saving ? 'Saving…' : 'Save'}
      </button>
    {/snippet}

    {#snippet children()}
      {#if loading}
        <p class="muted">Loading…</p>
      {:else}
        <div class="layout">
          <div class="main">
            <!-- Settings card -->
            <div class="card">
              <h3 class="card-title">Configuration</h3>
              <div class="field-row">
                <label class="label">Endpoint URL</label>
                <input class="input" type="url" bind:value={url} placeholder="https://…" />
              </div>
              <div class="field-row">
                <label class="label">Secret (leave blank to keep existing)</label>
                <input class="input" type="text" bind:value={secret} placeholder="New secret…" />
              </div>
              <div class="field-row">
                <label class="check-label">
                  <input type="checkbox" bind:checked={isActive} />
                  Active
                </label>
              </div>
            </div>

            <!-- Events card -->
            <div class="card">
              <h3 class="card-title">Events to listen for</h3>
              <div class="events-grid">
                {#each ALL_EVENTS as evt}
                  <label class="check-label">
                    <input type="checkbox" checked={events.includes(evt)} onchange={() => toggleEvent(evt)} />
                    {evt}
                  </label>
                {/each}
              </div>
            </div>

            <!-- Delivery log -->
            {#if deliveries.length > 0}
              <div class="card">
                <h3 class="card-title">Recent Deliveries</h3>
                <div class="deliveries">
                  {#each deliveries.slice(0, 20) as d (d.id)}
                    <div class="delivery-row">
                      <span class="status-code" style="color:{statusColor(d.status_code)}">{d.status_code}</span>
                      <span class="delivery-event">{d.event}</span>
                      <span class="delivery-dur">{d.duration_ms}ms</span>
                      <span class="delivery-date">{formatDate(d.fired_at)}</span>
                    </div>
                  {/each}
                </div>
              </div>
            {/if}
          </div>

          <aside class="sidebar">
            <div class="card">
              <h3 class="card-title">Name</h3>
              <input class="input" type="text" bind:value={name} />
            </div>
            <div class="card info-card">
              <h3 class="card-title">Info</h3>
              <p class="info-text">Payloads are signed with HMAC-SHA256. Verify using the <code>X-SpaceCadet-Signature</code> header.</p>
            </div>
          </aside>
        </div>
      {/if}
    {/snippet}
  </AdminShell>
{/if}

<ConfirmDialog
  open={showDelete}
  title="Delete webhook"
  message="Delete '{name}'? All delivery history will also be deleted."
  confirmLabel="Delete"
  danger={true}
  onconfirm={deleteHook}
  oncancel={() => showDelete = false}
/>

<style>
  .muted { color: var(--sc-text-muted); font-size: 13px; }
  .layout { display: grid; grid-template-columns: 1fr 240px; gap: 24px; align-items: start; }
  .main { display: flex; flex-direction: column; gap: 16px; }
  .sidebar { display: flex; flex-direction: column; gap: 14px; }
  .card { background: var(--sc-surface); border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); padding: 18px; }
  .card-title { margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sc-text-muted); }
  .field-row { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
  .field-row:last-child { margin-bottom: 0; }
  .label { font-size: 12px; font-weight: 600; color: var(--sc-text-muted); display: block; }
  .input { padding: 8px 12px; background: var(--sc-surface-2); border: 1px solid var(--sc-border); border-radius: var(--sc-radius); color: var(--sc-text); font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
  .input:focus { border-color: var(--sc-accent); }
  .check-label { display: flex; align-items: center; gap: 7px; font-size: 13px; color: var(--sc-text-muted); cursor: pointer; }
  .check-label input { accent-color: var(--sc-accent); cursor: pointer; }
  .events-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .deliveries { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--sc-border); border-radius: var(--sc-radius); overflow: hidden; }
  .delivery-row { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-bottom: 1px solid var(--sc-border); font-size: 12px; }
  .delivery-row:last-child { border-bottom: none; }
  .status-code { font-weight: 700; font-family: var(--sc-font-mono); flex-shrink: 0; }
  .delivery-event { flex: 1; color: var(--sc-text-muted); }
  .delivery-dur { color: var(--sc-text-muted); flex-shrink: 0; }
  .delivery-date { color: var(--sc-text-muted); flex-shrink: 0; }
  .info-card { padding: 14px 18px; }
  .info-text { margin: 0; font-size: 12px; color: var(--sc-text-muted); line-height: 1.6; }
  .info-text code { font-family: var(--sc-font-mono); color: var(--sc-accent); font-size: 11px; }
  .btn { padding: 8px 16px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
  .btn--ghost { background: transparent; border: 1px solid var(--sc-border); color: var(--sc-text-muted); }
  .btn--ghost:hover { color: var(--sc-text); }
  .btn--danger { border-color: var(--sc-danger); color: var(--sc-danger); }
  .btn--secondary { background: var(--sc-surface-2); border: 1px solid var(--sc-border); color: var(--sc-text); }
  .btn--secondary:hover { border-color: var(--sc-accent); color: var(--sc-accent); }
</style>
