import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  base: '/',
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
    // Build directly into the live public_html/build — single source of truth
    outDir: '../../public_html/build',
    emptyOutDir: true,
    sourcemap: false,
    // Raise the warning threshold slightly — MUI is large by nature
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      output: {
        // Manual chunk splitting for optimal caching
        manualChunks(id: string) {
          // Core React runtime — changes rarely
          if (id.includes('node_modules/react') || id.includes('node_modules/react-dom')) {
            return 'vendor-react';
          }
          // React-Router
          if (id.includes('node_modules/react-router')) {
            return 'vendor-router';
          }
          // MUI core + emotion
          if (
            id.includes('node_modules/@mui/material') ||
            id.includes('node_modules/@emotion')
          ) {
            return 'vendor-mui';
          }
          // MUI icons (very large — split separately)
          if (id.includes('node_modules/@mui/icons-material')) {
            return 'vendor-mui-icons';
          }
          // MUI DataGrid
          if (id.includes('node_modules/@mui/x-data-grid')) {
            return 'vendor-mui-datagrid';
          }
          // Recharts
          if (id.includes('node_modules/recharts')) {
            return 'vendor-recharts';
          }
          // Axios + other small utilities
          if (id.includes('node_modules/axios')) {
            return 'vendor-axios';
          }
          // Everything else in node_modules → vendor-misc
          if (id.includes('node_modules/')) {
            return 'vendor-misc';
          }
        },
      },
    },
  },
  // Improve dev-server HMR stability
  server: {
    hmr: { overlay: true },
    watch: { usePolling: false },
  },
});
