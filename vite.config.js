import { defineConfig } from 'vite';
import path from 'path';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

export default defineConfig({
  base: './',
  publicDir: false,
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(process.cwd(), 'resources/js/app.js'),
        style: path.resolve(process.cwd(), 'resources/scss/app-consolidated.scss'),
        materialWeb: path.resolve(process.cwd(), 'resources/js/material-web.js'),
        setup: path.resolve(process.cwd(), 'resources/js/setup.js'),
        'dark-mode': path.resolve(process.cwd(), 'resources/js/dark-mode.js'),
        spa: path.resolve(process.cwd(), 'resources/js/spa.js'),
        'unified-sidebar': path.resolve(process.cwd(), 'resources/js/unified-sidebar.js'),
        'calendar-clean': path.resolve(process.cwd(), 'resources/js/calendar-clean.js'),
        'calendar-test': path.resolve(process.cwd(), 'resources/js/calendar-test.js'),
      },
      output: {
        entryFileNames: 'assets/[name].js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: 'assets/[name].[ext]'
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
