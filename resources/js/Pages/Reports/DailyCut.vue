<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    savedCut: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const csrfToken = computed(() => {
    return document
        ?.querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
});

const date = ref(props.filters.date || new Date().toISOString().slice(0, 10));

const totals = ref(props.savedCut?.totals_json || null);
const loading = ref(false);
const previewError = ref('');

function money(n) {
    return Number(n ?? 0).toFixed(2);
}

async function generate() {
    previewError.value = '';
    totals.value = null;
    loading.value = true;

    try {
        const response = await window.fetch(route('reports.dailyCut.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken.value,
                Accept: 'application/json',
            },
            body: JSON.stringify({ date: date.value }),
        });

        const data = await response.json();

        if (!response.ok) {
            const msg = data?.message || 'No se pudo generar el reporte.';
            const firstFieldError = data?.errors?.date?.[0];
            previewError.value = firstFieldError || msg;
            return;
        }

        totals.value = data.totals;
        router.get(route('reports.dailyCut'), { date: date.value }, { replace: true, preserveState: true, preserveScroll: true });
    } catch (e) {
        previewError.value = 'Error de red al generar el reporte.';
    } finally {
        loading.value = false;
    }
}

const saveForm = useForm({
    date: date.value,
});

function save() {
    saveForm.date = date.value;

    saveForm.post(route('reports.dailyCut.save'), {
        preserveScroll: true,
        onSuccess: () => {
            // Se recarga la página con el corte guardado
        },
    });
}

const summary = computed(() => ({
    sales_count: Number(totals.value?.sales_count ?? 0),
    canceled_count: Number(totals.value?.canceled_count ?? totals.value?.cancelled_count ?? 0),
    subtotal_sum: Number(totals.value?.subtotal_sum ?? 0),
    manual_discount_total: Number(totals.value?.manual_discount_total ?? totals.value?.discount_manual_total ?? totals.value?.discount_sum ?? 0),
    coupon_discount_total: Number(totals.value?.coupon_discount_total ?? totals.value?.discount_coupon_total ?? totals.value?.coupon_discount_sum ?? 0),
    loyalty_discount_total: Number(totals.value?.loyalty_discount_total ?? totals.value?.discount_loyalty_total ?? totals.value?.loyalty_discount_sum ?? 0),
    total_sales: Number(totals.value?.total_sales ?? totals.value?.total_sold ?? totals.value?.total_sum ?? 0),
    profit_total: Number(totals.value?.profit_total ?? totals.value?.profit_sum ?? 0),
    top_products: totals.value?.top_products ?? [],
    top_categories: totals.value?.top_categories ?? [],
    cancelled_sales: totals.value?.cancelled_sales ?? [],
}));

const payments = computed(() => totals.value?.payments_by_method || totals.value?.payments || null);
const cancelledSales = computed(() => summary.value.cancelled_sales);
</script>

