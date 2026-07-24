import { defineConfig } from 'vite';
import path from 'path';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

export default defineConfig({
  base: '/',
  publicDir: false,
  // Emit runtime asset URLs (e.g. `?url` imports, dynamic imports, CSS url())
  // as relative paths. The app serves the build output under /build/, not /,
  // so an absolute `/assets/...` URL 404s; a relative URL resolves correctly
  // from the importing chunk's location (/build/assets/) and stays correct
  // under sub-path deployments. Manifest-based loads (vite_helper) are
  // unaffected — they build their own /build/ paths.
  experimental: {
    renderBuiltUrl() {
      return { relative: true };
    },
  },
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      preserveEntrySignatures: 'strict',
      input: {
        main: path.resolve(process.cwd(), 'resources/js/app.js'),
        style: path.resolve(process.cwd(), 'resources/scss/app-consolidated.scss'),
        setup: path.resolve(process.cwd(), 'resources/js/setup.js'),
        'theme-bootstrap': path.resolve(process.cwd(), 'resources/js/theme-bootstrap.js'),
        'dark-mode': path.resolve(process.cwd(), 'resources/js/dark-mode.js'),
        'app-layout-init': path.resolve(process.cwd(), 'resources/js/layout/app-layout-init.js'),
        'public-booking-bootstrap': path.resolve(process.cwd(), 'resources/js/public-booking-bootstrap.js'),
        spa: path.resolve(process.cwd(), 'resources/js/spa.js'),
        'unified-sidebar': path.resolve(process.cwd(), 'resources/js/unified-sidebar.js'),
        charts: path.resolve(process.cwd(), 'resources/js/charts.js'),
        'public-booking': path.resolve(process.cwd(), 'resources/js/public-booking.js'),
        // Standalone developer API docs page (Redoc). Self-hosted, no CDN.
        developers: path.resolve(process.cwd(), 'resources/js/developers.js'),
      },
      output: {
        // Hash entry files too so production clients/CDNs do not serve stale JS.
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]'
      },
    },
  },
  css: {
    postcss: {
      plugins: [
        tailwindcss,
        autoprefixer,
      ],
    },
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        additionalData: '',
        silenceDeprecations: [
          'legacy-js-api',
          'import',
          'global-builtin',
          'color-functions'
        ],
      },
    },
  },
});
