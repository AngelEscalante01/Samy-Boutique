<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  product: { type: Object, required: true },
  whatsappNumber: { type: String, default: '' },
})

const currentImage = ref(0)
const selectedColorId = ref(null)
const selectedSizeId = ref(null)

const images = computed(() => props.product.images ?? [])

const variants = computed(() =>
  (props.product.variants ?? [])
    .filter((variant) => Number(variant?.stock ?? 0) > 0),
)

const colorOptions = computed(() => {
  const map = new Map()

  for (const variant of variants.value) {
    const colorId = Number(variant?.color?.id ?? 0)
    if (!colorId || map.has(colorId)) continue

    map.set(colorId, {
      id: colorId,
      name: variant?.color?.name ?? 'Color',
      hex: variant?.color?.hex ?? null,
      stock: Number(variant?.stock ?? 0),
    })
  }

  return Array.from(map.values())
})

const sizeOptions = computed(() => {
  if (!selectedColorId.value) return []

  const map = new Map()

  for (const variant of variants.value) {
    if (Number(variant?.color?.id ?? 0) !== Number(selectedColorId.value)) continue

    const sizeId = Number(variant?.size?.id ?? 0)
    if (!sizeId || map.has(sizeId)) continue

    map.set(sizeId, {
      id: sizeId,
      name: variant?.size?.name ?? 'Talla',
      stock: Number(variant?.stock ?? 0),
    })
  }

  return Array.from(map.values())
})

const selectedVariant = computed(() => {
  if (!selectedColorId.value || !selectedSizeId.value) return null

  return variants.value.find((variant) =>
    Number(variant?.color?.id ?? 0) === Number(selectedColorId.value)
    && Number(variant?.size?.id ?? 0) === Number(selectedSizeId.value),
  ) ?? null
})

const displayPrice = computed(() => {
  if (selectedVariant.value) return Number(selectedVariant.value.sale_price_effective ?? 0)

  const prices = variants.value
    .map((variant) => Number(variant?.sale_price_effective ?? 0))
    .filter((price) => Number.isFinite(price) && price > 0)

  if (!prices.length) return Number(props.product.sale_price ?? props.product.sale_price_base ?? 0)
  return Math.min(...prices)
})

const stockText = computed(() => {
  const stock = Number(selectedVariant.value?.stock ?? 0)
  if (!stock) return 'Sin stock disponible'
  if (stock <= 3) return `Últimas piezas (${stock})`
  return `Stock disponible: ${stock}`
})

watch(colorOptions, (options) => {
  if (!options.length) {
    selectedColorId.value = null
    return
  }

  if (!options.some((color) => Number(color.id) === Number(selectedColorId.value))) {
    selectedColorId.value = options[0].id
  }
}, { immediate: true })

watch([selectedColorId, sizeOptions], () => {
  if (!sizeOptions.value.length) {
    selectedSizeId.value = null
    return
  }

  if (!sizeOptions.value.some((size) => Number(size.id) === Number(selectedSizeId.value))) {
    selectedSizeId.value = sizeOptions.value[0].id
  }
}, { immediate: true })

function imageUrl(image) {
  return image?.image_url ?? image?.url ?? (image?.path ? `/storage/${image.path}` : null)
}

function setPlaceholder(event) {
  event.target.onerror = null
  event.target.src = '/images/product-placeholder.svg'
}

function money(v) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0)
}

const whatsappLink = computed(() => {
  const number = (props.whatsappNumber || '').replace(/\D+/g, '')
  if (!number) return null

  const variantText = selectedVariant.value
    ? ` Color ${selectedVariant.value?.color?.name ?? 'N/A'}, talla ${selectedVariant.value?.size?.name ?? 'N/A'}.`
    : ''

  const text = `Hola, me interesa: ${props.product.name} (SKU ${props.product.sku}).${variantText} ¿Sigue disponible?`
  return `https://wa.me/${number}?text=${encodeURIComponent(text)}`
})

const isOffline = ref(typeof window !== 'undefined' ? !window.navigator.onLine : false)

function syncConnectivityState() {
  if (typeof window === 'undefined') return
  isOffline.value = !window.navigator.onLine
}

onMounted(() => {
  syncConnectivityState()
  window.addEventListener('online', syncConnectivityState)
  window.addEventListener('offline', syncConnectivityState)
})

onBeforeUnmount(() => {
  window.removeEventListener('online', syncConnectivityState)
  window.removeEventListener('offline', syncConnectivityState)
})
</script>

