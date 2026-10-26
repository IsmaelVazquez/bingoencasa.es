import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import symfonyPlugin from 'vite-plugin-symfony';
import path from 'path';

export default defineConfig({
  plugins: [tailwindcss(), symfonyPlugin(), react()],
  base: '/build/',
  resolve: {
    alias: {
      '@assets': path.resolve(import.meta.dirname, 'public/assets'),
    },
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    // Producción: sin sourcemaps (no exponer el código original) y minificado
    // con terser para que el bundle sea difícil de leer.
    sourcemap: false,
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
        passes: 2,
      },
      mangle: true,
      format: {
        comments: false,
      },
    },
    rollupOptions: {
      input: {
        app: './assets/main.jsx',
        admin: './assets/admin.js',
        fiesta: './assets/fiesta.js',
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    allowedHosts: 'all',
    cors: true,
    // El contenedor escucha en 5173, pero el host publica 5175. El navegador
    // (en el host) debe cargar los assets del dev server por 5175.
    origin: 'http://localhost:5175',
  },
});
