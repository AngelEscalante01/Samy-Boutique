<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import { onBeforeUnmount, ref } from 'vue'

const props = defineProps({
  products: { type: Array, default: () => [] },
  pagination: { type: Object, default: null },
  filters: { type: Object, default: () => ({ q: '', recent_days: '' }) },
})
const search = ref(props.filters.q ?? '')
const recentDays = ref(String(props.filters.recent_days ?? ''))
const drafts = ref(props.products.map((product) => ({
  id: product.id, sku: product.sku, name: product.name,
  category: product.category?.name ?? 'Sin categoría',
  variants: product.variants ?? [], currentImages: product.images ?? [],
  expectedUpdatedAt: product.updated_at, pendingImages: [], state: 'idle', error: '',
})))
const running = ref(false)

const imageUrl = (image) => image?.thumbnail_url || image?.url || image?.image_url || ''
const variantName = (variant) => `${variant.size?.name ?? 'Sin talla'} / ${variant.color?.name ?? 'Sin color'}`

function addImages(draft, event) {
  const files = Array.from(event.target.files ?? [])
  event.target.value = ''
  draft.error = ''
  if (draft.pendingImages.length + files.length > 10) {
    draft.error = 'Puedes agregar hasta 10 fotos por guardado.'
    return
  }
  if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 4 * 1024 * 1024)) {
    draft.error = 'Usa fotos JPG, PNG o WEBP de hasta 4 MB cada una.'
    return
  }
  draft.pendingImages.push(...files.map((file) => ({ file, url: URL.createObjectURL(file), variantId: '' })))
  draft.state = 'pending'
}

function removeImage(draft, index) {
  URL.revokeObjectURL(draft.pendingImages[index].url)
  draft.pendingImages.splice(index, 1)
  draft.state = draft.pendingImages.length ? 'pending' : 'idle'
  draft.error = ''
}

async function saveOne(draft) {
  if (!draft.pendingImages.length || draft.state === 'saving') return
  draft.state = 'saving'
  draft.error = ''
  const body = new FormData()
  body.append('expected_updated_at', draft.expectedUpdatedAt)
  draft.pendingImages.forEach((photo, index) => {
    body.append(`images[${index}]`, photo.file)
    if (photo.variantId) body.append(`image_variant_ids[${index}]`, photo.variantId)
  })
  try {
    const response = await axios.post(route('products.bulk-images.store', draft.id), body, { headers: { Accept: 'application/json' } })
    draft.expectedUpdatedAt = response.data.data.updated_at
    draft.currentImages = response.data.data.images ?? []
    draft.pendingImages.forEach((photo) => URL.revokeObjectURL(photo.url))
    draft.pendingImages = []
    draft.state = 'done'
  } catch (error) {
    draft.state = 'failed'
    draft.error = Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || error.response?.data?.message || 'No se pudieron guardar las fotos. Intenta de nuevo.'
  }
}

async function saveAll() {
  if (running.value) return
  running.value = true
  for (const draft of drafts.value.filter((row) => row.pendingImages.length)) await saveOne(draft)
  running.value = false
}

function searchProducts() {
  const filters = {}
  if (search.value.trim()) filters.q = search.value.trim()
  if (recentDays.value) filters.recent_days = recentDays.value
  router.get(route('products.bulk'), filters)
}

onBeforeUnmount(() => drafts.value.forEach((draft) => draft.pendingImages.forEach((photo) => URL.revokeObjectURL(photo.url))))
</script>

