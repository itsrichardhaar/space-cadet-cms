<script>
  import AdminShell from '$lib/components/layout/AdminShell.svelte';
  import { api } from '$lib/api.js';
  import { notifications } from '$lib/stores/notifications.svelte.js';
  import { onMount } from 'svelte';

  // RBAC roles (ordered by permission level, highest first)
  const ROLES = [
    { key: 'super_admin', label: 'Super Admin' },
    { key: 'admin',       label: 'Admin' },
    { key: 'developer',   label: 'Developer' },
    { key: 'editor',      label: 'Editor' },
    { key: 'free_member', label: 'Free Member' },
    { key: 'paid_member', label: 'Paid Member' },
  ];

  // Capability groups with human labels
  const CAPABILITIES = [
    { group: 'Content',     caps: [
      { key: 'create_items',   label: 'Create items' },
      { key: 'update_items',   label: 'Update items' },
      { key: 'delete_items',   label: 'Delete items' },
      { key: 'publish_items',  label: 'Publish items' },
      { key: 'manage_pages',   label: 'Manage pages' },
      { key: 'manage_globals', label: 'Manage globals' },
      { key: 'manage_menus',   label: 'Manage menus' },
    ]},
    { group: 'Media',       caps: [
      { key: 'upload_media',   label: 'Upload media' },
      { key: 'delete_media',   label: 'Delete media' },
    ]},
    { group: 'Forms',       caps: [
      { key: 'view_submissions', label: 'View submissions' },
      { key: 'manage_forms',     label: 'Manage forms' },
    ]},
    { group: 'System',      caps: [
      { key: 'manage_users',     label: 'Manage users' },
      { key: 'manage_webhooks',  label: 'Manage webhooks' },
      { key: 'manage_api_keys',  label: 'Manage API keys' },
      { key: 'manage_settings',  label: 'Manage settings' },
      { key: 'manage_templates', label: 'Manage templates' },
      { key: 'view_audit_log',   label: 'View audit log' },
    ]},
  ];

  // Default capability matrix
  const DEFAULTS = {
    super_admin: new Set(CAPABILITIES.flatMap(g => g.caps.map(c => c.key))),
    admin:       new Set(['create_items','update_items','delete_items','publish_items','manage_pages','manage_globals','manage_menus','upload_media','delete_media','view_submissions','manage_forms','manage_users','manage_webhooks','manage_api_keys','manage_settings','manage_templates','view_audit_log']),
    developer:   new Set(['create_items','update_items','delete_items','publish_items','manage_pages','manage_globals','manage_menus','upload_media','delete_media','view_submissions','manage_forms','manage_templates','view_audit_log']),
    editor:      new Set(['create_items','update_items','publish_items','manage_pages','manage_globals','manage_menus','upload_media','view_submissions']),
    free_member: new Set(['create_items','update_items']),
    paid_member: new Set(['create_items','update_items','publish_items','upload_media']),
  };

  let perms  = $state({});  // role → Set of cap keys
  let saving = $state(false);
  let loading = $state(true);

  onMount(loadPerms);

  async function loadPerms() {
    loading = true;
    try {
      const res = await api.get('settings');
      const stored = JSON.parse(res.data?.role_permissions ?? 'null');
      if (stored) {
        perms = Object.fromEntries(Object.entries(stored).map(([r, caps]) => [r, new Set(caps)]));
      } else {
        perms = Object.fromEntries(Object.entries(DEFAULTS).map(([r, s]) => [r, new Set(s)]));
      }
    } catch (e) {
      // Fall back to defaults
      perms = Object.fromEntries(Object.entries(DEFAULTS).map(([r, s]) => [r, new Set(s)]));
    } finally {
      loading = false;
    }
  }

  function toggle(role, cap) {
    const s = new Set(perms[role] ?? []);
    if (s.has(cap)) s.delete(cap); else s.add(cap);
    perms = { ...perms, [role]: s };
  }

  function isGranted(role, cap) {
    return perms[role]?.has(cap) ?? false;
  }

  async function save() {
    saving = true;
    try {
      const serialized = Object.fromEntries(
        Object.entries(perms).map(([r, s]) => [r, [...s]])
      );
      await api.put('settings', { role_permissions: JSON.stringify(serialized) });
      notifications.success('Role permissions saved.');
    } catch (e) {
      notifications.error(e.message);
    } finally {
      saving = false;
    }
  }
