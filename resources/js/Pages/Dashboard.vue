<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

const props = defineProps({
    snapshot: {
        type: Object,
        default: () => ({}),
    },
    endpoints: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

const summary = ref(props.snapshot?.summary ?? {
    total: 0,
    count: 0,
    average_ticket: 0,
    layaways_active_count: 0,
    layaways_pending_total: 0,
    products_available: 0,
});

const chartData = ref(props.snapshot?.chart ?? {
    mode: '7d',
    labels: [],
    series: [],
    max: 0,
});

const recentSales = ref(props.snapshot?.recentSales ?? []);
const recentLayaways = ref(props.snapshot?.recentLayaways ?? []);
const paymentSummary = ref(props.snapshot?.paymentSummary ?? {
    methods: [],
    total: 0,
});

const loading = ref({
    summary: false,
    chart: false,
    recentSales: false,
    recentLayaways: false,
    paymentSummary: false,
});

const chartMode = ref(chartData.value?.mode === 'hourly' ? 'hourly' : '7d');
const chartCanvas = ref(null);
let salesChart = null;

const permissionSet = computed(() => {
    const authPermissions = page.props.auth?.user?.permissions;
    if (Array.isArray(authPermissions)) {
        return new Set(authPermissions);
    }

    const legacyPermissions = page.props.permissions;
    if (legacyPermissions && typeof legacyPermissions === 'object') {
        return new Set(
            Object.entries(legacyPermissions)
                .filter(([, value]) => value === true)
                .map(([key]) => key),
        );
    }

    return new Set();
});

function can(permission) {
    if (!permission) return true;
    if (permissionSet.value.size === 0) return true;
    return permissionSet.value.has(permission);
}

function money(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function compactMoney(value) {
    return new Intl.NumberFormat('es-MX', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(Number(value ?? 0));
}

function number(value) {
    return new Intl.NumberFormat('es-MX').format(Number(value ?? 0));
}

function capitalize(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
}

const userName = computed(() => {
    const name = String(page.props.auth?.user?.name ?? '').trim();
    return name.split(' ')[0] || 'equipo';
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return `Buen dia, ${userName.value}`;
    if (hour < 19) return `Buena tarde, ${userName.value}`;
    return `Buena noche, ${userName.value}`;
});

const currentDateLabel = computed(() => {
    const formatted = new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    return capitalize(formatted);
});

const headerActions = computed(() => {
    const actions = [
        {
            key: 'go-pos',
            label: 'Ir al POS',
            href: route('pos.index'),
            permission: 'pos.view',
            style: 'primary',
        },
        {
            key: 'new-layaway',
            label: 'Nuevo apartado',
            href: route('layaways.create'),
            permission: 'pos.view',
            style: 'secondary',
        },
        {
            key: 'daily-cut',
            label: 'Corte del dia',
            href: route('reports.dailyCut'),
            permission: 'reports.view',
            style: 'secondary',
        },
    ];

    return actions.filter((action) => can(action.permission));
});

const kpiCards = computed(() => [
    {
        key: 'sales-total',
        label: 'Ventas del dia',
        value: money(summary.value.total),
        caption: `Ticket promedio ${money(summary.value.average_ticket)}`,
        accent: 'text-cyan-700',
    },
    {
        key: 'sales-count',
        label: 'Numero de ventas',
        value: number(summary.value.count),
        caption: 'Operaciones completadas hoy',
        accent: 'text-slate-600',
    },
    {
        key: 'layaways-active',
        label: 'Apartados activos',
        value: number(summary.value.layaways_active_count),
        caption: 'Apartados abiertos en sistema',
        accent: 'text-violet-700',
    },
    {
        key: 'layaways-balance',
        label: 'Saldo pendiente apartados',
        value: money(summary.value.layaways_pending_total),
        caption: 'Monto pendiente de liquidar',
        accent: 'text-rose-700',
    },
    {
        key: 'products-available',
        label: 'Productos disponibles',
        value: number(summary.value.products_available),
        caption: 'Piezas listas para venta',
        accent: 'text-emerald-700',
    },
]);

const chartTitle = computed(() => (
    chartMode.value === 'hourly'
        ? 'Ventas por hora del dia actual'
        : 'Ventas de los ultimos 7 dias'
));

const chartSubtitle = computed(() => (
    chartMode.value === 'hourly'
        ? 'Vista operativa para ritmo de caja'
        : 'Tendencia semanal de ingresos'
));

const chartValues = computed(() => (chartData.value?.series ?? []).map((point) => Number(point.total ?? 0)));

const quickActions = computed(() => [
    {
        key: 'quick-sale',
        title: 'Nueva venta',
        subtitle: 'Abrir POS',
        href: route('pos.index'),
        permission: 'pos.view',
        color: 'from-cyan-500/20 to-sky-400/5 border-cyan-100',
    },
    {
        key: 'quick-layaway',
        title: 'Registrar apartado',
        subtitle: 'Crear nuevo apartado',
        href: route('layaways.create'),
        permission: 'pos.view',
        color: 'from-amber-500/20 to-yellow-300/5 border-amber-100',
    },
    {
        key: 'quick-sales-history',
        title: 'Ver historial de ventas',
        subtitle: 'Consulta detallada',
        href: route('sales.index'),
        permission: 'sales.view',
        color: 'from-indigo-500/20 to-blue-300/5 border-indigo-100',
    },
    {
        key: 'quick-products',
        title: 'Ver productos',
        subtitle: 'Inventario actual',
        href: route('products.index'),
        permission: 'products.view',
        color: 'from-emerald-500/20 to-green-300/5 border-emerald-100',
    },
    {
        key: 'quick-customers',
        title: 'Ver clientes',
        subtitle: 'Gestion de clientes',
        href: route('customers.index'),
        permission: 'customers.view',
        color: 'from-violet-500/20 to-purple-300/5 border-violet-100',
    },
    {
        key: 'quick-cut',
        title: 'Corte diario',
        subtitle: 'Resumen de caja',
        href: route('reports.dailyCut'),
        permission: 'reports.view',
        color: 'from-rose-500/20 to-pink-300/5 border-rose-100',
    },
].filter((action) => can(action.permission)));

const paymentMax = computed(() => {
    if (!paymentSummary.value?.methods?.length) return 1;
    return Math.max(...paymentSummary.value.methods.map((item) => Number(item.total ?? 0)), 1);
});

function endpoint(name, fallbackRoute) {
    return props.endpoints?.[name] ?? route(fallbackRoute);
}

async function fetchSummary() {
    loading.value.summary = true;
    try {
        const { data } = await axios.get(endpoint('summary', 'dashboard.data.summary'));
        summary.value = data;
    } finally {
        loading.value.summary = false;
    }
}

async function fetchChart(mode = chartMode.value) {
    loading.value.chart = true;
    try {
        const { data } = await axios.get(endpoint('chart', 'dashboard.data.chart'), {
            params: { mode },
        });
        chartData.value = data;
    } finally {
        loading.value.chart = false;
    }
}

async function fetchRecentSales() {
    loading.value.recentSales = true;
    try {
        const { data } = await axios.get(endpoint('recentSales', 'dashboard.data.recent-sales'));
        recentSales.value = data;
    } finally {
        loading.value.recentSales = false;
    }
}

async function fetchRecentLayaways() {
    loading.value.recentLayaways = true;
    try {
        const { data } = await axios.get(endpoint('recentLayaways', 'dashboard.data.recent-layaways'));
        recentLayaways.value = data;
    } finally {
        loading.value.recentLayaways = false;
    }
}

async function fetchPaymentSummary() {
    loading.value.paymentSummary = true;
    try {
        const { data } = await axios.get(endpoint('paymentSummary', 'dashboard.data.payment-summary'));
        paymentSummary.value = data;
    } finally {
        loading.value.paymentSummary = false;
    }
}

function destroyChart() {
    if (salesChart) {
        salesChart.destroy();
        salesChart = null;
    }
}

function renderChart() {
    if (!chartCanvas.value) return;

    const context = chartCanvas.value.getContext('2d');
    if (!context) return;

    destroyChart();

    salesChart = new Chart(context, {
        type: 'line',
        data: {
            labels: chartData.value?.labels ?? [],
            datasets: [
                {
                    label: chartTitle.value,
                    data: chartValues.value,
                    borderColor: '#0e7490',
                    backgroundColor: 'rgba(14, 116, 144, 0.16)',
                    fill: true,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    tension: 0.35,
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(contextPoint) {
                            return ` ${money(contextPoint.parsed.y)}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback(value) {
                            return compactMoney(value);
                        },
                    },
                },
            },
        },
    });
}

watch(
    () => chartMode.value,
    async (mode) => {
        await fetchChart(mode);
    },
);

watch(
    () => chartData.value?.series,
    async () => {
        await nextTick();
        renderChart();
    },
    { deep: true },
);

onMounted(async () => {
    await nextTick();
    renderChart();

    await Promise.allSettled([
        fetchSummary(),
        fetchRecentSales(),
        fetchRecentLayaways(),
        fetchPaymentSummary(),
        fetchChart(chartMode.value),
    ]);
});

onBeforeUnmount(() => {
    destroyChart();
});
</script>

<template>
    <Head title="Dashboard POS" />

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="relative overflow-hidden rounded-3xl border border-cyan-100 bg-gradient-to-r from-cyan-600 via-sky-600 to-indigo-600 p-6 text-white shadow-[0_20px_45px_-25px_rgba(14,116,144,0.95)] sm:p-8">
            <div class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white/15 blur-2xl" />
            <div class="pointer-events-none absolute -right-12 bottom-0 h-44 w-44 rounded-full bg-amber-300/20 blur-2xl" />

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-cyan-100">Boutique POS</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{ greeting }}</h1>
                    <p class="mt-2 text-sm text-cyan-100">{{ currentDateLabel }}</p>
                    <p class="mt-5 text-xs uppercase tracking-[0.2em] text-cyan-100">Ventas acumuladas del dia</p>
                    <p class="mt-1 text-4xl font-black tracking-tight sm:text-5xl">{{ money(summary.total) }}</p>
                </div>

                <div class="flex flex-wrap gap-2 sm:gap-3 lg:max-w-sm lg:justify-end">
                    <Link
                        v-for="action in headerActions"
                        :key="action.key"
                        :href="action.href"
                        class="inline-flex items-center rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
                        :class="action.style === 'primary'
                            ? 'border-white/40 bg-white text-cyan-700 hover:bg-cyan-50'
                            : 'border-white/40 bg-white/10 text-white hover:bg-white/20'"
                    >
                        {{ action.label }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="card in kpiCards"
                :key="card.key"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_8px_30px_-22px_rgba(15,23,42,0.95)]"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ card.value }}</p>
                <p class="mt-2 text-xs font-medium" :class="card.accent">{{ card.caption }}</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ chartTitle }}</h2>
                    <p class="text-sm text-slate-500">{{ chartSubtitle }}</p>
                </div>

                <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                        :class="chartMode === '7d' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="chartMode = '7d'"
                    >
                        7 dias
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                        :class="chartMode === 'hourly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="chartMode = 'hourly'"
                    >
                        Hoy por hora
                    </button>
                </div>
            </div>

            <div class="mt-4 h-72 rounded-xl bg-slate-50/80 p-3 sm:h-80">
                <canvas ref="chartCanvas" />
            </div>

            <p v-if="loading.chart" class="mt-2 text-xs text-slate-400">Actualizando grafica...</p>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Ultimas ventas</h2>
                    <Link :href="route('sales.index')" class="text-sm font-semibold text-cyan-700 hover:text-cyan-600">Ver historial</Link>
                </div>

                <div v-if="recentSales.length" class="space-y-2">
                    <div
                        v-for="sale in recentSales"
                        :key="sale.id"
                        class="grid grid-cols-[74px_1fr_auto] items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5"
                    >
                        <div>
                            <p class="text-xs font-bold text-slate-600">{{ sale.time }}</p>
                            <p class="text-[11px] text-slate-400">#{{ sale.folio }}</p>
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ sale.customer }}</p>
                            <p class="truncate text-xs text-slate-500">{{ sale.payment_method }}</p>
                            <p v-if="sale.has_discount" class="text-[11px] font-semibold text-rose-600">Descuento: {{ money(sale.discount) }}</p>
                        </div>

                        <p class="text-sm font-bold text-slate-900">{{ money(sale.total) }}</p>
                    </div>
                </div>

                <div v-else class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-400">
                    No hay ventas recientes para mostrar.
                </div>

                <p v-if="loading.recentSales" class="mt-2 text-xs text-slate-400">Actualizando ventas...</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Apartados recientes</h2>
                    <Link :href="route('layaways.index')" class="text-sm font-semibold text-cyan-700 hover:text-cyan-600">Ver apartados</Link>
                </div>

                <div v-if="recentLayaways.length" class="space-y-2">
                    <div
                        v-for="layaway in recentLayaways"
                        :key="layaway.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">#{{ layaway.folio }} · {{ layaway.customer }}</p>
                            <p class="text-xs text-slate-400">{{ layaway.date }}</p>
                        </div>
                        <div class="mt-1 grid grid-cols-3 gap-2 text-xs">
                            <p class="text-slate-500">Total <span class="font-semibold text-slate-800">{{ money(layaway.total) }}</span></p>
                            <p class="text-slate-500">Abonado <span class="font-semibold text-emerald-700">{{ money(layaway.paid) }}</span></p>
                            <p class="text-slate-500">Pendiente <span class="font-semibold text-rose-700">{{ money(layaway.balance) }}</span></p>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-400">
                    No hay apartados abiertos por el momento.
                </div>

                <p v-if="loading.recentLayaways" class="mt-2 text-xs text-slate-400">Actualizando apartados...</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="action in quickActions"
                :key="action.key"
                :href="action.href"
                class="group relative overflow-hidden rounded-2xl border bg-gradient-to-br p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] transition-transform hover:-translate-y-0.5"
                :class="action.color"
            >
                <div class="absolute -right-5 -top-6 h-16 w-16 rounded-full bg-white/35 blur-xl transition group-hover:scale-110" />
                <p class="relative text-base font-semibold text-slate-900">{{ action.title }}</p>
                <p class="relative mt-1 text-sm text-slate-600">{{ action.subtitle }}</p>
            </Link>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Resumen por metodo de pago</h2>
                    <p class="text-sm text-slate-500">Distribucion del cobro de hoy</p>
                </div>
                <p class="text-sm font-semibold text-slate-700">Total: {{ money(paymentSummary.total) }}</p>
            </div>

            <div v-if="paymentSummary.methods?.length" class="space-y-2.5">
                <article
                    v-for="method in paymentSummary.methods"
                    :key="method.method"
                    class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ method.label }}</p>
                        <div class="text-right">
                            <p class="text-sm font-bold text-slate-900">{{ money(method.total) }}</p>
                            <p class="text-[11px] text-slate-500">{{ number(method.sales_count) }} ventas</p>
                        </div>
                    </div>

                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-indigo-500"
                            :style="{ width: `${Math.max(8, (Number(method.total) / paymentMax) * 100)}%` }"
                        />
                    </div>
                </article>
            </div>

            <div v-else class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-400">
                No hay cobros registrados hoy para desglosar por metodo.
            </div>

            <p v-if="loading.paymentSummary" class="mt-2 text-xs text-slate-400">Actualizando resumen de pagos...</p>
        </section>
    </div>
</template>
