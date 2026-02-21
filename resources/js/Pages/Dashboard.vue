<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// ── Props desde Inertia (con defaults para evitar errores si llegan vacíos) ──
const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            sales_total_today:  0,
            sales_count_today:  0,
            layaways_active:    0,
            products_available: 0,
            profit_today:       null,
        }),
    },
    recentSales:    { type: Array, default: () => [] },
    recentLayaways: { type: Array, default: () => [] },
    isManager:      { type: Boolean, default: false },
});

// ── Helpers ──────────────────────────────────────────────────────────────────
const todayLabel = computed(() => {
    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    }).format(new Date());
});

function money(v) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0);
}

// Cards para cajero
const cashierCards = computed(() => [
    {
        key: 'total',
        label: 'Ventas del día',
        value: money(props.stats.sales_total_today),
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        color: 'bg-blue-50 text-blue-600',
        ring:  'ring-blue-100',
    },
    {
        key: 'count',
        label: '# Ventas realizadas',
        value: props.stats.sales_count_today,
        icon: 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4',
        color: 'bg-indigo-50 text-indigo-600',
        ring:  'ring-indigo-100',
    },
    {
        key: 'layaways',
        label: 'Apartados activos',
        value: props.stats.layaways_active,
        icon: 'M19 11H5m14 0a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2m14 0V9a2 2 0 0 0-2-2M5 11V9a2 2 0 0 1 2-2m0 0V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2M7 7h10',
        color: 'bg-amber-50 text-amber-600',
        ring:  'ring-amber-100',
    },
]);

// Cards adicionales para gerente
const managerCards = computed(() => [
    ...cashierCards.value,
    {
        key: 'products',
        label: 'Productos disponibles',
        value: props.stats.products_available,
        icon: 'M6 7h12M6 7l1 14h10l1-14M6 7V5a3 3 0 0 1 6 0v2',
        color: 'bg-green-50 text-green-600',
        ring:  'ring-green-100',
    },
    ...(props.stats.profit_today !== null ? [{
        key: 'profit',
        label: 'Ganancia estimada',
        value: money(props.stats.profit_today),
        icon: 'M13 7h8m0 0v8m0-8-8 8-4-4-6 6',
        color: 'bg-emerald-50 text-emerald-600',
        ring:  'ring-emerald-100',
    }] : []),
]);

const displayCards = computed(() => props.isManager ? managerCards.value : cashierCards.value);
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto max-w-7xl space-y-6">

        <!-- ── Encabezado ──────────────────────────────────────────────── -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
                <p class="mt-0.5 text-sm capitalize text-gray-500">{{ todayLabel }} · Resumen del día</p>
            </div>

            <!-- Botones de acción según rol -->
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('pos.index')"
                    class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 7h10M7 11h10M7 15h6M6 3h12a2 2 0 0 1 2 2v14H4V5a2 2 0 0 1 2-2Z"/>
                    </svg>
                    Ir al POS
                </Link>

                <template v-if="isManager">
                    <Link
                        :href="route('reports.dailyCut')"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        <svg class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 7H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3m-1-4H8m0 0a2 2 0 1 0 4 0M8 3a2 2 0 0 1 4 0"/>
                        </svg>
                        Corte del día
                    </Link>
                    <Link
                        :href="route('reports.dailyCut')"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        <svg class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19V5M8 17v-6M12 17V7M16 17v-4M20 17v-8"/>
                        </svg>
                        Reportes
                    </Link>
                </template>
            </div>
        </div>

        <!-- ── Stats cards ─────────────────────────────────────────────── -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="card in displayCards"
                :key="card.key"
                class="flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1"
                :class="card.ring"
            >
                <!-- Icono -->
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" :class="card.color">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="card.icon" />
                    </svg>
                </div>

                <!-- Datos -->
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-500">{{ card.label }}</p>
                    <p class="mt-0.5 text-2xl font-bold text-gray-900 tabular-nums">{{ card.value }}</p>
                </div>
            </div>
        </div>

        <!-- ── Actividad reciente ─────────────────────────────────────── -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            <!-- Últimas ventas -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Últimas ventas</h2>
                    <Link :href="route('sales.index')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                        Ver todas →
                    </Link>
                </div>

                <div v-if="recentSales.length" class="divide-y divide-gray-50">
                    <div
                        v-for="sale in recentSales"
                        :key="sale.id"
                        class="flex items-center gap-3 px-5 py-3.5"
                    >
                        <!-- Folio badge -->
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-600">
                            #{{ sale.folio }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">{{ sale.customer }}</p>
                            <p class="text-xs text-gray-400">{{ sale.date }}&nbsp;&nbsp;{{ sale.time }}</p>
                        </div>

                        <span class="shrink-0 text-sm font-semibold text-gray-900 tabular-nums">
                            {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(sale.total) }}
                        </span>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center gap-2 px-5 py-10 text-gray-400">
                    <svg class="h-10 w-10 opacity-30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/>
                    </svg>
                    <p class="text-sm">Sin ventas recientes</p>
                </div>
            </div>

            <!-- Últimos apartados -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Apartados activos</h2>
                    <Link :href="route('layaways.index')" class="text-xs font-medium text-amber-600 hover:text-amber-500">
                        Ver todos →
                    </Link>
                </div>

                <div v-if="recentLayaways.length" class="divide-y divide-gray-50">
                    <div
                        v-for="lay in recentLayaways"
                        :key="lay.id"
                        class="flex items-center gap-3 px-5 py-3.5"
                    >
                        <!-- Folio badge -->
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-xs font-bold text-amber-600">
                            #{{ lay.folio }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">{{ lay.customer }}</p>
                            <p class="text-xs text-gray-400">
                                Abonado:
                                {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(lay.paid) }}
                                de
                                {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(lay.subtotal) }}
                            </p>
                        </div>

                        <!-- Saldo pendiente -->
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-red-600 tabular-nums">
                                {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(lay.balance) }}
                            </p>
                            <p class="text-xs text-gray-400">pendiente</p>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center gap-2 px-5 py-10 text-gray-400">
                    <svg class="h-10 w-10 opacity-30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2m14 0V9a2 2 0 0 0-2-2M5 11V9a2 2 0 0 1 2-2m0 0V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2M7 7h10"/>
                    </svg>
                    <p class="text-sm">Sin apartados activos</p>
                </div>
            </div>

        </div><!-- /grid actividad -->

    </div>
</template>
