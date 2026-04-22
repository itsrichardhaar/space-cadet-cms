<script>
  import { page } from '$app/stores';
  import { userStore } from '$lib/stores/user.svelte.js';
  import api from '$lib/api.js';
  import { goto } from '$app/navigation';
  import { notifications } from '$lib/stores/notifications.svelte.js';

  const NAV = [
    { label: 'Dashboard',   href: '/dashboard',   icon: '◈',  roles: ['editor'] },
    { label: 'Collections', href: '/collections', icon: '⊞',  roles: ['editor'] },
    { label: 'Pages',       href: '/pages',       icon: '□',  roles: ['editor'] },
    { label: 'Globals',     href: '/globals',     icon: '◉',  roles: ['editor'] },
    { label: 'Media',       href: '/media',       icon: '▨',  roles: ['editor'] },
    { label: 'Menus',       href: '/menus',       icon: '≡',  roles: ['editor'] },
    { label: 'Forms',       href: '/forms',       icon: '✉',  roles: ['editor'] },
    { label: 'Members',     href: '/members',     icon: '◐',  roles: ['editor'] },
  ];

  const NAV2 = [
    { label: 'Templates',   href: '/templates',   icon: '</>',roles: ['developer'] },
    { label: 'Webhooks',    href: '/webhooks',    icon: '↑',  roles: ['admin'] },
    { label: 'Smart Forge', href: '/forge',       icon: '✦',  roles: ['editor'] },
    { label: 'Search',      href: '/search',      icon: '⌕',  roles: ['editor'] },
    { label: 'API Keys',    href: '/api-keys',    icon: '⚿',  roles: ['admin'] },
    { label: 'Settings',    href: '/settings',    icon: '⚙',  roles: ['admin'] },
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
    <svg width="28" height="28" viewBox="0 0 36 36" fill="none">
      <circle cx="18" cy="18" r="18" fill="#7c6af7"/>
      <path d="M10 24 L18 12 L26 24" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      <circle cx="18" cy="12" r="2.5" fill="white"/>
    </svg>
    <span>Space Cadet</span>
  </div>

  <nav class="sidebar__nav">
    {#each NAV as item}
      {#if canSee(item.roles)}
        <a href="/admin{item.href}" class="nav-item" class:nav-item--active={isActive(item.href)}>
          <span class="nav-item__icon">{item.icon}</span>
          <span>{item.label}</span>
        </a>
      {/if}
    {/each}

    <div class="sidebar__sep"></div>

    {#each NAV2 as item}
      {#if canSee(item.roles)}
        <a href="/admin{item.href}" class="nav-item" class:nav-item--active={isActive(item.href)}>
          <span class="nav-item__icon">{item.icon}</span>
          <span>{item.label}</span>
        </a>
      {/if}
    {/each}
  </nav>

  <div class="sidebar__footer">
    <div class="sidebar__user">
      <div class="sidebar__avatar">{userStore.current?.displayName?.[0] ?? '?'}</div>
      <div class="sidebar__user-info">
        <span class="sidebar__user-name">{userStore.current?.displayName}</span>
        <span class="sidebar__user-role">{userStore.current?.role}</span>
      </div>
    </div>
    <button class="sidebar__logout" onclick={logout} title="Sign out">⏻</button>
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
    gap: 10px;
    padding: 20px 16px 16px;
    font-size: 14px;
    font-weight: 700;
    color: var(--sc-text);
    border-bottom: 1px solid var(--sc-border);
  }

  .sidebar__nav {
    flex: 1;
    padding: 8px 8px;
    overflow-y: auto;
  }

  .sidebar__sep {
    height: 1px;
    background: var(--sc-border);
    margin: 8px 8px;
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 10px;
    border-radius: var(--sc-radius);
    color: var(--sc-text-muted);
    font-size: 13.5px;
    text-decoration: none;
    transition: background 0.12s, color 0.12s;
    margin-bottom: 1px;
  }

  .nav-item:hover { background: var(--sc-surface-2); color: var(--sc-text); }

  .nav-item--active {
    background: rgba(124,106,247,.15);
    color: var(--sc-accent);
  }

  .nav-item__icon {
    width: 18px;
    text-align: center;
    font-size: 14px;
    flex-shrink: 0;
  }

  .sidebar__footer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 12px;
    border-top: 1px solid var(--sc-border);
  }

  .sidebar__user {
    display: flex;
    align-items: center;
    gap: 9px;
    flex: 1;
    min-width: 0;
  }

  .sidebar__avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--sc-accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
    text-transform: uppercase;
  }

  .sidebar__user-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  .sidebar__user-name {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--sc-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .sidebar__user-role {
    font-size: 11px;
    color: var(--sc-text-muted);
    text-transform: capitalize;
  }

  .sidebar__logout {
    background: none;
    border: none;
    color: var(--sc-text-muted);
    font-size: 16px;
    padding: 4px 6px;
    border-radius: var(--sc-radius);
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.12s;
  }
  .sidebar__logout:hover { color: var(--sc-danger); }
</style>
