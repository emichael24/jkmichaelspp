import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { registerServiceWorker } from './pwa';
import PwaInstallPrompt from './Components/PwaInstallPrompt.vue';
import '../css/app.css';
import { setOfflineAccount } from './lib/offlineStore';

const appName = import.meta.env.VITE_APP_NAME || 'JK Michaels';

createInertiaApp({
    title: (title) => title ? `${title} — ${appName}` : appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    async setup({ el, App, props, plugin }) {
        await setOfflineAccount(props.initialPage.props.auth).catch(() => {});
        router.on('navigate', event => { setOfflineAccount(event.detail.page.props.auth).catch(() => {}); });
        const pinia = createPinia();

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue);

        // Register global PWA install prompt component
        app.component('PwaInstallPrompt', PwaInstallPrompt);

        return app.mount(el);
    },
    progress: {
        color: '#F04000',
        showSpinner: true,
    },
});

// Register PWA service worker
if (import.meta.env.PROD) {
    registerServiceWorker();
}
