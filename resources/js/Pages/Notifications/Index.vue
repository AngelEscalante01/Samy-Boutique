<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { enableWebPush } from '@/services/web-push';

const props = defineProps({
    canSend: { type: Boolean, default: false },
    webPushConfigured: { type: Boolean, default: false },
});

const categories = [
    ['sale', 'Ventas'], ['sale_cancelled', 'Ventas canceladas'], ['inventory_low', 'Inventario bajo'],
    ['product_out_of_stock', 'Productos agotados'], ['layaway', 'Apartados'],
    ['layaway_payment', 'Abonos'], ['layaway_liquidated', 'Apartados liquidados'],
    ['layaway_due', 'Apartados próximos a vencer'], ['layaway_expired', 'Apartados vencidos'],
    ['cash_cut', 'Cortes de caja'], ['administrative', 'Avisos administrativos'],
];
const rows = ref([]);
const unread = ref(0);
const page = ref(1);
const lastPage = ref(1);
const category = ref('');
const preferences = ref({});
const deviceStatus = ref('Consultando');
const workerDiagnostics = ref('');
const showWorkerDiagnostics = ['127.0.0.1', 'localhost'].includes(window.location.hostname);
const error = ref('');
const success = ref('');
const busy = ref(false);
const users = ref([]);
const history = ref([]);
const form = ref({ title: '', message: '', priority: 'normal', roles: [], user_ids: [] });
const confirmSend = ref(false);
const previewCount = computed(() => {
    const selected = new Set(form.value.user_ids.map(Number));
    for (const user of users.value) {
        if (user.roles?.some((role) => form.value.roles.includes(role))) selected.add(Number(user.id));
    }
    return selected.size;
});

async function loadNotifications() {
    error.value = '';
    try {
        const { data } = await window.axios.get('/api/notifications', { params: { page: page.value, per_page: 20, ...(category.value ? { category: category.value } : {}) } });
        rows.value = data.data ?? [];
        lastPage.value = data.meta?.last_page ?? 1;
        const count = await window.axios.get('/api/notifications/unread-count');
        unread.value = count.data?.data?.unread_count ?? 0;
    } catch (_) {
        error.value = 'No se pudieron cargar las notificaciones. Revisa tu conexión e inténtalo de nuevo.';
    }
}

async function loadPreferences() {
    try {
        const { data } = await window.axios.get('/api/notification-preferences');
        preferences.value = data.data?.preferences ?? {};
    } catch (_) {
        error.value = 'No se pudieron cargar tus preferencias.';
    }
}

async function savePreferences() {
    busy.value = true;
    success.value = '';
    try {
        const { data } = await window.axios.put('/api/notification-preferences', { preferences: preferences.value });
        preferences.value = data.data?.preferences ?? preferences.value;
        success.value = 'Preferencias guardadas.';
    } catch (_) {
        error.value = 'No se pudieron guardar las preferencias.';
    } finally {
        busy.value = false;
    }
}

async function activateWebPush() {
    error.value = '';
    try {
        const result = await enableWebPush();
        deviceStatus.value = result.enabled ? 'Activadas en este navegador' : result.permission === 'granted' ? 'Permiso concedido; dispositivo pendiente de vincular' : (typeof Notification !== 'undefined' && Notification.permission === 'denied') ? 'Permiso denegado en el navegador' : 'Sin permiso';
        if (result.enabled) success.value = 'Este navegador quedó vinculado a tu cuenta.';
        else if (result.permission === 'granted') error.value = 'No se pudo vincular este navegador para recibir avisos. Revisa tu conexión y vuelve a intentarlo.';
    } catch (_) {
        error.value = 'No se pudo activar la recepción en este navegador.';
    }
}

async function markRead(item) {
    try {
        await window.axios.patch(`/api/notifications/${item.id}/read`);
        await loadNotifications();
    } catch (_) {
        error.value = 'No se pudo actualizar el estado de lectura.';
    }
}