<template>
    <Head title="Corte diario" />

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">Corte diario</h1>
                <p class="text-sm text-gray-500">Reporte de ventas pagadas por fecha</p>
            </div>

            <Link
                :href="route('dashboard')"
                class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200"
            >
                Dashboard
            </Link>
        </div>

        <div>
            <div v-if="Object.keys(saveForm.errors).length" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3">
                <p class="text-sm font-medium text-red-800">Error al guardar corte</p>
                <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
                    <li v-for="(msg, key) in saveForm.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>

            <div class="rounded-lg bg-white p-4 shadow">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="w-full sm:w-auto">
                        <label class="block text-sm font-medium text-gray-700">Fecha</label>
                        <input
                            v-model="date"
                            type="date"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64"
                        />
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                            :disabled="loading"
                            @click="generate"
                        >
                            {{ loading ? 'Generando…' : 'Generar' }}
                        </button>

                        <button
                            type="button"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="!totals || saveForm.processing"
                            @click="save"
                        >
                            Guardar corte
                        </button>
                    </div>
                </div>

                <p v-if="previewError" class="mt-3 text-sm text-red-700">
                    {{ previewError }}
                </p>

                <div v-if="savedCut" class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            Corte guardado para <span class="font-semibold">{{ savedCut.cut_date }}</span>
                            <span v-if="savedCut.creator" class="text-gray-500">(por {{ savedCut.creator.name }})</span>
                        </div>
                        <div class="text-gray-500">Actualizado: {{ savedCut.updated_at }}</div>
                    </div>
                </div>
            </div>

            <div v-if="totals" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="rounded-lg bg-white p-4 shadow">
                        <h3 class="text-lg font-semibold text-gray-900">Resumen</h3>

                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Número de ventas</span>
                                <span class="font-medium text-gray-900">{{ summary.sales_count }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="font-medium text-gray-900">{{ money(summary.subtotal_sum) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Descuentos</span>
                                <span class="font-medium text-gray-900">-{{ money(summary.manual_discount_total) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Cupones</span>
                                <span class="font-medium text-gray-900">-{{ money(summary.coupon_discount_total) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Fidelidad</span>
                                <span class="font-medium text-gray-900">-{{ money(summary.loyalty_discount_total) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Ventas canceladas</span>
                                <span class="font-medium text-gray-900">{{ summary.canceled_count }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-900">Ganancia</span>
                                <span class="font-semibold text-emerald-700">{{ money(summary.profit_total) }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-2 text-base">
                                <span class="font-semibold text-gray-900">Total ventas</span>
                                <span class="font-semibold text-gray-900">{{ money(summary.total_sales) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg bg-white p-4 shadow">
                        <h3 class="text-lg font-semibold text-gray-900">Top productos (opcional)</h3>

                        <div class="mt-4 overflow-hidden rounded-md border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Ventas</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="p in summary.top_products" :key="p.product_id">
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ p.sku }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ p.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ p.qty }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(p.total) }}</td>
                                    </tr>
                                    <tr v-if="!summary.top_products?.length">
                                        <td class="px-4 py-6 text-center text-sm text-gray-500" colspan="4">Sin datos.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="cancelledSales.length" class="mt-6 rounded-lg bg-white p-4 shadow">
                        <h3 class="text-lg font-semibold text-gray-900">Canceladas</h3>
                        <p class="mt-1 text-sm text-gray-500">Estas ventas no se incluyen en el total cobrado del corte.</p>

                        <div class="mt-4 overflow-hidden rounded-md border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Venta</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Motivo</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="sale in cancelledSales" :key="sale.id">
                                        <td class="px-4 py-3 text-sm text-gray-700">#{{ sale.id }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ sale.cancel_reason || '—' }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(sale.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="rounded-lg bg-white p-4 shadow lg:sticky lg:top-6">
                        <h3 class="text-lg font-semibold text-gray-900">Totales por método</h3>

                        <div v-if="payments" class="mt-4 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Efectivo</span>
                                <span class="font-medium text-gray-900">{{ money(payments.cash) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Tarjeta</span>
                                <span class="font-medium text-gray-900">{{ money(payments.card) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Transferencia</span>
                                <span class="font-medium text-gray-900">{{ money(payments.transfer) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Otro</span>
                                <span class="font-medium text-gray-900">{{ money(payments.other) }}</span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h3 class="text-lg font-semibold text-gray-900">Top categorías (opcional)</h3>
                            <div class="mt-3 space-y-2 text-sm">
                                <div
                                    v-for="c in summary.top_categories"
                                    :key="c.category_id ?? c.name"
                                    class="flex items-center justify-between"
                                >
                                    <span class="text-gray-700">{{ c.name }}</span>
                                    <span class="font-medium text-gray-900">{{ money(c.total) }}</span>
                                </div>
                                <div v-if="!summary.top_categories?.length" class="text-sm text-gray-500">
                                    Sin datos.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
