import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const normalizedAppUrl = (env.APP_URL || '').trim();
    const appPathPrefix = (() => {
        if (!normalizedAppUrl) return '';

        try {
            const pathname = new URL(normalizedAppUrl).pathname || '';
            return pathname.endsWith('/') ? pathname.slice(0, -1) : pathname;
        } catch {
            return '';
        }
    })();

    const pwaScope = appPathPrefix ? `${appPathPrefix}/` : '/';
    const catalogStartUrl = `${appPathPrefix}/catalogo` || '/catalogo';
    const offlineFallbackUrl = `${appPathPrefix}/offline` || '/offline';

    return {
        plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false, // lo registramos manualmente en app.js
            includeAssets: [
                'favicon.ico',
                'pwa-192x192.png',
                'pwa-512x512.png',
                'pwa-512x512-maskable.png',
                'apple-touch-icon.png',
            ],
            manifest: {
                name: 'Samy Boutique',
                short_name: 'Samy',
                description: 'Catalogo de moda Samy Boutique. Ropa con estilo.',
                start_url: catalogStartUrl,
                scope: pwaScope,
                display: 'standalone',
                orientation: 'portrait',
                theme_color: '#0a0a0a',
                background_color: '#fafaf9',
                lang: 'es',
                categories: ['shopping', 'lifestyle'],
                icons: [
                    {
                        src: '../pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '../pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '../pwa-512x512-maskable.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                // Cachear todos los assets compilados por Vite
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
                globDirectory: 'public/build',
                navigateFallback: offlineFallbackUrl,
                additionalManifestEntries: [
                    { url: offlineFallbackUrl, revision: null },
                ],

                // Cache de runtime
                runtimeCaching: [
                    // Imágenes del storage de Laravel — cache-first
                    {
                        urlPattern: /\/storage\/.+\.(png|jpe?g|webp|gif|svg)$/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'samy-images',
                            expiration: {
                                maxEntries: 300,
                                maxAgeSeconds: 60 * 60 * 24 * 7, // 7 días
                            },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                    // Assets estáticos de build (js/css) — stale-while-revalidate
                    {
                        urlPattern: /\/build\/assets\/.+\.(js|css)$/i,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'samy-assets',
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                    // Listado del catálogo público — network-first con fallback a caché
                    {
                        urlPattern: /\/catalogo(?:\?.*)?$/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'samy-catalog-index',
                            networkTimeoutSeconds: 5,
                            expiration: {
                                maxEntries: 20,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                    // Detalle de producto público /catalogo/p/:sku — network-first con fallback a caché
                    {
                        urlPattern: /\/catalogo\/p\/.+$/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'samy-catalog-product-pages',
                            networkTimeoutSeconds: 5,
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24, // 1 día
                            },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                ],
            },
            // El SW se inyecta en el <head> por la etiqueta @vite en la blade
            devOptions: {
                enabled: false, // evitar conflictos con el dev server de Vite+Laravel
            },
        }),
    ],
    };
});
