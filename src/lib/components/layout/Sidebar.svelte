<script>
  import { page } from '$app/stores';
  import { userStore } from '$lib/stores/user.svelte.js';
  import api from '$lib/api.js';
  import { goto } from '$app/navigation';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  const ICONS = {
    Dashboard:    `<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>`,
    Collections:  `<rect x="3" y="4" width="18" height="4" rx="1"/><rect x="3" y="10" width="18" height="4" rx="1"/><rect x="3" y="16" width="18" height="4" rx="1"/>`,
    Pages:        `<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>`,
    Globals:      `<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>`,
    Media:        `<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>`,
    Menus:        `<path d="M4 6h16M4 12h16M4 18h10"/>`,
    Forms:        `<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h6M7 13h10M7 17h4"/>`,
    Members:      `<circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-4-5.6"/>`,
    Templates:    `<rect x="3" y="3" width="18" height="5" rx="1"/><rect x="3" y="11" width="7" height="10" rx="1"/><rect x="13" y="11" width="8" height="10" rx="1"/>`,
    Assets:       `<path d="m16 18 5-5-5-5M8 6l-5 5 5 5M14 4l-4 16"/>`,
    Components:   `<path d="M5 3h14a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M3 13h8a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z"/><path d="M14 13h7a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z"/>`,
    Webhooks:     `<path d="M12 3v9M12 21a4 4 0 1 0-4-4M16 17a4 4 0 1 0-3.5-6M8 17h8"/>`,
    Blueprint:    `<path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/>`,
    Search:       `<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>`,
    ApiKeys:      `<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>`,
    Settings:     `<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>`,
    Backup:       `<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>`,
  };

  const NAV = [
    { label: 'Dashboard',   href: '/dashboard',   icon: 'Dashboard',   roles: ['editor'] },
    { label: 'Collections', href: '/collections', icon: 'Collections', roles: ['editor'] },
    { label: 'Pages',       href: '/pages',        icon: 'Pages',       roles: ['editor'] },
    { label: 'Globals',     href: '/globals',      icon: 'Globals',     roles: ['editor'] },
    { label: 'Media',       href: '/media',        icon: 'Media',       roles: ['editor'] },
    { label: 'Menus',       href: '/menus',        icon: 'Menus',       roles: ['editor'] },
    { label: 'Forms',       href: '/forms',        icon: 'Forms',       roles: ['editor'] },
    { label: 'Members',     href: '/members',      icon: 'Members',     roles: ['editor'] },
  ];

  const NAV2 = [
    { label: 'Templates',   href: '/templates',    icon: 'Templates',   roles: ['developer'] },
    { label: 'Components',  href: '/components',   icon: 'Components',  roles: ['developer'] },
    { label: 'Assets',      href: '/assets',       icon: 'Assets',      roles: ['developer'] },
    { label: 'Webhooks',    href: '/webhooks',      icon: 'Webhooks',    roles: ['admin'] },
    { label: 'Blueprint AI', href: '/blueprint',     icon: 'Blueprint',   roles: ['editor'] },
    { label: 'Search',      href: '/search',        icon: 'Search',      roles: ['editor'] },
    { label: 'API Keys',    href: '/api-keys',      icon: 'ApiKeys',     roles: ['admin'] },
    { label: 'Backup',      href: '/settings/backup', icon: 'Backup',    roles: ['admin'] },
    { label: 'Settings',    href: '/settings',      icon: 'Settings',    roles: ['admin'] },
  ];

  function isActive(href) {
    const current = $page.url.pathname.replace('/admin', '') || '/';
    return current === href || (href !== '/dashboard' && current.startsWith(href));
  }

  async function logout() {
    try {
      await api.post('logout');
    } finally {
      userStore.clear();
      goto('/admin/login');
    }
  }

  function canSee(roles) {
    return roles.some(r => userStore.hasRole(r));
  }
</script>

