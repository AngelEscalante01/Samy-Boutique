<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    emails:   { type: Array,  required: true },
    settings: { type: Object, required: true },
});

// ─── Formulario: agregar correo ─────────────────────────────────────────────
const emailForm = useForm({
    email: '',
    label: '',
});

function addEmail() {
    emailForm.post(route('notifications.emails.store'), {
        preserveScroll: true,
        onSuccess: () => emailForm.reset(),
    });
}

// ─── Formulario: configuración de eventos ───────────────────────────────────
const settingsForm = useForm({
    sale_enabled:            !!props.settings?.sale_enabled,
    layaway_enabled:         !!props.settings?.layaway_enabled,
    layaway_payment_enabled: !!props.settings?.layaway_payment_enabled,
});

function saveSettings() {
    settingsForm.put(route('notifications.settings.update'), { preserveScroll: true });
}

// ─── Acciones sobre correos ──────────────────────────────────────────────────
function toggleEmail(id) {
    router.patch(route('notifications.emails.toggle', id), {}, { preserveScroll: true });
}

function deleteEmail(id, email) {
    if (!confirm(`¿Eliminar el correo "${email}"?`)) return;
    router.delete(route('notifications.emails.destroy', id), { preserveScroll: true });
}

// ─── Flash ────────────────────────────────────────────────────────────────────
const page = ref(null);
</script>

<template>
    <Head title="Notificaciones" />

    <div class="mx-auto max-w-4xl space-y-8">

        <!-- Título -->
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Notificaciones por correo</h1>
            <p class="text-sm text-gray-500">
                Configura a quién se le enviará un correo cuando se registre una venta, apartado o abono.
                Solo visible para el administrador.
            </p>
        </div>

        <!-- ── Eventos activos ──────────────────────────────────────────────── -->
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-1 text-sm font-semibold text-gray-900">Eventos que generan notificación</h2>
            <p class="mb-4 text-xs text-gray-400">Desactiva los eventos de los que no deseas recibir correo.</p>

            <form class="space-y-3" @submit.prevent="saveSettings">
                <label class="flex cursor-pointer items-center gap-3">
                    <input
                        v-model="settingsForm.sale_enabled"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-gray-900 shadow-sm"
                    />
                    <span class="text-sm text-gray-700">
                        <strong>Venta completada</strong>
                        <span class="ml-1 text-gray-400">— cuando el cajero procesa un pago en el POS.</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-center gap-3">
                    <input
                        v-model="settingsForm.layaway_enabled"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-gray-900 shadow-sm"
                    />
                    <span class="text-sm text-gray-700">
                        <strong>Apartado creado</strong>
                        <span class="ml-1 text-gray-400">— cuando se registra un nuevo apartado.</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-center gap-3">
                    <input
                        v-model="settingsForm.layaway_payment_enabled"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-gray-900 shadow-sm"
                    />
                    <span class="text-sm text-gray-700">
                        <strong>Abono a apartado</strong>
                        <span class="ml-1 text-gray-400">— cuando se registra un pago parcial en un apartado.</span>
                    </span>
                </label>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                        :disabled="settingsForm.processing"
                    >
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- ── Agregar correo ───────────────────────────────────────────────── -->
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-1 text-sm font-semibold text-gray-900">Agregar destinatario</h2>
            <p class="mb-4 text-xs text-gray-400">
                Puedes agregar uno o más correos. Cada uno recibirá una copia de cada notificación activa.
            </p>

            <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="addEmail">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Correo electrónico *</label>
                    <input
                        v-model="emailForm.email"
                        type="email"
                        placeholder="owner@ejemplo.com"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :class="{ 'border-red-400': emailForm.errors.email }"
                    />
                    <p v-if="emailForm.errors.email" class="mt-1 text-xs text-red-600">{{ emailForm.errors.email }}</p>
                </div>

                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Nombre (opcional)</label>
                    <input
                        v-model="emailForm.label"
                        type="text"
                        placeholder="Dueño, Administrador…"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <button
                    type="submit"
                    class="rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-400 disabled:opacity-50"
                    :disabled="emailForm.processing"
                >
                    + Agregar
                </button>
            </form>
        </div>

        <!-- ── Lista de destinatarios ──────────────────────────────────────── -->
        <div class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-900">
                    Destinatarios configurados
                    <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                        {{ emails.length }}
                    </span>
                </h2>
            </div>

            <!-- Sin correos -->
            <div v-if="emails.length === 0" class="px-6 py-10 text-center">
                <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
                <p class="text-sm text-gray-400">Aún no hay destinatarios. Agrega al menos un correo.</p>
            </div>

            <!-- Lista -->
            <ul v-else class="divide-y divide-gray-50">
                <li
                    v-for="item in emails"
                    :key="item.id"
                    class="flex items-center justify-between gap-4 px-6 py-4"
                >
                    <!-- Info -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <!-- Indicador activo/inactivo -->
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="item.active ? 'bg-green-400' : 'bg-gray-300'"
                            />
                            <span class="truncate text-sm font-medium text-gray-800">{{ item.email }}</span>
                            <span
                                v-if="!item.active"
                                class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-400"
                            >Inactivo</span>
                        </div>
                        <p v-if="item.label" class="mt-0.5 pl-4 text-xs text-gray-400">{{ item.label }}</p>
                    </div>

                    <!-- Acciones -->
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="rounded px-3 py-1.5 text-xs font-medium transition"
                            :class="item.active
                                ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                : 'bg-green-50 text-green-700 hover:bg-green-100'"
                            @click="toggleEmail(item.id)"
                        >
                            {{ item.active ? 'Desactivar' : 'Activar' }}
                        </button>

                        <button
                            type="button"
                            class="rounded px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 transition"
                            @click="deleteEmail(item.id, item.email)"
                        >
                            Eliminar
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Nota informativa -->
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-5 py-4">
            <p class="text-xs text-amber-800">
                <strong>Importante:</strong> Para que los correos se envíen correctamente debes configurar
                las variables <code>MAIL_HOST</code>, <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code>
                y <code>MAIL_FROM_ADDRESS</code> en el archivo <code>.env</code> del servidor.
            </p>
        </div>

    </div>
</template>
