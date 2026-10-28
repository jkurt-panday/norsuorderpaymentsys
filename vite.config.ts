<<<<<<< HEAD
import path from 'path';
=======
import inertia from '@inertiajs/vite';
>>>>>>> cf4b8bf68f2f3b3c3b2f5e39bf3b026e7927c5f6
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
<<<<<<< HEAD
=======
    server: {
        watch: {
            ignored: ['*/.csv', '*/.xlsx', '*/.xls', '*/.pdf', '*/storage/*'],
        },
    },
>>>>>>> cf4b8bf68f2f3b3c3b2f5e39bf3b026e7927c5f6
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
<<<<<<< HEAD
=======
        inertia(),
>>>>>>> cf4b8bf68f2f3b3c3b2f5e39bf3b026e7927c5f6
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
<<<<<<< HEAD
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});
=======
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
            'react-is',
            'recharts',
            '@inertiajs/react',
            // Add any other dependencies that might cause issues
        ],
    },
});
>>>>>>> cf4b8bf68f2f3b3c3b2f5e39bf3b026e7927c5f6