async function markAllRead() {
    try {
        await window.axios.post('/api/notifications/read-all');
        await loadNotifications();
    } catch (_) {
        error.value = 'No se pudieron marcar como leídas.';
    }
}

function destination(item) {
    const id = String(item.resource_id ?? '');
    if (item.resource_type === 'sale' && /^\d+$/.test(id)) return `/sales/${id}`;
    if (item.resource_type === 'layaway' && /^\d+$/.test(id)) return `/layaways/${id}`;
    if (item.resource_type === 'cash_cut' && /^\d+$/.test(id)) return `/cash-cuts/${id}`;
    if (item.resource_type === 'product') return '/products';
    return '/notifications';
}

async function openItem(item) {
    if (!item.read_at) await markRead(item);
    window.location.assign(destination(item));
}

async function loadUsersAndHistory() {
    if (!props.canSend) return;
    try {
        const [userResponse, historyResponse] = await Promise.all([
            window.axios.get('/api/notifications/recipients', { params: { per_page: 100 } }),
            window.axios.get('/api/notifications/sent-history', { params: { per_page: 20 } }),
        ]);
        users.value = userResponse.data?.data ?? [];
        history.value = historyResponse.data?.data ?? [];
    } catch (_) {
        error.value = 'No se pudo cargar el directorio o historial de avisos.';
    }
}

async function sendManual() {
    error.value = '';
    success.value = '';
    if (!confirmSend.value || previewCount.value < 1 || previewCount.value > 100) return;
    busy.value = true;
    try {
        const { data } = await window.axios.post('/api/notifications/manual', {
            ...form.value,
            idempotency_key: crypto.randomUUID(),
            confirm: true,
        });
        success.value = `Aviso registrado para ${data.data.announcement.recipient_count} destinatarios.`;
        form.value = { title: '', message: '', priority: 'normal', roles: [], user_ids: [] };
        confirmSend.value = false;
        await loadUsersAndHistory();
        await loadNotifications();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'No se pudo enviar el aviso.';
    } finally {
        busy.value = false;
    }
}

function showWorkerDiagnostic(event) {
    const data = event.detail ?? {};
    workerDiagnostics.value = [
        `scope=${data.scope || 'sin registro'}`,
        `worker=${data.scriptURL || 'ninguno'} (${data.state || 'sin estado'})`,
        `controller=${data.controllerScriptURL || 'ninguno'}`,
        `registros=${data.registrationCount ?? 'sin respuesta'}`,
        `suscripción push=${data.hasPushSubscription === true ? 'presente' : data.hasPushSubscription === false ? 'ausente' : 'sin respuesta'}`,
        `registro FCM=${data.fcmRegistrationMatches === true ? 'coincide' : data.fcmRegistrationMatches === false ? 'difiere' : 'sin respuesta'}`,
        `cachés=${data.cacheCount ?? 'sin respuesta'}`,
        `fallback offline=${data.offlineFallbackCached === true ? 'en caché' : data.offlineFallbackCached === false ? 'ausente' : 'sin respuesta'}`,
        `actualización=${data.updateStatus || 'no comprobada'}`,
        `waiting=${data.waitingState || 'ninguno'}`,
        `respuesta=${data.workerResponse || 'desconocida'}`,
        `versión=${data.version || 'sin respuesta'}`,
        data.importError ? `error de importación=${data.importError}` : '',
        `Firebase Messaging=${data.messagingInitialized === true ? 'inicializado' : data.messagingInitialized === false ? 'no inicializado' : 'sin respuesta'}`,
        data.messagingUnavailable ? 'worker marcó Firebase como no disponible' : '',
    ].filter(Boolean).join(' · ');
}

