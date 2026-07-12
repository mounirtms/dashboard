import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],

  // CRITICAL: base must match the URL path where /build/ is served from.
  // Apache serves /build/ at /build/, so all asset URLs inside JS bundles
  // become /build/assets/... which is where they physically live.
  base: '/build/',

  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
      '@api': resolve(__dirname, 'src/api'),
      '@components': resolve(__dirname, 'src/components'),
      '@hooks': resolve(__dirname, 'src/hooks'),
      '@pages': resolve(__dirname, 'src/pages'),
      '@theme': resolve(__dirname, 'src/theme'),
      '@utils': resolve(__dirname, 'src/utils'),
    },
  },

  build: {
    // Build to a dashboard-owned temp dir to avoid permission issues
    // when public_html/build/assets/ is owned by a different process user.
    // post-build.sh copies the output to public_html/build/.
    outDir: '/tmp/dashboard-build',
    emptyOutDir: true,
    sourcemap: false,
    chunkSizeWarningLimit: 600,

    rollupOptions: {
      output: {
        manualChunks(id: string) {
          if (id.includes('node_modules/react') || id.includes('node_modules/react-dom'))
            return 'vendor-react';
          if (id.includes('node_modules/react-router'))
            return 'vendor-router';
          if (id.includes('node_modules/@mui/material') || id.includes('node_modules/@emotion'))
            return 'vendor-mui';
          if (id.includes('node_modules/@mui/icons-material'))
            return 'vendor-mui-icons';
          if (id.includes('node_modules/@mui/x-data-grid'))
            return 'vendor-mui-datagrid';
          if (id.includes('node_modules/recharts'))
            return 'vendor-recharts';
          if (id.includes('node_modules/axios'))
            return 'vendor-axios';
          if (id.includes('node_modules/'))
            return 'vendor-misc';
        },
      },
    },
  },

  server: {
    hmr: { overlay: true },
    watch: { usePolling: false },
  },
});
