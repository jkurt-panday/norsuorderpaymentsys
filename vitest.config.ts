import path from 'node:path';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [
        react({
            babel: { plugins: ['babel-plugin-react-compiler'] },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/tests/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
});
