<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CategoryScale,
    Chart,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip);

const props = defineProps({
    snapshot: { type: Object, default: () => ({}) },
    endpoints: { type: Object, default: () => ({}) },
});

const page = usePage();
const summary = ref(props.snapshot?.summary ?? {});
const weeklySales = ref(props.snapshot?.chart ?? { labels: [], series: [] });
const recentSales = ref(props.snapshot?.recentSales ?? []);
const recentLayaways = ref(props.snapshot?.recentLayaways ?? []);
const refreshing = ref(false);
const refreshError = ref(false);
const chartCanvas = ref(null);
let chart = null;

const permissions = computed(() => {
    const userPermissions = page.props.auth?.user?.permissions;
    if (Array.isArray(userPermissions)) return new Set(userPermissions);
    const legacy = page.props.permissions;
    if (legacy && typeof legacy === 'object') {
        return new Set(Object.entries(legacy).filter(([, value]) => value === true).map(([key]) => key));
    }
    return new Set();
});

function can(permission) {
    return permissions.value.size === 0 || permissions.value.has(permission);
}

function money(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value ?? 0));
}

function number(value) {
    return new Intl.NumberFormat('es-MX').format(Number(value ?? 0));
}

function shortDate(value) {
    if (!value) return '';
    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    if (value === summary.value.date) return 'Hoy';
    return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short' }).format(date);
}

const dateLabel = computed(() => {
    const date = summary.value.date ? new Date(`${summary.value.date}T12:00:00`) : new Date();
    const formatted = new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
});

const metrics = computed(() => [
    {
        key: 'sales',
        label: 'Ventas de hoy',
        value: money(summary.value.total),
        note: `${number(summary.value.count)} ${Number(summary.value.count) === 1 ? 'venta realizada' : 'ventas realizadas'}`,
    },
    {
        key: 'layaways',
        label: 'Apartados activos',
        value: number(summary.value.layaways_active_count),
        note: `${money(summary.value.layaways_pending_total)} pendiente por cobrar`,
    },
    {
        key: 'products',
        label: 'Productos disponibles',
        value: number(summary.value.products_available),
        note: 'Registrados como disponibles',
    },
]);

const latestSales = computed(() => recentSales.value.slice(0, 3));
const latestLayaways = computed(() => recentLayaways.value.slice(0, 4));
const chartValues = computed(() => (weeklySales.value?.series ?? []).map((point) => Number(point.total ?? 0)));
const hasWeeklySales = computed(() => chartValues.value.some((value) => value > 0));

function endpoint(name, fallback) {
    return props.endpoints?.[name] ?? route(fallback);
}

async function refreshDashboard() {
    if (refreshing.value) return;
    refreshing.value = true;
    refreshError.value = false;

    const requests = await Promise.allSettled([
        axios.get(endpoint('summary', 'dashboard.data.summary')),
        axios.get(endpoint('chart', 'dashboard.data.chart'), { params: { mode: '7d' } }),
        axios.get(endpoint('recentSales', 'dashboard.data.recent-sales')),
        axios.get(endpoint('recentLayaways', 'dashboard.data.recent-layaways')),
    ]);

    if (requests[0].status === 'fulfilled') summary.value = requests[0].value.data;
    if (requests[1].status === 'fulfilled') weeklySales.value = requests[1].value.data;
    if (requests[2].status === 'fulfilled') recentSales.value = requests[2].value.data;
    if (requests[3].status === 'fulfilled') recentLayaways.value = requests[3].value.data;
    refreshError.value = requests.some((result) => result.status === 'rejected');
    refreshing.value = false;
}

function renderChart() {
    chart?.destroy();
    chart = null;
    if (!chartCanvas.value || !hasWeeklySales.value) return;

    const context = chartCanvas.value.getContext('2d');
    if (!context) return;

    chart = new Chart(context, {
        type: 'line',
        data: {
            labels: weeklySales.value?.labels ?? [],
            datasets: [{
                data: chartValues.value,
                borderColor: '#a16207',
                backgroundColor: '#a16207',
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(point) { return money(point.parsed.y); },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#78716c' },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#e7e5e4' },
                    border: { display: false, dash: [4, 4] },
                    ticks: {
                        color: '#78716c',
                        callback(value) {
                            return new Intl.NumberFormat('es-MX', { notation: 'compact' }).format(value);
                        },
                    },
                },
            },
        },
    });
}

watch(weeklySales, async () => {
    await nextTick();
    renderChart();
});

onMounted(async () => {
    await nextTick();
    renderChart();
});

onBeforeUnmount(() => {
    chart?.destroy();
});
</script>

