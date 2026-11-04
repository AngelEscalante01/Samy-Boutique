<script setup>
import { Head, Link } from '@inertiajs/vue3'
import ProductEditor from '@/Components/Products/ProductEditor.vue'
defineProps({
  categories: { type: Array, default: () => [] }, sizes: { type: Array, default: () => [] }, colors: { type: Array, default: () => [] }, occasions: { type: Array, default: () => [] }, can: { type: Object, default: () => ({}) },
})
</script>
<template>
  <Head title="Nuevo producto" />
  <div class="app-page">
    <header class="app-heading flex flex-wrap items-center justify-between gap-3 border border-stone-200 bg-white">
      <div><h1 class="text-xl font-semibold text-stone-900">Nuevo producto</h1><p class="mt-1 text-sm text-stone-500">Datos generales, variantes separadas y fotos del catálogo.</p></div>
      <Link :href="route('catalog-imports.index')" class="rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold">Importar desde Excel</Link>
    </header>
    <ProductEditor :categories="categories" :sizes="sizes" :colors="colors" :occasions="occasions" :can="can" />
  </div>
</template>
