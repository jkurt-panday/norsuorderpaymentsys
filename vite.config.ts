import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        watch: {
            ignored: ['**/*.csv', '**/*.xlsx', '**/*.xls', '**/*.pdf', '**/storage/**'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    // Add specific font display strategy
                    fontDisplay: 'swap',
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    // Add build configuration for better font handling
    build: {
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    // Ensure fonts go to correct directory
                    if (assetInfo.name?.match(/\.(woff2?|ttf|eot|otf)$/)) {
                        return 'fonts/[name].[hash][extname]';
                    }
                    return 'assets/[name].[hash][extname]';
                }
            }
        },
        // Improve chunk splitting
        chunkSizeWarningLimit: 1000,
    },
    // Optimize dependencies
    optimizeDeps: {
        include: [
            'react',
            'react-dom',
            '@inertiajs/react',
            // Add any other dependencies that might cause issues
        ],
    },
});