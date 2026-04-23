/**
 * Space Cadet CMS — Auth Helpers
 */

import { api } from './api.js';
import { userStore } from './stores/user.svelte.js';
import { goto } from '$app/navigation';

export async function checkAuth() {
  try {
    const res = await api.get('me');
    userStore.set(res.data);
    return true;
  } catch {
    return false;
  }
}

export async function logout() {
  try {
    await api.post('logout');
  } catch { /* ignore */ }
  userStore.clear();
  goto('/admin/login');
}
