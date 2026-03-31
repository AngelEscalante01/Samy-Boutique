<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SaleStatusBadge from '@/Components/Sales/SaleStatusBadge.vue';

const props = defineProps({
    sales: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    quickStats: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const q = ref(props.filters.q ?? '');
const movementType = ref(props.filters.movement_type ?? '');
const status = ref(props.filters.status ?? '');
const paymentMethod = ref(props.filters.payment_method ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const movementTypeOptions = [
    { value: '', label: 'Todos' },
    { value: 'venta', label: 'Ventas' },
    { value: 'abono', label: 'Abonos' },
];

const statusOptions = [
    { value: '', label: 'Todas' },
    { value: 'completed', label: 'Pagadas' },
    { value: 'cancelled', label: 'Canceladas' },
    { value: 'applied', label: 'Aplicados' },
];

const paymentOptions = [
    { value: '', label: 'Todos' },
    { value: 'cash', label: 'Efectivo' },
    { value: 'card', label: 'Tarjeta' },
    { value: 'transfer', label: 'Transferencia' },
    { value: 'other', label: 'Otro' },
    { value: 'mixed', label: 'Mixto' },
];

function apply() {
    router.get(
        route('sales.index'),
        {
            q: q.value || undefined,
            movement_type: movementType.value || undefined,
            status: status.value || undefined,
            payment_method: paymentMethod.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    q.value = '';
    movementType.value = '';
    status.value = '';
    paymentMethod.value = '';
    from.value = '';
    to.value = '';

    router.get(route('sales.index'), {}, { preserveState: false, replace: true });
}

let searchTimer = null;
function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(apply, 350);
}

const hasFilters = computed(() =>
    !!q.value || !!movementType.value || !!status.value || !!paymentMethod.value || !!from.value || !!to.value,
);

const paginationMeta = computed(() => props.sales?.meta ?? {});

const pagination = computed(() => {
    const links = props.sales?.links ?? [];
    if (links.length < 3) {
        return { prev: null, pages: [], next: null };
    }

    return {
        prev: links[0],
        pages: links.slice(1, -1),
        next: links[links.length - 1],
    };
});

function pageLabel(label) {
    return String(label ?? '').replace(/<[^>]*>/g, '').trim();
}

function money(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function fmtDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function folio(id) {
    return String(id ?? '').padStart(6, '0');
}

function customerName(movement) {
    return movement?.customer?.name ?? 'Publico general';
}

function methodBadge(method) {
    if (!method) {
        return {
            label: 'Sin pago',
            className: 'bg-slate-100 text-slate-600 ring-slate-200',
        };
    }

    if (method === 'mixed') {
        return {
            label: 'Mixto',
            className: 'bg-violet-100 text-violet-700 ring-violet-200',
        };
    }

    if (method === 'cash') {
        return {
            label: 'Efectivo',
            className: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        };
    }

    if (method === 'card') {
        return {
            label: 'Tarjeta',
            className: 'bg-sky-100 text-sky-700 ring-sky-200',
        };
    }

    if (method === 'transfer') {
        return {
            label: 'Transferencia',
            className: 'bg-indigo-100 text-indigo-700 ring-indigo-200',
        };
    }

    return {
        label: 'Otro',
        className: 'bg-slate-100 text-slate-600 ring-slate-200',
    };
}

function movementTypeBadge(type) {
    if (type === 'abono') {
        return {
            label: 'Abono',
            className: 'bg-orange-100 text-orange-700 ring-orange-200',
        };
    }

    return {
        label: 'Venta',
        className: 'bg-cyan-100 text-cyan-700 ring-cyan-200',
    };
}

const kpis = computed(() => [
    {
        key: 'sales_today',
        title: 'Ventas hoy',
        value: Number(props.quickStats?.today_count ?? 0).toLocaleString('es-MX'),
        icon: 'M3 6.75h18M3 12h18M3 17.25h12',
        accent: 'text-cyan-700 bg-cyan-50 ring-cyan-100',
    },
    {
        key: 'total_today',
        title: 'Total vendido hoy',
        value: money(props.quickStats?.today_total ?? 0),
        icon: 'M12 6v12m4-8H8m8 4H8',
        accent: 'text-emerald-700 bg-emerald-50 ring-emerald-100',
    },
    {
        key: 'cancelled_today',
        title: 'Canceladas hoy',
        value: Number(props.quickStats?.today_cancelled ?? 0).toLocaleString('es-MX'),
        icon: 'M6 18L18 6M6 6l12 12',
        accent: 'text-rose-700 bg-rose-50 ring-rose-100',
    },
    {
        key: 'avg_ticket_today',
        title: 'Ticket promedio hoy',
        value: money(props.quickStats?.today_avg_ticket ?? 0),
        icon: 'M4.5 12h15m-12 4.5h9m-6-9h3',
        accent: 'text-violet-700 bg-violet-50 ring-violet-100',
    },
    {
        key: 'abonos_today',
        title: 'Abonos hoy',
        value: Number(props.quickStats?.today_abonos_count ?? 0).toLocaleString('es-MX'),
        icon: 'M12 6v12m-4-8h8',
        accent: 'text-orange-700 bg-orange-50 ring-orange-100',
    },
    {
        key: 'abonos_total_today',
        title: 'Total abonado hoy',
        value: money(props.quickStats?.today_abonos_total ?? 0),
        icon: 'M3 12h18m-3-3 3 3-3 3',
        accent: 'text-amber-700 bg-amber-50 ring-amber-100',
    },
]);
</script>

<template>
    <Head title="Historial de movimientos" />

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">
        <section class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Historial de movimientos</h1>
                <p class="mt-0.5 text-sm text-slate-500">Consulta ventas y abonos desde una sola vista</p>
            </div>

            <Link
                :href="route('pos.index')"
                class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
            >
                Ir al POS
            </Link>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <article
                v-for="kpi in kpis"
                :key="kpi.key"
                class="rounded-xl border border-slate-200 bg-white px-3.5 py-3 shadow-[0_8px_24px_-20px_rgba(15,23,42,0.95)]"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ kpi.title }}</p>
                        <p class="mt-1.5 truncate text-2xl font-bold tracking-tight text-slate-900">{{ kpi.value }}</p>
                    </div>

                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1" :class="kpi.accent">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path :d="kpi.icon" />
                        </svg>
                    </span>
                </div>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white px-3.5 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:px-4">
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-12">
                <div class="relative xl:col-span-3">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Buscar</label>
                    <svg class="pointer-events-none absolute left-3 top-[31px] h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input
                        v-model="q"
                        type="text"
                        placeholder="Folio, cliente o telefono"
                        class="h-9 w-full rounded-lg border border-slate-200 py-1.5 pl-8 pr-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
                        @input="onSearch"
                    >
                </div>

                <div class="xl:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tipo</label>
                    <select
                        v-model="movementType"
                        class="h-9 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
                        @change="apply"
                    >
                        <option v-for="option in movementTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div class="xl:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Estado</label>
                    <select
                        v-model="status"
                        class="h-9 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
                        @change="apply"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div class="xl:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Metodo pago</label>
                    <select
                        v-model="paymentMethod"
                        class="h-9 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
                        @change="apply"
                    >
                        <option v-for="option in paymentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div class="xl:col-span-1">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Desde</label>
                    <input
                        v-model="from"
                        type="date"
                        class="h-9 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
                        @change="apply"
                    >
                </div>

                <div class="xl:col-span-1">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Hasta</label>
                    <input
                        v-model="to"
                        type="date"
                        class="h-9 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
                        @change="apply"
                    >
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 xl:col-span-1 xl:self-end"
                    @click="apply"
                >
                    Aplicar
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 xl:col-span-1 xl:self-end"
                    :disabled="!hasFilters"
                    @click="clearFilters"
                >
                    Limpiar filtros
                </button>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 hidden md:table">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th class="py-2.5 pl-4 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Folio</th>
                            <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                            <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha / hora</th>
                            <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</th>
                            <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                            <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Metodo de pago</th>
                            <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                            <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Referencia</th>
                            <th class="py-2.5 pl-2 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="sales.data.length === 0">
                            <td colspan="9" class="px-5 py-10 text-center text-sm text-slate-400">No hay movimientos con estos filtros.</td>
                        </tr>

                        <tr v-for="movement in sales.data" :key="movement.id" class="transition hover:bg-slate-50/80">
                            <td class="py-2.5 pl-4 pr-2">
                                <span class="font-mono text-sm font-bold text-slate-900">{{ movement.folio }}</span>
                            </td>
                            <td class="px-2 py-2.5 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                                    :class="movementTypeBadge(movement.movement_type).className"
                                >
                                    {{ movementTypeBadge(movement.movement_type).label }}
                                </span>
                            </td>
                            <td class="px-2 py-2.5 text-sm text-slate-600 whitespace-nowrap">{{ fmtDate(movement.created_at) }}</td>
                            <td class="px-2 py-2.5 text-sm font-medium text-slate-800">{{ customerName(movement) }}</td>
                            <td class="px-2 py-2.5 text-right">
                                <span class="text-sm font-bold text-cyan-700">{{ money(movement.total) }}</span>
                            </td>
                            <td class="px-2 py-2.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                                    :class="methodBadge(movement.payment_method).className"
                                >
                                    {{ methodBadge(movement.payment_method).label }}
                                </span>
                            </td>
                            <td class="px-2 py-2.5 text-center">
                                <SaleStatusBadge :status="movement.status" size="sm" />
                            </td>
                            <td class="px-2 py-2.5 text-xs text-slate-500">
                                {{ movement.reference || '—' }}
                            </td>
                            <td class="py-2.5 pl-2 pr-4">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="movement.detail_url"
                                        class="inline-flex items-center rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Ver detalle
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                                        title="Proximamente mas acciones"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 5h.01M12 12h.01M12 19h.01" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-slate-100 md:hidden">
                <li v-if="sales.data.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
                    No hay movimientos con estos filtros.
                </li>

                <li v-for="movement in sales.data" :key="movement.id" class="space-y-2.5 px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-mono text-sm font-bold text-slate-900">{{ movement.folio }}</p>
                            <p class="text-xs text-slate-500">{{ fmtDate(movement.created_at) }}</p>
                        </div>
                        <SaleStatusBadge :status="movement.status" size="sm" />
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-800">{{ customerName(movement) }}</p>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                            :class="movementTypeBadge(movement.movement_type).className"
                        >
                            {{ movementTypeBadge(movement.movement_type).label }}
                        </span>
                    </div>

                    <div>
                        <span
                            class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                            :class="methodBadge(movement.payment_method).className"
                        >
                            {{ methodBadge(movement.payment_method).label }}
                        </span>
                        <p class="mt-1 text-xs text-slate-500">{{ movement.reference || '—' }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Total</p>
                        <p class="text-base font-bold text-cyan-700">{{ money(movement.total) }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <Link
                            :href="movement.detail_url"
                            class="inline-flex items-center rounded-md border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Ver detalle
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                            title="Proximamente mas acciones"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5h.01M12 12h.01M12 19h.01" />
                            </svg>
                        </button>
                    </div>
                </li>
            </ul>

            <div
                v-if="(paginationMeta.last_page ?? 1) > 1"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5"
            >
                <p class="text-xs text-slate-500">
                    Mostrando {{ paginationMeta.from ?? 0 }} a {{ paginationMeta.to ?? 0 }} de {{ paginationMeta.total ?? 0 }} movimientos
                </p>

                <nav class="flex items-center gap-1" aria-label="Paginacion">
                    <Link
                        :href="pagination.prev?.url ?? '#'"
                        class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
                        :class="[
                            !pagination.prev?.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                    >
                        Anterior
                    </Link>

                    <Link
                        v-for="link in pagination.pages"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-2 text-xs font-semibold transition"
                        :class="[
                            link.active
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                    >
                        {{ pageLabel(link.label) }}
                    </Link>

                    <Link
                        :href="pagination.next?.url ?? '#'"
                        class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
                        :class="[
                            !pagination.next?.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                    >
                        Siguiente
                    </Link>
                </nav>
            </div>
        </section>
    </div>
</template>
