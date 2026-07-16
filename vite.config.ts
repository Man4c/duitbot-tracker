import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            registerType: 'autoUpdate',
            devOptions: {
                enabled: true,
                type: 'module',
                navigateFallback: '/offline.html',
            },
            includeAssets: [
                'favicon.ico',
                'apple-touch-icon.png',
                'icons/*.png',
                'brand/*.png',
                'offline.html',
            ],
            workbox: {
                navigateFallback: '/offline.html',
                navigateFallbackDenylist: [/^\/api\//, /^\/sanctum\//],
                cleanupOutdatedCaches: true,
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) =>
                            request.destination === 'font',
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'duitbot-fonts',
                            expiration: {
                                maxEntries: 20,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                        },
                    },
                ],
            },
            manifest: {
                id: '/',
                name: 'DuitBot Tracker',
                short_name: 'DuitBot',
                description: 'Catat pengeluaran lewat Telegram, pantau dari mana saja.',
                theme_color: '#0f766e',
                background_color: '#f0fdfa',
                display: 'standalone',
                start_url: '/dashboard',
                scope: '/',
                orientation: 'portrait-primary',
                categories: ['finance', 'productivity'],
                icons: [
                    {
                        src: '/icons/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/icon-maskable-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
        }),
    ],
});