<template>
  <Head title="Fotos de productos" />
  <div class="mx-auto max-w-[1500px] space-y-5 px-4 py-6 app-card-stack app-page">
    <div class="flex flex-wrap items-start justify-between gap-3 app-heading">
      <div><h1 class="text-2xl font-semibold">Fotos de productos</h1><p class="mt-1 text-sm text-stone-600">Una fila por producto. Agrega fotos y guárdalas aquí; los demás datos se editan en cada producto o con el Excel.</p></div>
      <Link :href="route('products.index')" class="text-sm underline">Volver a productos</Link>
    </div>

    <form v-if="pagination" class="flex flex-wrap gap-2" @submit.prevent="searchProducts">
      <input v-model="search" type="search" placeholder="Buscar por nombre o código" aria-label="Buscar productos" class="min-w-64 flex-1 rounded-lg border-stone-300 text-sm">
      <label class="flex items-center gap-2 text-sm text-stone-700">Agregados <select v-model="recentDays" aria-label="Filtrar por fecha de alta" class="rounded-lg border-stone-300 text-sm" @change="searchProducts"><option value="">Todas las fechas</option><option value="7">Últimos 7 días</option><option value="30">Últimos 30 días</option><option value="90">Últimos 90 días</option></select></label>
      <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Buscar</button>
      <span class="self-center text-sm text-stone-500">{{ pagination.total }} productos · más nuevos primero</span>
    </form>

    <div v-if="drafts.length" class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm app-surface">
      <table class="min-w-[1050px] w-full border-collapse text-left text-sm">
        <thead class="bg-stone-100 text-xs font-semibold uppercase tracking-wide text-stone-600"><tr><th class="sticky left-0 z-10 border-r border-stone-200 bg-stone-100 p-3">Código / producto</th><th class="p-3">Categoría y variantes</th><th class="p-3">Fotos actuales</th><th class="p-3">Agregar fotos</th><th class="p-3">Guardar</th></tr></thead>
        <tbody>
          <tr v-for="draft in drafts" :key="draft.id" class="border-t border-stone-200 align-top">
            <td class="sticky left-0 z-10 w-52 border-r border-stone-200 bg-white p-3"><span class="block font-mono text-xs text-stone-500">{{ draft.sku }}</span><strong class="block break-words">{{ draft.name }}</strong><Link :href="route('products.edit', draft.id)" class="mt-2 inline-block text-xs underline">Ver producto</Link></td>
            <td class="w-44 p-3"><span class="block">{{ draft.category }}</span><span class="mt-1 block text-xs text-stone-500">{{ draft.variants.map(variantName).join(', ') || 'Sin variantes activas' }}</span></td>
            <td class="w-36 p-3"><div class="flex flex-wrap gap-1"><img v-for="image in draft.currentImages.slice(0, 4)" :key="image.id" :src="imageUrl(image)" :alt="`Foto de ${draft.name}`" class="h-12 w-12 rounded border object-cover"><span v-if="draft.currentImages.length > 4" class="self-center text-xs">+{{ draft.currentImages.length - 4 }}</span><span v-if="!draft.currentImages.length" class="text-xs text-stone-500">Sin fotos</span></div></td>
            <td class="min-w-96 p-3">
              <label class="inline-flex cursor-pointer items-center rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold hover:bg-stone-50">Elegir fotos<input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :aria-label="`Elegir fotos para ${draft.name}`" @change="addImages(draft, $event)"></label>
              <span class="ml-2 text-xs text-stone-500">Hasta 10 por guardado · JPG, PNG o WEBP</span>
              <div v-if="draft.pendingImages.length" class="mt-2 space-y-2"><div v-for="(photo, index) in draft.pendingImages" :key="photo.url" class="flex items-center gap-2 rounded border border-stone-200 p-1"><img :src="photo.url" :alt="photo.file.name" class="h-12 w-12 rounded object-cover"><div class="min-w-0 flex-1"><span class="block truncate text-xs" :title="photo.file.name">{{ photo.file.name }}</span><select v-model="photo.variantId" :aria-label="`Variante de ${photo.file.name}`" class="mt-1 w-full rounded border-stone-300 py-1 text-xs"><option value="">Todas las variantes</option><option v-for="variant in draft.variants" :key="variant.id" :value="variant.id">{{ variantName(variant) }}</option></select></div><button type="button" :aria-label="`Quitar ${photo.file.name}`" class="rounded px-2 py-1 text-xs text-red-700 hover:bg-red-50" @click="removeImage(draft, index)">Quitar</button></div></div>
              <p v-if="draft.error" role="alert" class="mt-2 text-xs text-red-700">{{ draft.error }}</p>
            </td>
            <td class="w-36 p-3"><button type="button" :disabled="!draft.pendingImages.length || draft.state === 'saving'" class="rounded-lg bg-stone-900 px-3 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="saveOne(draft)">Guardar fotos</button><span class="mt-2 block text-xs" :class="draft.state === 'done' ? 'text-emerald-700' : draft.state === 'failed' ? 'text-red-700' : 'text-stone-500'">{{ { idle: '', pending: 'Pendiente', saving: 'Guardando...', done: 'Fotos guardadas', failed: 'Error' }[draft.state] }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="rounded-xl border border-stone-200 bg-white p-8 text-center text-sm text-stone-500">No hay productos en esta lista.</p>
    <div v-if="pagination && pagination.lastPage > 1" class="flex items-center justify-between gap-3 text-sm"><Link v-if="pagination.previous" :href="pagination.previous" class="rounded-lg border px-3 py-2">Anterior</Link><span v-else></span><span>Página {{ pagination.currentPage }} de {{ pagination.lastPage }}</span><Link v-if="pagination.next" :href="pagination.next" class="rounded-lg border px-3 py-2">Siguiente</Link><span v-else></span></div>
    <div v-if="drafts.some((draft) => draft.pendingImages.length)" class="sticky bottom-4 rounded-xl bg-stone-900 p-3 shadow-lg app-surface"><button type="button" :disabled="running" class="w-full text-sm font-semibold text-white disabled:opacity-50" @click="saveAll">{{ running ? 'Guardando fotos...' : 'Guardar todas las fotos pendientes' }}</button></div>
  </div>
</template>
