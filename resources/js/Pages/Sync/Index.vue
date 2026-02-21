<script setup>
import {
    getPendingCounts,
    listPendingSales,
    removePendingSale,
    retrySale,
    syncAll,
} from '@/offline/sync'
import { getLocalMeta } from '@/offline/snapshot'
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const loading = ref(false)
const syncing = ref(false)
const rows = ref([])
const counts = ref({ sales: 0, layaways: 0, total: 0, conflicts: 0 })
const lastSyncAt = ref(null)
const isOffline = ref(typeof window !== 'undefined' ? !window.navigator.onLine : false)

const hasRows = computed(() => rows.value.length > 0)

function money(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value ?? 0)
}

function totalFromRow(row) {
    const payments = row?.payload?.payments ?? []
    return payments.reduce((sum, p) => sum + Number(p.amount || 0), 0)
}

function dateTime(value) {
    if (!value) return '—'

    return new Date(value).toLocaleString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

async function reloadData() {
    loading.value = true

    try {
        const [pendingRows, pendingCounts, syncAt] = await Promise.all([
            listPendingSales(),
            getPendingCounts(),
            getLocalMeta(),
        ])

        rows.value = pendingRows
        counts.value = pendingCounts
        lastSyncAt.value = syncAt?.generated_at ?? null
    } finally {
        loading.value = false
    }
}

async function syncNow() {
    if (isOffline.value || syncing.value) return

    syncing.value = true
    try {
        await syncAll({ salesEndpoint: route('sync.sales.store'), includeConflicts: false })
        await reloadData()
    } finally {
        syncing.value = false
    }
}

async function retryRow(row) {
    if (isOffline.value || !row?.uuid) return

    syncing.value = true
    try {
        await retrySale(row.uuid, { salesEndpoint: route('sync.sales.store') })
        await reloadData()
    } finally {
        syncing.value = false
    }
}

async function removeRow(row) {
    if (!row?.uuid) return

    await removePendingSale(row.uuid)
    await reloadData()
}

async function onOnline() {
    isOffline.value = false
    await syncNow()
}

function onOffline() {
    isOffline.value = true
}

onMounted(async () => {
    await reloadData()

    window.addEventListener('online', onOnline)
    window.addEventListener('offline', onOffline)
})

onBeforeUnmount(() => {
    window.removeEventListener('online', onOnline)
    window.removeEventListener('offline', onOffline)
})
</script>

<template>
    <Head title="Pendientes offline" />

    <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-wide text-zinc-900">Pendientes offline</h1>
                <p class="text-sm text-stone-500">
                    Última sincronización:
                    <span class="font-medium text-stone-700">{{ dateTime(lastSyncAt) }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="isOffline ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                >
                    {{ isOffline ? 'Offline' : 'Online' }}
                </span>

                <button
                    type="button"
                    class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isOffline || syncing"
                    @click="syncNow"
                >
                    {{ syncing ? 'Sincronizando...' : 'Sincronizar ahora' }}
                </button>

                <button
                    type="button"
                    class="rounded-xl border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-100"
                    @click="router.visit(route('pos.index'))"
                >
                    Volver a POS
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-400">Pendientes</p>
                <p class="mt-1 text-2xl font-semibold text-zinc-900">{{ counts.sales }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-400">Conflictos</p>
                <p class="mt-1 text-2xl font-semibold text-amber-700">{{ counts.conflicts }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-400">Total cola</p>
                <p class="mt-1 text-2xl font-semibold text-zinc-900">{{ counts.total }}</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">UUID</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="6" class="px-4 py-8 text-center text-stone-500">Cargando pendientes...</td>
                        </tr>

                        <tr v-else-if="!hasRows">
                            <td colspan="6" class="px-4 py-8 text-center text-stone-500">No hay ventas pendientes offline.</td>
                        </tr>

                        <tr v-for="row in rows" :key="row.uuid" class="border-t border-stone-100 align-top">
                            <td class="px-4 py-3 text-stone-700">Venta</td>
                            <td class="px-4 py-3 font-mono text-xs text-stone-600">{{ row.uuid }}</td>
                            <td class="px-4 py-3 text-stone-700">{{ dateTime(row.created_at) }}</td>
                            <td class="px-4 py-3 font-semibold text-zinc-900">{{ money(totalFromRow(row)) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="row.status === 'conflict'
                                        ? 'bg-amber-100 text-amber-700'
                                        : row.status === 'synced'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-stone-100 text-stone-700'"
                                >
                                    {{ row.status === 'conflict' ? 'conflicto' : (row.status === 'synced' ? 'sincronizada' : 'pendiente') }}
                                </span>
                                <p v-if="row.last_error" class="mt-1 max-w-sm text-xs text-red-600">{{ row.last_error }}</p>
                                <ul v-if="row.status === 'conflict' && row.conflicts?.length" class="mt-1 list-disc pl-4 text-xs text-amber-700">
                                    <li v-for="conflict in row.conflicts" :key="`${row.uuid}-${conflict.id}`">
                                        {{ conflict.sku }} · {{ conflict.name }} ({{ conflict.status }})
                                    </li>
                                </ul>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-700 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="isOffline || syncing || row.status === 'synced'"
                                        @click="retryRow(row)"
                                    >
                                        Reintentar
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50"
                                        @click="removeRow(row)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