</script>

<AdminShell title="Settings — Roles">
  <!-- Sub-nav -->
  <div class="subnav">
    <a href="/admin/settings" class="subnav-link">General</a>
    <a href="/admin/settings/roles" class="subnav-link subnav-link--active">Roles</a>
    <a href="/admin/settings/security" class="subnav-link">Security</a>
  </div>

  <p class="hint">Define which capabilities each role has. Super Admin always has all permissions.</p>

  {#if loading}
    <p class="muted">Loading…</p>
  {:else}
    <div class="matrix-wrap">
      <table class="matrix">
        <thead>
          <tr>
            <th class="cap-col">Capability</th>
            {#each ROLES as role}
              <th class="role-col" title={role.label}>{role.label.replace(' ', '\u00A0')}</th>
            {/each}
          </tr>
        </thead>
        <tbody>
          {#each CAPABILITIES as group}
            <tr class="group-row">
              <td colspan={ROLES.length + 1} class="group-label">{group.group}</td>
            </tr>
            {#each group.caps as cap}
              <tr>
                <td class="cap-name">{cap.label}</td>
                {#each ROLES as role}
                  <td class="check-cell">
                    {#if role.key === 'super_admin'}
                      <span class="lock-check" title="Always granted">✓</span>
                    {:else}
                      <input
                        type="checkbox"
                        checked={isGranted(role.key, cap.key)}
                        onchange={() => toggle(role.key, cap.key)}
                      />
                    {/if}
                  </td>
                {/each}
              </tr>
            {/each}
          {/each}
        </tbody>
      </table>
    </div>

    <div class="actions">
      <button class="btn btn--primary" onclick={save} disabled={saving}>
        {saving ? 'Saving…' : 'Save Permissions'}
      </button>
    </div>
  {/if}
</AdminShell>

<style>
  .subnav { display: flex; gap: 4px; margin-bottom: 20px; }
  .subnav-link { padding: 6px 14px; border-radius: var(--sc-radius); font-size: 13px; color: var(--sc-text-muted); text-decoration: none; border: 1px solid transparent; }
  .subnav-link:hover { background: var(--sc-surface-2); color: var(--sc-text); }
  .subnav-link--active { background: rgba(124,106,247,.1); color: var(--sc-accent); border-color: rgba(124,106,247,.2); }

  .hint { font-size: 13px; color: var(--sc-text-muted); margin: 0 0 20px; }
  .muted { color: var(--sc-text-muted); font-size: 13px; }

  .matrix-wrap { border: 1px solid var(--sc-border); border-radius: var(--sc-radius-lg); overflow: auto; margin-bottom: 20px; }
  .matrix { border-collapse: collapse; font-size: 13px; min-width: 700px; }
  .matrix th { padding: 10px 14px; background: var(--sc-surface-2); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--sc-text-muted); text-align: center; border-bottom: 1px solid var(--sc-border); white-space: nowrap; }
  .matrix th.cap-col { text-align: left; width: 200px; }
  .role-col { width: 96px; }
  .matrix td { padding: 9px 14px; border-bottom: 1px solid var(--sc-border); }
  .matrix tbody tr:last-child td { border-bottom: none; }
  .matrix tbody tr:hover td { background: var(--sc-surface-2); }
  .group-row td { background: var(--sc-surface-2) !important; }
  .group-label { font-size: 11px; font-weight: 700; color: var(--sc-text-muted); text-transform: uppercase; letter-spacing: .05em; }
  .cap-name { color: var(--sc-text); }
  .check-cell { text-align: center; }
  .check-cell input[type="checkbox"] { accent-color: var(--sc-accent); width: 14px; height: 14px; cursor: pointer; }
  .lock-check { color: var(--sc-text-muted); font-size: 13px; display: inline-block; }

  .actions { }
  .btn { padding: 8px 18px; border-radius: var(--sc-radius); font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
  .btn--primary { background: var(--sc-accent); color: #fff; }
  .btn--primary:hover:not(:disabled) { background: var(--sc-accent-hover); }
  .btn--primary:disabled { opacity: .5; cursor: not-allowed; }
</style>
