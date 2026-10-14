import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('mapbox-gl')) {
              return 'vendor-map';
            }
            if (id.includes('three') || id.includes('@photo-sphere-viewer')) {
              return 'vendor-three';
            }
            if (id.includes('recharts') || id.includes('d3-')) {
              return 'vendor-charts';
            }
            if (id.includes('react-markdown') || id.includes('remark-gfm') || id.includes('micromark') || id.includes('unist') || id.includes('mdast') || id.includes('vfile')) {
              return 'vendor-markdown';
            }
            if (id.includes('lucide-react')) {
              return 'vendor-icons';
            }
            if (id.includes('framer-motion')) {
              return 'vendor-motion';
            }
            if (id.includes('@radix-ui')) {
              return 'vendor-radix';
            }
            if (id.includes('/react/') || id.includes('/react-dom/') || id.includes('/react-router-dom/') || id.includes('\\react\\') || id.includes('\\react-dom\\') || id.includes('\\react-router-dom\\')) {
              return 'vendor-react';
            }
            return 'vendor-misc';
          }
        }
      }
    }
  },
  server: {
    port: 5173,
    allowedHosts: true,
    proxy: {
      '/api': {
        target: 'http://localhost:5000',
        changeOrigin: true
      },
      '/nvidia-api': {
        target: 'https://integrate.api.nvidia.com/v1',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/nvidia-api/, '')
      }
    }
  }
})