<aside class="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__logo">SC</div>
    <div class="sidebar__brand-text">
      <span class="sidebar__brand-name">Space Cadet</span>
      <span class="sidebar__brand-version">v0.1.5</span>
    </div>
  </div>

  <nav class="sidebar__nav">
    <div class="nav-group-label">CONTENT</div>
    {#each NAV as item}
      {#if canSee(item.roles)}
        <a href="/admin{item.href}" class="nav-item" class:nav-item--active={isActive(item.href)}>
          <svg class="nav-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            {@html ICONS[item.icon]}
          </svg>
          <span>{item.label}</span>
        </a>
      {/if}
    {/each}

    <div class="nav-group-label nav-group-label--tools">TOOLS</div>

    {#each NAV2 as item}
      {#if canSee(item.roles)}
        <a href="/admin{item.href}" class="nav-item" class:nav-item--active={isActive(item.href)}>
          <svg class="nav-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            {@html ICONS[item.icon]}
          </svg>
          <span>{item.label}</span>
        </a>
      {/if}
    {/each}
  </nav>

  <div class="sidebar__cpu-strip">
    <span class="sidebar__cpu-version">v0.1.5</span>
    <span class="sidebar__cpu-dot"></span>
  </div>

  <div class="sidebar__footer">
    <div class="sidebar__user">
      <div class="sidebar__avatar">{userStore.current?.displayName?.[0] ?? '?'}</div>
      <div class="sidebar__user-info">
        <span class="sidebar__user-name">{userStore.current?.displayName}</span>
        <span class="sidebar__user-role">{userStore.current?.role}</span>
      </div>
    </div>
    <button class="sidebar__logout" onclick={logout} title="Sign out">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
    </button>
  </div>
</aside>

<style>
  .sidebar {
    width: var(--sc-sidebar-w);
    height: 100vh;
    background: var(--sc-surface);
    border-right: 1px solid var(--sc-border);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
    overflow-y: auto;
  }

  .sidebar__brand {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 14px 12px 12px;
    border-bottom: 1px solid var(--sc-border);
    flex-shrink: 0;
  }

  .sidebar__logo {
    width: 22px;
    height: 22px;
    border-radius: 3px;
    background: linear-gradient(180deg, var(--sc-accent), var(--sc-chip-peach));
    color: #1a1814;
    font-size: 9px;
    font-weight: 800;
    font-family: var(--sc-font-mono);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: 0;
  }

  .sidebar__brand-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
  }

  .sidebar__brand-name {
    font-size: 12px;
    font-weight: 700;
    color: var(--sc-text);
    line-height: 1;
  }

  .sidebar__brand-version {
    font-size: 10px;
    font-family: var(--sc-font-mono);
    color: var(--sc-text-dim);
    line-height: 1;
  }

  .sidebar__nav {
    flex: 1;
    padding: 8px 0;
    overflow-y: auto;
  }

  .nav-group-label {
    font-size: 9px;
    font-family: var(--sc-font-mono);
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--sc-text-dim);
    padding: 4px 12px;
    margin-top: 2px;
    margin-bottom: 2px;
  }

  .nav-group-label--tools {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--sc-border);
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    border-left: 2px solid transparent;
    color: var(--sc-text-muted);
    font-size: 12.5px;
    text-decoration: none;
    transition: background 0.1s, color 0.1s;
  }

  .nav-item:hover {
    background: var(--sc-surface-2);
    color: var(--sc-text);
  }

  .nav-item--active {
    border-left-color: var(--sc-accent);
    background: var(--sc-accent-soft);
    color: var(--sc-text);
  }

  .nav-icon {
    flex-shrink: 0;
    opacity: 0.8;
  }

  .nav-item--active .nav-icon {
    opacity: 1;
  }

  /* CPU meter strip */
  .sidebar__cpu-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 12px;
    border-top: 1px solid var(--sc-border);
    border-bottom: 1px solid var(--sc-border);
    flex-shrink: 0;
  }

  .sidebar__cpu-version {
    font-family: var(--sc-font-mono);
    font-size: 9px;
    color: var(--sc-text-dim);
  }

  .sidebar__cpu-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--sc-success);
    box-shadow: 0 0 4px var(--sc-success);
  }

  .sidebar__footer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    flex-shrink: 0;
  }

  .sidebar__user {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
  }

  .sidebar__avatar {
    width: 26px;
    height: 26px;
    border-radius: 3px;
    background: linear-gradient(135deg, var(--sc-chip-lavender), var(--sc-chip-pink));
    color: #1a1814;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    text-transform: uppercase;
  }

  .sidebar__user-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 1px;
  }

  .sidebar__user-name {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--sc-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
  }

  .sidebar__user-role {
    font-size: 10px;
    font-family: var(--sc-font-mono);
    color: var(--sc-text-dim);
    text-transform: capitalize;
    line-height: 1;
  }

  .sidebar__logout {
    background: none;
    border: none;
    color: var(--sc-text-dim);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    border-radius: var(--sc-radius);
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.1s;
  }
  .sidebar__logout:hover { color: var(--sc-danger); }
</style>
