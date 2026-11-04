<script setup>
import { Head, Link } from '@inertiajs/vue3'
import BarcodePreview from '@/Components/Products/BarcodePreview.vue'
defineProps({ product: { type: Object, required: true }, barcodes: { type: Array, default: () => [] }, can: { type: Object, default: () => ({}) } })
const money = value => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value ?? 0))
</script>

<template>
  <Head :title="product.name" />
  <div class="app-page">
    <header class="app-heading flex flex-wrap items-center justify-between gap-3 border border-stone-200 bg-white">
      <div><h1 class="text-xl font-semibold text-stone-900">{{ product.name }}</h1><p class="text-sm text-stone-500">{{ product.sku }} · {{ product.category?.name }}</p></div>
      <div class="flex flex-wrap gap-2">
        <Link :href="route('products.index')" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold">Volver</Link>
        <Link v-if="can.update" :href="route('products.edit', product.id)" class="rounded-lg bg-stone-900 px-3 py-2 text-sm font-semibold text-white">Editar producto</Link>
      </div>
    </header>
    <BarcodePreview :codes="barcodes" />
    <section class="app-surface border border-stone-200 bg-white p-3" aria-label="Información del producto">
      <div v-if="product.images?.length" class="mb-3 flex gap-3 overflow-x-auto">
        <img v-for="image in product.images" :key="image.id" :src="image.optimized_url || image.url || image.image_url" :alt="product.name" class="h-48 w-36 shrink-0 rounded-lg object-cover" />
      </div>
      <p class="text-lg font-bold text-stone-900">{{ money(product.sale_price_base ?? product.sale_price) }}</p>
      <p class="mt-2 text-sm text-stone-600">{{ product.description || 'Sin descripción' }}</p>
      <p class="mt-2 text-xs text-stone-500">Estado: {{ product.status }} · {{ product.gender }}</p>
      <p v-if="can.viewPurchasePrice" class="mt-2 text-xs text-stone-500">Compra: {{ money(product.purchase_price) }}</p>
    </section>
    <section v-if="product.variants?.length" class="app-surface border border-stone-200 bg-white p-3">
      <h2 class="mb-3 text-sm font-semibold">Variantes</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm"><thead class="bg-stone-50 text-xs text-stone-500"><tr><th class="p-2">Variante</th><th class="p-2">Código</th><th class="p-2">Existencias</th><th class="p-2">Precio</th><th class="p-2">Estado</th></tr></thead>
          <tbody><tr v-for="variant in product.variants" :key="variant.id" class="border-t border-stone-100"><td class="p-2">{{ variant.size?.name }} / {{ variant.color?.name }}</td><td class="p-2 font-mono text-xs">{{ variant.sku }}</td><td class="p-2">{{ variant.stock }}</td><td class="p-2">{{ money(variant.sale_price_effective) }}</td><td class="p-2">{{ variant.active ? 'Activa' : 'Inactiva' }}</td></tr></tbody>
        </table>
      </div>
    </section>
  </div>
</template>
