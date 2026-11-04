import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import { normalizePublicImageBase, publicImagePattern } from './resources/js/offline/image-cache-policy.js';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const publicImageBase = normalizePublicImageBase(env.VITE_PRODUCT_IMAGES_URL || env.R2_PUBLIC_URL);
    const r2ImagePattern = publicImagePattern(publicImageBase);

    const normalizeBasePath = (value) => {
        const raw = (value || '').trim();

        if (!raw || raw === '/') {
            return '/';
        }

        const withLeadingSlash = raw.startsWith('/') ? raw : `/${raw}`;
        const withoutTrailingSlash = withLeadingSlash.replace(/\/+$/, '');

        return `${withoutTrailingSlash}/`;
    };

    const appUrlPath = (() => {
        const normalizedAppUrl = (env.APP_URL || '').trim();
        if (!normalizedAppUrl) return '/';

        try {
            const pathname = new URL(normalizedAppUrl).pathname || '';
            return pathname || '/';
        } catch {
            return '/';
        }
    })();

    const appBase = normalizeBasePath(env.VITE_APP_BASE_PATH || appUrlPath);
    const appBasePrefix = appBase === '/' ? '' : appBase.slice(0, -1);

    const startUrl = `${appBasePrefix}/login`;
    const offlineFallbackUrl = `${appBasePrefix}/offline.html`;

    return {
        base: appBase,
        define: { __SAMY_PRODUCT_IMAGES_BASE__: JSON.stringify(publicImageBase) },
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
                start_url: startUrl,
                scope: appBase,
                display: 'standalone',
                orientation: 'any',
                theme_color: '#0a0a0a',
                background_color: '#fafaf9',
                lang: 'es',
                categories: ['shopping', 'lifestyle'],
                icons: [
                    {
                        src: `${appBasePrefix}/pwa-192x192.png`,
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: `${appBasePrefix}/pwa-512x512.png`,
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: `${appBasePrefix}/pwa-512x512-maskable.png`,
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                cleanupOutdatedCaches: true,
                skipWaiting: true,
                clientsClaim: true,
                // /sw.js imports this build. Inline the runtime so Workbox
                // does not resolve its relative chunk against the root URL.
                inlineWorkboxRuntime: true,
                modifyURLPrefix: {
                    'assets/': `${appBasePrefix}/build/assets/`,
                    'manifest.webmanifest': `${appBasePrefix}/build/manifest.webmanifest`,
                },
                // Firebase Messaging joins the existing Workbox worker instead
                // of registering a second worker for the same scope.
                importScripts: ['/firebase-messaging-sw.js'],
                // Cachear todos los assets compilados por Vite
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
                globDirectory: 'public/build',
                navigateFallback: null,
                additionalManifestEntries: [
                    { url: offlineFallbackUrl, revision: null },
                ],

                // Cache de runtime
                runtimeCaching: [
                    // Laravel renders the app pages. Fetch navigations from
                    // it and show the precached fallback only on network error.
                    {
                        urlPattern: ({ request, url }) => request.mode === 'navigate'
                            && url.origin === self.location.origin,
                        handler: 'NetworkOnly',
                        options: {
                            precacheFallback: { fallbackURL: offlineFallbackUrl },
                        },
                    },
                    // Imágenes del storage de Laravel — cache-first
                    {
                        urlPattern: ({ url }) => url.origin === self.location.origin
                            && !url.search
                            && /\/storage\/(?:samy\/)?products\/.+\.(png|jpe?g|webp|gif)$/i.test(url.pathname),
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'samy-images',
                            expiration: {
                                maxEntries: 300,
                                maxAgeSeconds: 60 * 60 * 24 * 7, // 7 días
                            },
                            cacheableResponse: { statuses: [200] },
                        },
                    },
                    // R2's immutable migrated keys have no extension. Only the
                    // configured public product prefix may enter this cache.
                    ...(r2ImagePattern ? [{
                        urlPattern: r2ImagePattern,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'samy-images',
                            fetchOptions: { credentials: 'omit', mode: 'cors', redirect: 'error' },
                            expiration: { maxEntries: 300, maxAgeSeconds: 60 * 60 * 24 * 7 },
                            cacheableResponse: { statuses: [200] },
                        },
                    }] : []),
                    // Assets estáticos de build (js/css) — stale-while-revalidate
                    {
                        urlPattern: /\/build\/assets\/.+\.(js|css)$/i,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'samy-assets',
                            cacheableResponse: { statuses: [200] },
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
                            cacheableResponse: { statuses: [200] },
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
                            cacheableResponse: { statuses: [200] },
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
