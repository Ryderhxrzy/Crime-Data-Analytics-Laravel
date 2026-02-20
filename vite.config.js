import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/js/crime-page.ts', 'resources/js/notification-manager.ts', 'resources/js/mapping-fullscreen.js'],
            refresh: true,
        }),
    ],
    define: {
        __PUSHER_KEY__: JSON.stringify(process.env.PUSHER_APP_KEY),
        __PUSHER_CLUSTER__: JSON.stringify(process.env.PUSHER_APP_CLUSTER),
        __PUSHER_HOST__: JSON.stringify(process.env.PUSHER_HOST),
        __PUSHER_PORT__: JSON.stringify(process.env.PUSHER_PORT),
        __PUSHER_SCHEME__: JSON.stringify(process.env.PUSHER_SCHEME),
    },
    server: {
        // Use IPv4 localhost instead of IPv6 to avoid CSP issues
        host: '127.0.0.1',
        port: 5173,
        hmr: {
            host: '127.0.0.1',
            port: 5173,
        },
    },
});
