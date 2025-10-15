import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig({
    plugins: [react(), tailwindcss()],
    base: './',
    publicDir: 'public', // Copy public directory to build
    server: {
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173'
    },
    build: {
        outDir: 'build',
        manifest: true,
        emptyOutDir: true,
        rollupOptions: {
            input: 'admin/src/index.tsx',
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]'
            }
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, './admin/src'),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: './admin/src/test/setup.ts',
        css: true,
        testTimeout: 10000,
    },
});