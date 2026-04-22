/**
 * Space Cadet CMS — User Store (Svelte 5 runes)
 */

let _user = $state(window.__SC__?.user ?? null);

export const userStore = {
  get current() { return _user; },
  set(u) { _user = u; },
  clear() { _user = null; },
  get isLoggedIn() { return _user !== null; },
  get role() { return _user?.role ?? null; },
  hasRole(minRole) {
    const hierarchy = ['free_member','paid_member','editor','developer','admin','super_admin'];
    const mine = hierarchy.indexOf(_user?.role ?? '');
    const min  = hierarchy.indexOf(minRole);
    return mine >= min;
  },
};
