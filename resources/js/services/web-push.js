import { initializeApp, getApps } from 'firebase/app';
import { deleteToken, getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging';

const config = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

const installationKey = 'samy.notifications.installation.v1';
let messagingPromise;
let listenerAttached = false;

function inspectServiceWorker(registration) {
    const active = registration.active;
    const basic = {
        scope: registration.scope,
        scriptURL: active?.scriptURL || null,
        state: active?.state || null,
        controllerScriptURL: navigator.serviceWorker.controller?.scriptURL || null,
    };

    if (!active || typeof MessageChannel === 'undefined') {
        return Promise.resolve({ ...basic, workerResponse: 'unavailable' });
    }

    return new Promise((resolve) => {
        const channel = new MessageChannel();
        const timeout = window.setTimeout(() => {
            channel.port1.close();
            resolve({ ...basic, workerResponse: 'timeout' });
        }, 5000);

        channel.port1.onmessage = ({ data }) => {
            window.clearTimeout(timeout);
            channel.port1.close();
            resolve({ ...basic, ...data, workerResponse: data?.type === 'SAMY_FCM_PONG' ? 'received' : 'unexpected' });
        };

        try {
            active.postMessage({ type: 'SAMY_FCM_PING' }, [channel.port2]);
        } catch (_) {
            window.clearTimeout(timeout);
            channel.port1.close();
            resolve({ ...basic, workerResponse: 'post_failed' });
        }
    });
}

function attachForegroundListener(client) {
    if (listenerAttached) return;

    onMessage(client, async (payload) => {
        const notificationId = payload.data?.notification_id;
        console.info('[notificaciones] Mensaje FCM recibido en primer plano.', {
            notificationId: notificationId || null,
            hasNotification: Boolean(payload.notification),
        });
        window.dispatchEvent(new CustomEvent('samy:notification-received'));

        if (!payload.notification || Notification.permission !== 'granted') return;

        try {
            const registration = await navigator.serviceWorker.ready;
            await registration.showNotification(payload.notification.title || 'Samy Boutique', {
                body: payload.notification.body || 'Tienes un nuevo aviso.',
                icon: '/pwa-192x192.png',
                tag: notificationId || undefined,
                data: { notification_id: notificationId || null },
            });
        } catch (error) {
            console.error('[notificaciones] No se pudo mostrar el aviso FCM en primer plano.', error);
        }
    });

    listenerAttached = true;
}

function installationId() {
    let id = localStorage.getItem(installationKey);
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem(installationKey, id);
    }
    return id;
}

async function messaging() {
    if (!messagingPromise) {
        messagingPromise = (async () => {
            if (!('serviceWorker' in navigator) || !(await isSupported())) return null;
            const app = getApps().length ? getApps()[0] : initializeApp(config);
            const client = getMessaging(app);
            attachForegroundListener(client);
            return client;
        })();
    }
    return messagingPromise;
}

async function syncDevice({ requestPermission = false } = {}) {
    if (!('Notification' in window) || !config.apiKey) return { enabled: false };

    let permission = Notification.permission;
    if (requestPermission && permission === 'default') {
        permission = await Notification.requestPermission();
    }

    let token = null;
    if (permission === 'granted' && import.meta.env.VITE_FIREBASE_VAPID_KEY) {
        try {
            const client = await messaging();
            if (client) {
                // Reuse the PWA worker already controlling this origin. It
                // imports Firebase Messaging at install time; registering a
                // second worker at `/` would replace Workbox and can leave
                // Firebase without the app's active push registration.
                const registration = await navigator.serviceWorker.ready;
                const diagnostics = await inspectServiceWorker(registration);
                console.info('[notificaciones] Service worker activo para FCM.', {
                    ...diagnostics,
                });
                token = await getToken(client, {
                    vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
                    serviceWorkerRegistration: registration,
                });
            }
        } catch (error) {
            console.warn('[notificaciones] No se pudo obtener el token web.', error?.code || 'error');
        }
    }

    const id = installationId();
    await window.axios.post('/api/notification-devices', {
        installation_id: id,
        platform: 'web',
        name: `${navigator.platform || 'Navegador'} · ${location.hostname}`.slice(0, 120),
        token,
        permission_status: permission === 'granted' ? 'granted' : permission === 'denied' ? 'denied' : 'not_determined',
    });

    return { enabled: permission === 'granted' && Boolean(token), permission, installationId: id };
}

export async function initializeWebPush() {
    try {
        return await syncDevice();
    } catch (error) {
        console.warn('[notificaciones] El registro web no está disponible.', error?.response?.status || 'error');
        return { enabled: false };
    }
}

export async function enableWebPush() {
    return syncDevice({ requestPermission: true });
}

export async function unregisterWebPush() {
    const id = localStorage.getItem(installationKey);
    try {
        const client = await messaging();
        if (client) await deleteToken(client);
    } catch (_) {
        // Laravel still clears the server-side association during logout.
    } finally {
        localStorage.removeItem(installationKey);
    }
    return id;
}
