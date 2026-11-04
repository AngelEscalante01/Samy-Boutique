<script setup>
import { Head, Link } from '@inertiajs/vue3'
import ProductEditor from '@/Components/Products/ProductEditor.vue'
import ProductLabelControls from '@/Components/Products/ProductLabelControls.vue'
defineProps({
  product: { type: Object, required: true }, photoVariants: { type: Array, default: () => [] }, barcodes: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] }, sizes: { type: Array, default: () => [] }, colors: { type: Array, default: () => [] }, occasions: { type: Array, default: () => [] }, can: { type: Object, default: () => ({}) },
})
</script>
<template>
  <Head title="Editar producto" />
  <div class="app-page">
    <header class="app-heading flex flex-wrap items-center justify-between gap-3 border border-stone-200 bg-white">
      <div><h1 class="text-xl font-semibold text-stone-900">Editar producto</h1><p class="mt-1 text-sm text-stone-500">{{ product.sku }} · {{ product.name }}</p></div>
      <Link :href="route('products.show', product.id)" class="rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold">Ver producto</Link>
    </header>
    <ProductEditor :key="`${product.id}:${product.updated_at}`" :product="product" :categories="categories" :sizes="sizes" :colors="colors" :occasions="occasions" :can="can" :photo-variants="photoVariants" :barcodes="barcodes" />
    <ProductLabelControls :key="`labels:${product.id}:${product.updated_at}`" :product="product" />
  </div>
</template>