<template>
    <Head title="Resumen de hoy — Samy Boutique" />

    <div class="mx-auto max-w-7xl space-y-6 pb-8 app-card-stack app-page">
        <header class="flex flex-wrap items-start justify-between gap-4 app-heading">
            <div>
                <p class="text-sm font-medium text-stone-500">{{ dateLabel }}</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-zinc-950">Resumen de hoy</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-stone-300 bg-white px-4 text-sm font-medium text-stone-700 transition hover:bg-stone-100 disabled:opacity-50" :disabled="refreshing" :aria-busy="refreshing" @click="refreshDashboard">
                    <svg aria-hidden="true" class="h-4 w-4" :class="{ 'animate-spin': refreshing }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 11a8 8 0 1 1-2.3-5.7" /><path d="M20 4v5h-5" /></svg>
                    {{ refreshing ? 'Actualizando' : 'Actualizar' }}
                </button>
                <Link v-if="can('pos.view')" :href="route('pos.index')" class="inline-flex min-h-11 items-center rounded-xl bg-zinc-900 px-5 text-sm font-semibold text-white transition hover:bg-zinc-700">Nueva venta</Link>
                <Link v-if="can('pos.view')" :href="route('layaways.create')" class="inline-flex min-h-11 items-center rounded-xl border border-zinc-900 bg-white px-5 text-sm font-semibold text-zinc-900 transition hover:bg-stone-100">Nuevo apartado</Link>
            </div>
        </header>

        <p v-if="refreshError" role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">No se pudo actualizar toda la información. Intenta de nuevo.</p>

        <section aria-label="Cifras clave" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 app-card-grid">
            <article v-for="metric in metrics" :key="metric.key" class="rounded-2xl border p-5 sm:p-6 app-surface" :class="metric.key === 'sales' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-stone-200 bg-white text-zinc-900'">
                <h2 class="text-sm font-medium" :class="metric.key === 'sales' ? 'text-stone-300' : 'text-stone-500'">{{ metric.label }}</h2>
                <p class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ metric.value }}</p>
                <p class="mt-2 text-sm" :class="metric.key === 'sales' ? 'text-stone-300' : 'text-stone-600'">{{ metric.note }}</p>
            </article>
        </section>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:items-start app-card-grid">
            <section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6 app-surface" aria-labelledby="recent-sales-title">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="recent-sales-title" class="text-lg font-semibold text-zinc-900">Ventas recientes</h2>
                    <Link v-if="can('sales.view')" :href="route('sales.index')" class="text-sm font-semibold text-amber-800 hover:underline">Ver todas</Link>
                </div>
                <div v-if="latestSales.length" class="divide-y divide-stone-100">
                    <Link v-for="sale in latestSales" :key="sale.id" :href="route('sales.show', sale.id)" class="flex items-center justify-between gap-4 py-3 transition hover:bg-stone-50">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-900">{{ sale.customer }}</p>
                            <p class="mt-0.5 text-xs text-stone-500">{{ shortDate(sale.date) }} · {{ sale.time }} · #{{ sale.folio }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-zinc-900">{{ money(sale.total) }}</span>
                    </Link>
                </div>
                <p v-else class="py-8 text-sm text-stone-500">Aún no hay ventas recientes.</p>
            </section>

            <section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6 app-surface" aria-labelledby="active-layaways-title">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="active-layaways-title" class="text-lg font-semibold text-zinc-900">Últimos apartados</h2>
                    <Link v-if="can('pos.view')" :href="route('layaways.index')" class="text-sm font-semibold text-amber-800 hover:underline">Ver todos</Link>
                </div>
                <div v-if="latestLayaways.length" class="divide-y divide-stone-100">
                    <Link v-for="layaway in latestLayaways" :key="layaway.id" :href="route('layaways.show', layaway.id)" class="flex items-center justify-between gap-4 py-3 transition hover:bg-stone-50">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-900">{{ layaway.customer }}</p>
                            <p class="mt-0.5 text-xs text-stone-500">Apartado #{{ layaway.folio }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-zinc-900">{{ money(layaway.balance) }}</p>
                            <p class="text-xs text-stone-500">pendiente</p>
                        </div>
                    </Link>
                </div>
                <p v-else class="py-8 text-sm text-stone-500">No hay apartados activos.</p>
            </section>
        </div>

        <section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6 app-surface" aria-labelledby="weekly-sales-title">
            <div class="mb-5">
                <h2 id="weekly-sales-title" class="text-lg font-semibold text-zinc-900">Ventas de los últimos 7 días</h2>
                <p class="mt-1 text-sm text-stone-500">Consulta cómo se han movido las ventas esta semana.</p>
            </div>
            <div v-if="hasWeeklySales" class="h-56 sm:h-64">
                <canvas ref="chartCanvas" role="img" aria-label="Gráfica de ventas de los últimos siete días"></canvas>
                <ul class="sr-only">
                    <li v-for="(point, index) in weeklySales.series" :key="point.date ?? index">{{ weeklySales.labels?.[index] }}: {{ money(point.total) }}</li>
                </ul>
            </div>
            <p v-else class="rounded-xl bg-stone-50 px-4 py-8 text-center text-sm text-stone-500">Aún no hay ventas registradas en los últimos 7 días.</p>
        </section>
    </div>
</template>
