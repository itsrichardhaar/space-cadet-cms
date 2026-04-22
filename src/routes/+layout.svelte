<script>
  import '../app.css';
  import Toast from '$lib/components/common/Toast.svelte';
  import { userStore } from '$lib/stores/user.svelte.js';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import { onMount } from 'svelte';

  let { children } = $props();

  // Public routes that don't require auth
  const PUBLIC = ['/login'];

  onMount(() => {
    const path = $page.url.pathname.replace('/admin', '') || '/';
    const isPublic = PUBLIC.some(p => path === p || path.startsWith(p + '/'));

    if (!userStore.isLoggedIn && !isPublic) {
      goto('/admin/login');
    }
  });
</script>

{@render children()}
<Toast />
