import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./admin/src/test/setup.ts'],
    testTimeout: 10000,
    css: true,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      exclude: [
        'node_modules/',
        'admin/src/test/',
        '**/*.test.{ts,tsx}',
        '**/*.spec.{ts,tsx}',
        '**/vite-env.d.ts',
        'build/',
      ],
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './admin/src'),
    },
  },
});