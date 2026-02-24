<script setup>
import { computed } from 'vue'

const emit = defineEmits(['select'])

const props = defineProps({
    product: { type: Object, required: true },
    inCartVariantIds: { type: Array, default: () => [] },
})

const availableVariants = computed(() =>
    (props.product?.variants ?? []).filter((variant) => Number(variant?.stock ?? 0) > 0),
)

const isAnyVariantInCart = computed(() =>
    availableVariants.value.some((variant) => props.inCartVariantIds.includes(variant.id)),
)

const priceLabel = computed(() => {
    const prices = availableVariants.value
        .map((variant) => Number(variant?.sale_price_effective ?? 0))
        .filter((price) => Number.isFinite(price) && price > 0)

    if (!prices.length) {
        return money(props.product?.sale_price ?? props.product?.sale_price_base ?? 0)
    }

    const min = Math.min(...prices)
    const max = Math.max(...prices)

    if (min === max) {
        return money(min)
    }

    return `Desde ${money(min)}`
})

function imageUrl(product) {
    const first = product?.images?.[0];
    if (!first) return null;
    return first.image_url ?? first.url ?? (first.path ? `/storage/${first.path}` : null);
}

function setPlaceholder(event) {
    event.target.onerror = null;
    event.target.src = '/images/product-placeholder.svg';
}

function money(v) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0);
}

function selectProduct() {
    if (!availableVariants.value.length) return
    emit('select', props.product)
}
</script>

<template>
    <div
        class="group relative flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100 transition-shadow hover:shadow-md"
        :class="isAnyVariantInCart ? 'ring-gray-900 ring-2' : ''"
    >
        <!-- Badge "En carrito" -->
        <div v-if="isAnyVariantInCart" class="absolute left-2 top-2 z-10 rounded-full bg-gray-900 px-2 py-0.5 text-xs font-bold text-white shadow">
            En carrito
        </div>

        <!-- Image -->
        <div class="aspect-[4/3] w-full overflow-hidden bg-gray-100">
            <img
                v-if="imageUrl(product)"
                :src="imageUrl(product)"
                :alt="product.name"
                @error="setPlaceholder"
                class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
            />
            <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 bg-gray-50">
                <svg class="h-10 w-10 text-gray-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="m3 9 4-4 4 4 4-4 4 4"/><path d="M3 15h18"/>
                </svg>
                <span class="text-xs text-gray-300">Sin imagen</span>
            </div>
        </div>

        <!-- Info -->
        <div class="flex flex-1 flex-col gap-2 p-3">
            <div class="min-w-0">
                <p class="truncate text-xs text-gray-400">{{ product.sku }}</p>
                <p class="truncate text-sm font-semibold leading-tight text-gray-900">{{ product.name }}</p>
            </div>

            <!-- Variantes -->
            <div class="flex flex-wrap gap-1">
                <span v-if="product.category?.name" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-600">
                    {{ product.category.name }}
                </span>
            </div>

            <!-- Price + Seleccionar -->
            <div class="mt-auto flex items-center justify-between gap-2">
                <span class="text-base font-bold text-gray-900">{{ priceLabel }}</span>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="availableVariants.length
                        ? 'bg-gray-900 text-white hover:bg-gray-700'
                        : 'cursor-not-allowed bg-gray-100 text-gray-400'"
                    :disabled="!availableVariants.length"
                    @click="selectProduct"
                >
                    {{ availableVariants.length ? 'Seleccionar' : 'Sin stock' }}
                </button>
            </div>
        </div>
    </div>
</template>
