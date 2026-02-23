<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    product:          { type: Object,  required: true },
    canUpdate:        { type: Boolean, default: false },
    canViewPurchase:  { type: Boolean, default: false },
});

// --- Carrusel ---
const currentIndex = ref(0);

const images = computed(() => props.product?.images ?? []);
const hasImages = computed(() => images.value.length > 0);
const hasMultiple = computed(() => images.value.length > 1);

function imgUrl(img) {
    return img.image_url ?? img.url ?? (img.path ? `/storage/${img.path}` : null);
}

function setPlaceholder(event) {
    event.target.onerror = null;
    event.target.src = '/images/product-placeholder.svg';
}
function prev() {
    currentIndex.value = (currentIndex.value - 1 + images.value.length) % images.value.length;
}
function next() {
    currentIndex.value = (currentIndex.value + 1) % images.value.length;
}

function money(n) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n ?? 0);
}

const statusMap = {
    disponible: { label: 'Disponible', cls: 'bg-emerald-100 text-emerald-700' },
    apartado:   { label: 'Apartado',   cls: 'bg-amber-100  text-amber-700'   },
    vendido:    { label: 'Vendido',    cls: 'bg-gray-100   text-gray-500'    },
    cancelado:  { label: 'Cancelado',  cls: 'bg-red-100    text-red-600'     },
};

const genderMap = {
    dama:      { label: 'Dama',      cls: 'bg-pink-50 text-pink-600'    },
    caballero: { label: 'Caballero', cls: 'bg-blue-50 text-blue-600'   },
    unisex:    { label: 'Unisex',    cls: 'bg-purple-50 text-purple-600'},
};

const statusInfo = (s) => statusMap[s] ?? { label: s, cls: 'bg-gray-100 text-gray-500' };
const genderInfo = (g) => genderMap[g] ?? { label: g, cls: 'bg-gray-100 text-gray-500' };

const profit = (p) => {
    const sp = Number(p.sale_price ?? 0);
    const pp = Number(p.purchase_price ?? 0);
    if (!pp) return null;
    return { amount: sp - pp, pct: Math.round(((sp - pp) / pp) * 100) };
};
</script>

<template>
    <div class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100 transition-shadow hover:shadow-md">

        <!-- Carrusel de imagenes -->
        <div class="relative aspect-[4/3] w-full overflow-hidden bg-gray-100">

            <img
                v-if="hasImages"
                :src="imgUrl(images[currentIndex])"
                :alt="`${product.name} ${currentIndex + 1}`"
                @error="setPlaceholder"
                class="h-full w-full object-cover transition-all duration-300"
            />

            <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 bg-gray-50">
                <svg class="h-12 w-12 text-gray-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <span class="text-xs text-gray-300">Sin imagen</span>
            </div>

            <template v-if="hasMultiple">
                <button
                    @click.prevent="prev"
                    class="absolute left-1.5 top-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-full bg-black/40 text-white opacity-0 transition-opacity group-hover:opacity-100 hover:bg-black/60"
                    aria-label="Anterior"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <button
                    @click.prevent="next"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-full bg-black/40 text-white opacity-0 transition-opacity group-hover:opacity-100 hover:bg-black/60"
                    aria-label="Siguiente"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                    <button
                        v-for="(_, i) in images"
                        :key="i"
                        @click.prevent="currentIndex = i"
                        class="h-1.5 rounded-full transition-all duration-200"
                        :class="i === currentIndex ? 'w-4 bg-white' : 'w-1.5 bg-white/50'"
                        :aria-label="`Foto ${i + 1}`"
                    />
                </div>

                <span class="absolute bottom-2 right-2 rounded-full bg-black/40 px-1.5 py-0.5 text-[10px] text-white tabular-nums">
                    {{ currentIndex + 1 }}/{{ images.length }}
                </span>
            </template>

            <span
                class="absolute right-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="statusInfo(product.status).cls"
            >{{ statusInfo(product.status).label }}</span>
        </div>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <div>
                <p class="text-xs text-gray-400">{{ product.sku }}</p>
                <p class="truncate text-sm font-bold leading-tight text-gray-900">{{ product.name }}</p>
            </div>

            <div class="flex flex-wrap gap-1">
                <span v-if="product.category?.name" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">{{ product.category.name }}</span>
                <span v-if="product.size?.name"     class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">T: {{ product.size.name }}</span>
                <span v-if="product.color?.name"    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ product.color.name }}</span>
                <span
                    v-if="product.gender"
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="genderInfo(product.gender).cls"
                >{{ genderInfo(product.gender).label }}</span>
            </div>

            <div class="mt-auto pt-2">
                <p class="text-xl font-black text-gray-900 tabular-nums">{{ money(product.sale_price) }}</p>

                <template v-if="canViewPurchase && product.purchase_price">
                    <p class="mt-0.5 text-xs text-gray-400">
                        Compra: <span class="font-medium text-gray-600">{{ money(product.purchase_price) }}</span>
                    </p>
                    <p v-if="profit(product)" class="text-xs font-semibold" :class="profit(product).amount >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        Ganancia: {{ money(profit(product).amount) }} ({{ profit(product).pct }}%)
                    </p>
                </template>
            </div>

            <div v-if="canUpdate" class="mt-2 border-t border-gray-50 pt-2">
                <Link
                    :href="route('products.edit', product.id)"
                    class="flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Editar
                </Link>
            </div>
        </div>
    </div>
</template>