<template>
  <Head :title="`${product.name} — Samy Boutique`" />

  <div class="min-h-screen bg-stone-50">
    <nav class="border-b border-stone-200 bg-white px-4 py-4 sm:px-6">
      <div class="mx-auto flex max-w-6xl items-center justify-between">
        <Link
          :href="route('public.catalog.index')"
          class="inline-flex items-center gap-2 text-sm font-semibold text-stone-500 transition hover:text-zinc-900"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
          Catálogo
        </Link>
        <span class="text-sm font-semibold tracking-widest text-zinc-900">Samy Boutique</span>
      </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-12">
      <div
        v-if="isOffline"
        class="mb-6 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-center text-xs font-semibold tracking-wide text-stone-500"
      >
        Offline: mostrando última versión guardada
      </div>

      <div class="grid grid-cols-1 gap-0 overflow-hidden rounded-2xl bg-white shadow-sm lg:grid-cols-5">
        <section class="lg:col-span-3">
          <div class="aspect-[3/4] overflow-hidden bg-stone-100">
            <img
              v-if="images.length && imageUrl(images[currentImage])"
              :src="imageUrl(images[currentImage])"
              :alt="product.name"
              @error="setPlaceholder"
              class="h-full w-full object-cover"
            >
            <div v-else class="flex h-full items-center justify-center text-stone-300">Sin imagen</div>
          </div>

          <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto border-t border-stone-100 bg-white p-3">
            <button
              v-for="(img, idx) in images"
              :key="img.id ?? idx"
              type="button"
              class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition"
              :class="idx === currentImage ? 'border-zinc-900 opacity-100' : 'border-transparent opacity-60 hover:opacity-100'"
              @click="currentImage = idx"
            >
              <img :src="imageUrl(img)" :alt="product.name" @error="setPlaceholder" class="h-full w-full object-cover">
            </button>
          </div>
        </section>

        <section class="flex flex-col justify-between border-t border-stone-100 p-7 lg:col-span-2 lg:border-l lg:border-t-0">
          <div>
            <h1 class="text-2xl font-semibold tracking-wide text-zinc-900 sm:text-3xl">{{ product.name }}</h1>
            <p class="mt-1 text-xs text-stone-400">SKU: {{ product.sku }}</p>
            <p class="mt-5 text-3xl font-semibold tracking-wide text-zinc-900">{{ money(displayPrice) }}</p>

            <div class="mt-6 space-y-4 border-t border-stone-100 pt-5">
              <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-400">Color</p>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="color in colorOptions"
                    :key="`color-${color.id}`"
                    type="button"
                    class="rounded-full border px-3 py-1 text-sm font-semibold transition"
                    :class="Number(selectedColorId) === Number(color.id)
                      ? 'border-zinc-900 bg-zinc-900 text-white'
                      : 'border-stone-200 bg-white text-zinc-900 hover:border-zinc-900'"
                    @click="selectedColorId = color.id"
                  >
                    {{ color.name }}
                  </button>
                </div>
              </div>

              <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-400">Talla</p>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="size in sizeOptions"
                    :key="`size-${size.id}`"
                    type="button"
                    class="rounded-full border px-3 py-1 text-sm font-semibold transition"
                    :class="Number(selectedSizeId) === Number(size.id)
                      ? 'border-zinc-900 bg-zinc-900 text-white'
                      : 'border-stone-200 bg-white text-zinc-900 hover:border-zinc-900'"
                    @click="selectedSizeId = size.id"
                  >
                    {{ size.name }}
                  </button>
                </div>
              </div>

              <p class="text-sm font-semibold" :class="Number(selectedVariant?.stock ?? 0) <= 3 ? 'text-amber-700' : 'text-emerald-700'">
                {{ stockText }}
              </p>
            </div>
          </div>

          <div class="mt-8 space-y-3">
            <a
              v-if="whatsappLink"
              :href="whatsappLink"
              target="_blank"
              rel="noopener noreferrer"
              class="flex w-full items-center justify-center gap-3 rounded-2xl bg-zinc-900 py-4 text-sm font-semibold tracking-wide text-white transition hover:bg-zinc-800"
            >
              Contactar por WhatsApp
            </a>

            <Link
              :href="route('public.catalog.index')"
              class="flex w-full items-center justify-center gap-2 rounded-2xl border border-stone-200 bg-white py-4 text-sm font-semibold tracking-wide text-zinc-900 transition hover:border-zinc-900"
            >
              Volver al catálogo
            </Link>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>
