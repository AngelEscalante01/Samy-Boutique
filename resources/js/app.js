import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import AppLayout from '@/Layouts/AppLayout.vue';
import { setupGlobalPrintResultHandler } from '@/bridges/printResultHandler';
import { setupAndroidPrinterBridgeGlobal } from '@/services/printSale';
import { registerSW } from 'virtual:pwa-register';

// Registrar el service worker — se actualiza automáticamente en segundo plano
registerSW({
    immediate: true,
    onOfflineReady() {
        console.info('[PWA] Samy Boutique lista para usar sin conexión.');
    },
});

setupGlobalPrintResultHandler();
setupAndroidPrinterBridgeGlobal();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ).then((page) => {
            const isAuthPage = name.startsWith('Auth/');
            const isWelcome = name === 'Welcome';
            const isPublicPage = name.startsWith('Public/');

            if (!isAuthPage && !isWelcome && !isPublicPage) {
                page.default.layout ??= AppLayout;
            }

            return page;
        }),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
