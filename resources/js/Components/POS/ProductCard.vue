<script setup>
defineProps({
    product: { type: Object, required: true },
    inCart:  { type: Boolean, default: false },
});

defineEmits(['add']);

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
</script>

<template>
    <div
        class="group relative flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100 transition-shadow hover:shadow-md"
        :class="inCart ? 'ring-gray-900 ring-2' : ''"
    >
        <!-- Badge "En carrito" -->
        <div v-if="inCart" class="absolute left-2 top-2 z-10 rounded-full bg-gray-900 px-2 py-0.5 text-xs font-bold text-white shadow">
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

            <!-- Chips talla + color -->
            <div class="flex flex-wrap gap-1">
                <span v-if="product.size?.name" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                    {{ product.size.name }}
                </span>
                <span v-if="product.color?.name" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                    {{ product.color.name }}
                </span>
                <span v-if="product.category?.name" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-600">
                    {{ product.category.name }}
                </span>
            </div>

            <!-- Price + Agregar -->
            <div class="mt-auto flex items-center justify-between gap-2">
                <span class="text-base font-bold text-gray-900">{{ money(product.sale_price) }}</span>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="inCart
                        ? 'cursor-default bg-gray-100 text-gray-400'
                        : 'bg-gray-900 text-white hover:bg-gray-700'"
                    :disabled="inCart"
                    @click="!inCart && $emit('add')"
                >
                    {{ inCart ? 'Agregado' : 'Agregar' }}
                </button>
            </div>
        </div>
    </div>
</template>
