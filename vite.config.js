import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [sveltekit()],
  server: {
    port: 5173,
    proxy: {
      '/api.php': {
        target: 'http://localhost:8000',
        changeOrigin: true
      },
      '/admin.php': {
        target: 'http://localhost:8000',
        changeOrigin: true
      },
      '/install.php': {
        target: 'http://localhost:8000',
        changeOrigin: true
      }
    }
  },
  build: {
    outDir: 'php/dist',
    emptyOutDir: true
  }
});
