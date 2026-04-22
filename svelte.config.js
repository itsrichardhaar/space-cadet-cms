import adapter from '@sveltejs/adapter-static';

/** @type {import('@sveltejs/kit').Config} */
const config = {
  kit: {
    adapter: adapter({
      pages: 'php/dist',
      assets: 'php/dist',
      fallback: 'index.html',
      precompress: false,
      strict: false
    }),
    paths: {
      base: '/admin'
    }
  }
};

export default config;