async function inspectLocalWorker() {
    if (!showWorkerDiagnostics) return;

    if (!('serviceWorker' in navigator)) {
        workerDiagnostics.value = 'Service Worker API no disponible';
        return;
    }

    try {
        let registration = await navigator.serviceWorker.ready;
        let updateStatus = 'sin cambios';

        try {
            await registration.update();
            updateStatus = 'comprobada';
        } catch (error) {
            updateStatus = `falló (${error?.name || 'Error'})`;
        }

        if (registration.installing && !['installed', 'activated', 'redundant'].includes(registration.installing.state)) {
            updateStatus = 'instalando';
            await new Promise((resolve) => {
                const installing = registration.installing;
                const timeout = window.setTimeout(resolve, 5000);
                installing.addEventListener('statechange', () => {
                    if (['installed', 'activated', 'redundant'].includes(installing.state)) {
                        window.clearTimeout(timeout);
                        resolve();
                    }
                });
            });
        }

        if (registration.waiting) {
            updateStatus = 'activando worker actualizado';
            const waiting = registration.waiting;
            const controllerChanged = new Promise((resolve) => {
                const timeout = window.setTimeout(() => {
                    navigator.serviceWorker.removeEventListener('controllerchange', onControllerChange);
                    resolve(false);
                }, 5000);
                const onControllerChange = () => {
                    window.clearTimeout(timeout);
                    resolve(true);
                };
                navigator.serviceWorker.addEventListener('controllerchange', onControllerChange, { once: true });
            });
            waiting.postMessage({ type: 'SKIP_WAITING' });
            await controllerChanged;
            registration = await navigator.serviceWorker.ready;
        }

        const active = registration.active;
        const registrations = await navigator.serviceWorker.getRegistrations();
        const pageRegistration = await navigator.serviceWorker.getRegistration(window.location.href);
        const pushSubscription = await registration.pushManager?.getSubscription();
        const cacheCount = 'caches' in window ? (await caches.keys()).length : null;
        const offlineFallbackCached = 'caches' in window
            ? Boolean(await caches.match(new URL(`${import.meta.env.BASE_URL}offline.html`, window.location.origin).href))
            : null;
        const basic = {
            scope: registration.scope,
            scriptURL: active?.scriptURL || null,
            state: active?.state || null,
            controllerScriptURL: navigator.serviceWorker.controller?.scriptURL || null,
            registrationCount: registrations.length,
            hasPushSubscription: Boolean(pushSubscription),
            cacheCount,
            offlineFallbackCached,
            fcmRegistrationMatches: pageRegistration?.scope === registration.scope
                && pageRegistration?.active?.scriptURL === registration.active?.scriptURL,
            updateStatus,
            waitingState: registration.waiting?.state || null,
        };

        if (!active || typeof MessageChannel === 'undefined') {
            showWorkerDiagnostic({ detail: { ...basic, workerResponse: 'unavailable' } });
            return;
        }

        const result = await new Promise((resolve) => {
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

        showWorkerDiagnostic({ detail: result });
    } catch (error) {
        workerDiagnostics.value = `No se pudo consultar el Service Worker (${error?.name || 'Error'})`;
    }
}

onMounted(() => {
    deviceStatus.value = typeof Notification === 'undefined' ? 'No disponible en este navegador' : Notification.permission === 'granted' ? 'Permiso concedido' : Notification.permission === 'denied' ? 'Permiso denegado' : 'Sin permiso';
    inspectLocalWorker();
    loadNotifications();
    loadPreferences();
    loadUsersAndHistory();
    window.addEventListener('samy:notification-received', loadNotifications);
});

onUnmounted(() => {
    window.removeEventListener('samy:notification-received', loadNotifications);
});
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-6 app-card-stack app-page">
        <div class="flex flex-wrap items-end justify-between gap-3 app-heading">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Centro de avisos</p>
                <h1 class="mt-1 text-2xl font-bold text-stone-900">Notificaciones</h1>
                <p class="mt-1 text-sm text-stone-500">{{ unread }} sin leer · historial vinculado a tu cuenta</p>
            </div>
            <div class="flex gap-2">
                <select v-model="category" class="rounded-xl border-stone-300 text-sm" aria-label="Filtrar por categoría" @change="page = 1; loadNotifications()">
                    <option value="">Todas las categorías</option>
                    <option v-for="[value, label] in categories" :key="value" :value="value">{{ label }}</option>
                </select>
                <button class="rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50" @click="markAllRead">Marcar todas como leídas</button>
            </div>
        </div>

        <p v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ error }}</p>
        <p v-if="success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ success }}</p>

        <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm app-surface">
            <div v-if="rows.length" class="divide-y divide-stone-100">
                <button v-for="item in rows" :key="item.id" class="flex w-full gap-3 px-4 py-4 text-left transition hover:bg-stone-50 sm:px-6" @click="openItem(item)">
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="item.read_at ? 'bg-stone-200' : 'bg-amber-500'"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-stone-900">{{ item.title }}</span>
                            <time class="text-xs text-stone-400">{{ new Date(item.created_at).toLocaleString('es-MX') }}</time>
                        </span>
                        <span class="mt-1 block text-sm text-stone-600">{{ item.message }}</span>
                        <span class="mt-2 inline-flex rounded-full bg-stone-100 px-2.5 py-1 text-xs text-stone-600">{{ categories.find(([value]) => value === item.category)?.[1] || item.category }}</span>
                    </span>
                </button>
            </div>
            <div v-else class="px-6 py-16 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-800"><span class="text-xl">♧</span></div>
                <h2 class="mt-4 font-semibold text-stone-900">No hay notificaciones</h2>
                <p class="mt-1 text-sm text-stone-500">Los avisos de tu cuenta aparecerán aquí.</p>
            </div>
            <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-stone-100 px-4 py-3 text-sm">
                <button :disabled="page <= 1" class="text-amber-800 disabled:text-stone-300" @click="page--; loadNotifications()">Anterior</button>
                <span class="text-stone-500">Página {{ page }} de {{ lastPage }}</span>
                <button :disabled="page >= lastPage" class="text-amber-800 disabled:text-stone-300" @click="page++; loadNotifications()">Siguiente</button>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6 app-surface">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-stone-900">Este navegador</h2>
                    <p class="mt-1 text-sm text-stone-500">{{ deviceStatus }} · {{ webPushConfigured ? 'Firebase Web configurado' : 'Falta configurar la clave VAPID' }}</p>
                    <p class="mt-1 text-xs text-stone-400">El permiso se solicita únicamente cuando pulses el botón.</p>
                    <p v-if="showWorkerDiagnostics && workerDiagnostics" class="mt-2 max-w-3xl break-words text-xs text-stone-500">Diagnóstico local: {{ workerDiagnostics }}</p>
                </div>
                <button :disabled="!webPushConfigured" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" @click="activateWebPush">Activar notificaciones web</button>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6 app-surface">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="font-semibold text-stone-900">Preferencias</h2><p class="mt-1 text-sm text-stone-500">Elige qué categorías conservar en tu bandeja.</p></div>
                <button class="rounded-xl border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 disabled:opacity-50" :disabled="busy" @click="savePreferences">Guardar preferencias</button>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <label v-for="[key, label] in categories" :key="key" class="flex items-center gap-3 rounded-xl bg-stone-50 px-3 py-2.5 text-sm text-stone-700">
                    <input v-model="preferences[key]" type="checkbox" class="rounded border-stone-300 text-amber-700 focus:ring-amber-600" />{{ label }}
                </label>
                <label class="flex items-center gap-3 rounded-xl bg-stone-50 px-3 py-2.5 text-sm text-stone-700"><input v-model="preferences.all_sales" type="checkbox" class="rounded border-stone-300 text-amber-700 focus:ring-amber-600" />Todas las ventas</label>
                <label class="flex items-center gap-3 rounded-xl bg-stone-50 px-3 py-2.5 text-sm text-stone-700"><input v-model="preferences.push_enabled" type="checkbox" class="rounded border-stone-300 text-amber-700 focus:ring-amber-600" />Permitir push en este usuario</label>
            </div>
        </section>

        <section v-if="canSend" class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6 app-surface">
            <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Administración</p><h2 class="mt-1 text-lg font-semibold text-stone-900">Enviar aviso</h2><p class="mt-1 text-sm text-stone-500">El envío se limita a 100 cuentas y queda en el historial.</p></div>
            <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="sendManual">
                <label class="text-sm font-medium text-stone-700">Título<input v-model.trim="form.title" required maxlength="140" class="mt-1 w-full rounded-xl border-stone-300" /></label>
                <label class="text-sm font-medium text-stone-700">Prioridad<select v-model="form.priority" class="mt-1 w-full rounded-xl border-stone-300"><option value="low">Baja</option><option value="normal">Normal</option><option value="high">Alta</option></select></label>
                <label class="text-sm font-medium text-stone-700 md:col-span-2">Mensaje<textarea v-model.trim="form.message" required maxlength="500" rows="3" class="mt-1 w-full rounded-xl border-stone-300"></textarea><span class="text-xs text-stone-400">{{ form.message.length }}/500</span></label>
                <fieldset class="rounded-xl border border-stone-200 p-3"><legend class="px-1 text-sm font-medium text-stone-700">Roles</legend><div class="flex flex-wrap gap-4 text-sm"><label><input v-model="form.roles" type="checkbox" value="gerente" class="mr-2 rounded text-amber-700" />Gerente</label><label><input v-model="form.roles" type="checkbox" value="administrador" class="mr-2 rounded text-amber-700" />Administrador</label><label><input v-model="form.roles" type="checkbox" value="cajero" class="mr-2 rounded text-amber-700" />Cajero</label></div></fieldset>
                <label class="text-sm font-medium text-stone-700">Usuarios específicos <select v-model="form.user_ids" multiple size="4" class="mt-1 w-full rounded-xl border-stone-300"><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.roles?.join(', ') || 'sin rol' }}</option></select></label>
                <div class="md:col-span-2 rounded-xl bg-amber-50 p-4 text-sm text-amber-950"><p class="font-semibold">Vista previa</p><p class="mt-1">{{ form.title || 'Título del aviso' }} · {{ previewCount }} destinatarios seleccionados</p><label class="mt-3 flex items-start gap-2"><input v-model="confirmSend" type="checkbox" class="mt-0.5 rounded border-amber-400 text-amber-800" /><span>Confirmo enviar este aviso a las cuentas seleccionadas.</span></label></div>
                <div class="md:col-span-2"><button :disabled="busy || !confirmSend || previewCount < 1 || previewCount > 100 || !form.title || !form.message" class="rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-40">{{ busy ? 'Enviando…' : 'Enviar aviso' }}</button><span v-if="previewCount > 100" class="ml-3 text-sm text-rose-700">Reduce la audiencia a 100 cuentas.</span></div>
            </form>
            <div class="mt-6 border-t border-stone-100 pt-5"><h3 class="font-semibold text-stone-900">Avisos enviados</h3><div v-if="history.length" class="mt-3 divide-y divide-stone-100"> <div v-for="item in history" :key="item.id" class="py-3"><div class="flex flex-wrap justify-between gap-2 text-sm"><span class="font-medium text-stone-800">{{ item.title }}</span><span class="text-xs text-stone-400">{{ item.recipient_count }} destinatarios · {{ item.sender }}</span></div><p class="mt-1 text-sm text-stone-500">{{ item.message }}</p></div></div><p v-else class="mt-2 text-sm text-stone-500">No hay avisos enviados.</p></div>
        </section>
    </div>
</template>
