<script setup>
import ProductCard from '@/Components/Products/ProductCard.vue';
import LabelsTable from '@/Components/Products/LabelsTable.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ status: 'disponible', q: '', gender: '', category_id: 0 }),
    },
    products:   { type: Object, required: true },
    categories: { type: Array,  default: () => [] },
    occasions: { type: Array, default: () => [] },
    labelTable: { type: Object, default: () => ({ open: false, q: '', recent_days: '' }) },
    can: {
        type: Object,
        default: () => ({ create: false, update: false, viewPurchasePrice: false }),
    },
});

const q            = ref(props.filters.q          || '');
const gender       = ref(props.filters.gender     || '');
const categoryId   = ref(props.filters.category_id ? String(props.filters.category_id) : '');
const activeStatus = ref(props.filters.status     || 'disponible');
const selectedIds = ref([]);
const selectedOccasions = ref([]);
const labelsOpen = ref(!!props.labelTable.open);

function closeLabels() {
    labelsOpen.value = false;
    const url = new URL(window.location.href);
    if (url.searchParams.has('labels')) {
        url.searchParams.delete('labels');
        url.searchParams.delete('labels_q');
        url.searchParams.delete('labels_recent_days');
        window.history.replaceState(window.history.state, '', url);
    }
}

function assignOccasions() {
    if (!selectedIds.value.length) return;
    router.post(route('catalogs.occasions.assign'), {
        product_ids: selectedIds.value,
        occasion_ids: selectedOccasions.value,
    }, { preserveScroll: true, onSuccess: () => { selectedIds.value = []; } });
}

function applyFilters(patch = {}) {
    router.get(
        route('products.index'),
        {
            status:      patch.status      ?? activeStatus.value,
            q:           patch.q           ?? q.value,
            gender:      patch.gender      ?? gender.value,
            category_id: patch.category_id ?? categoryId.value,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

watch([gender, categoryId], () => applyFilters());

const statusTabs = [
    { key: 'disponible', label: 'Disponible' },
    { key: 'apartado',   label: 'Apartado'   },
    { key: 'vendido',    label: 'Vendido'    },
];
</script>

<template>
    <Head title="Inventario" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 app-heading">
        <div class="app-heading">
            <h1 class="text-xl font-black text-gray-900">Productos</h1>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ products.meta?.total ?? products.data?.length ?? 0 }} registros
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <Link
            v-if="can.create"
            :href="route('catalog-imports.index')"
            class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-100"
            >Importar Excel</Link>
            <Link v-if="can.update" :href="route('products.bulk')" class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-100">Fotos en tabla</Link>
            <button type="button" class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-100" @click="labelsOpen ? closeLabels() : labelsOpen = true">{{ labelsOpen ? 'Cerrar etiquetas' : 'Etiquetas en tabla' }}</button>
            <Link
            v-if="can.create"
            :href="route('products.create')"
            class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nuevo producto
            </Link>
        </div>
    </div>

    <LabelsTable v-if="labelsOpen" :initial-query="labelTable.q" :initial-recent-days="labelTable.recent_days" class="mb-5" @close="closeLabels" />

    <div v-if="!labelsOpen" class="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-sm space-y-3 app-surface">
        <div class="flex gap-1 rounded-xl bg-gray-100 p-1 w-fit">
            <button
                v-for="tab in statusTabs"
                :key="tab.key"
                type="button"
                class="rounded-lg px-4 py-2 text-sm font-semibold transition-all"
                :class="activeStatus === tab.key
                    ? 'bg-white text-gray-900 shadow'
                    : 'text-gray-500 hover:text-gray-700'"
                @click="applyFilters({ status: tab.key }); activeStatus = tab.key"
            >{{ tab.label }}</button>
        </div>

        <div class="flex flex-wrap gap-2">
            <form class="flex min-w-[200px] flex-1 gap-2" @submit.prevent="applyFilters({ q: q })">
                <input
                    v-model="q"
                    type="text"
                    placeholder="Buscar por SKU o nombre..."
                    class="w-full rounded-lg border-gray-200 py-2 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400"
                />
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    Buscar
                </button>
            </form>

            <div class="flex flex-wrap gap-1 items-center">
                <button
                    v-for="g in [{ v: '', label: 'Todos' }, { v: 'dama', label: 'Dama' }, { v: 'caballero', label: 'Caballero' }, { v: 'unisex', label: 'Unisex' }]"
                    :key="g.v"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="gender === g.v ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    @click="gender = g.v"
                >{{ g.label }}</button>
            </div>

            <select
                v-if="categories.length"
                v-model="categoryId"
                class="rounded-lg border-gray-200 py-2 pl-3 pr-8 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400"
            >
                <option value="">Todas las categorias</option>
                <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
            </select>
        </div>
    </div>

    <div v-if="!labelsOpen && products.data?.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <div v-for="p in products.data" :key="p.id" class="relative">
          <label v-if="can.update" class="absolute left-2 top-2 z-10 rounded-lg bg-white/95 px-2 py-1 text-xs font-semibold shadow">
            <input v-model="selectedIds" type="checkbox" :value="p.id" class="mr-1 rounded border-stone-300">Seleccionar
          </label>
          <ProductCard :product="p" :can-update="can.update" :can-view-purchase="can.viewPurchasePrice" />
        </div>
    </div>

    <section v-if="!labelsOpen && can.update && selectedIds.length" class="mt-4 rounded-xl border border-stone-200 bg-white p-4 app-surface">
      <Link :href="route('products.bulk', { ids: selectedIds })" class="mb-3 inline-block rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Agregar fotos a seleccionados</Link>
      <h2 class="text-sm font-semibold">Asignar ocasiones a {{ selectedIds.length }} productos seleccionados</h2>
      <p class="mt-1 text-xs text-stone-500">Se reemplazarán las ocasiones actuales de estos productos.</p>
      <div class="mt-3 flex flex-wrap gap-3">
        <label v-for="occasion in occasions" :key="occasion.id" class="text-sm"><input v-model="selectedOccasions" type="checkbox" :value="occasion.id" class="mr-1 rounded border-stone-300">{{ occasion.name }}</label>
      </div>
      <button type="button" class="mt-3 rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white" @click="assignOccasions">Aplicar a seleccionados</button>
    </section>

    <div v-if="!labelsOpen && !products.data?.length" class="flex flex-col items-center justify-center gap-3 rounded-xl bg-white py-20 text-center shadow-sm ring-1 ring-gray-100 app-surface">
        <svg class="h-16 w-16 text-gray-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0l-.77.78-.77-.78a5.4 5.4 0 0 0-7.65 7.65l1.06 1.06L12 21.23l7.77-7.79 1.06-1.06a5.4 5.4 0 0 0-.41-7.8z"/>
        </svg>
        <p class="text-sm font-bold text-gray-500">Sin productos en este filtro</p>
        <p class="text-xs text-gray-400">Prueba con otro estado, genero o categoria</p>
    </div>

    <div v-if="!labelsOpen && products.meta?.links?.length" class="mt-6 flex flex-wrap gap-2">
        <template v-for="link in products.meta.links" :key="link.label">
            <span
                v-if="!link.url"
                class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-300"
                v-html="link.label"
            />
            <button
                v-else
                type="button"
                class="rounded-lg border px-3 py-2 text-sm"
                :class="link.active
                    ? 'border-gray-900 bg-gray-900 text-white'
                    : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                v-html="link.label"
                @click="router.visit(link.url, { preserveScroll: true, preserveState: true })"
            />
        </template>
    </div>
</template>
