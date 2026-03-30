<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  products: { type: Object, required: true },
  pagination: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
  categories: { type: Array, default: () => [] },
  sizes: { type: Array, default: () => [] },
  colors: { type: Array, default: () => [] },
  whatsappNumber: { type: String, default: '' },
})

const q = ref(props.filters.q ?? '')
const gender = ref(props.filters.gender ?? '')
const categoryId = ref(String(props.filters.category_id ?? '') === '0' ? '' : String(props.filters.category_id ?? ''))
const sizeId = ref(String(props.filters.size_id ?? '') === '0' ? '' : String(props.filters.size_id ?? ''))
const colorId = ref(String(props.filters.color_id ?? '') === '0' ? '' : String(props.filters.color_id ?? ''))
const sort = ref(props.filters.sort ?? 'newest')

let searchTimer = null

function applyFilters() {
  router.get(
    route('public.catalog.index'),
    {
      q: q.value || undefined,
      gender: gender.value || undefined,
      category_id: categoryId.value || undefined,
      size_id: sizeId.value || undefined,
      color_id: colorId.value || undefined,
      sort: sort.value || undefined,
    },
    { preserveScroll: true, preserveState: true, replace: true },
  )
}

watch(q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => applyFilters(), 350)
})

watch([gender, categoryId, sizeId, colorId, sort], () => applyFilters())

function clearFilters() {
  q.value = ''
  gender.value = ''
  categoryId.value = ''
  sizeId.value = ''
  colorId.value = ''
  sort.value = 'newest'
  applyFilters()
}

const hasProducts = computed(() => (props.products?.data?.length ?? 0) > 0)

const paginationSummary = computed(() => {
  const from = Number(props.pagination?.from ?? 0)
  const to = Number(props.pagination?.to ?? 0)
  const total = Number(props.pagination?.total ?? 0)

  if (total <= 0 || from <= 0 || to <= 0) {
    return 'Mostrando 0 productos'
  }

  return `Mostrando ${from}-${to} de ${total} productos`
})

const metaPaginationLinks = computed(() => {
  const links = props.products?.meta?.links
  return Array.isArray(links) ? links : []
})

const previousPageLink = computed(() => {
  if (metaPaginationLinks.value.length > 0) {
    return metaPaginationLinks.value[0] ?? null
  }

  return {
    url: props.products?.links?.prev ?? null,
    label: 'Anterior',
    active: false,
  }
})

const nextPageLink = computed(() => {
  if (metaPaginationLinks.value.length > 0) {
    return metaPaginationLinks.value[metaPaginationLinks.value.length - 1] ?? null
  }

  return {
    url: props.products?.links?.next ?? null,
    label: 'Siguiente',
    active: false,
  }
})

const pageNumberLinks = computed(() => {
  const links = metaPaginationLinks.value

  return links
    .slice(1, -1)
    .map((link) => {
      const label = String(link?.label ?? '').replace(/[^\d]/g, '')

      return {
        ...link,
        page: label ? Number(label) : null,
      }
    })
})

function visitPage(url) {
  if (!url) return

  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
  })
}

const showPagination = computed(() => {
  return (Number(props.pagination?.last_page ?? 1) > 1)
})

function money(v) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0)
}

function imageUrl(product) {
  const img = product?.images?.[0]
  return img?.image_url ?? img?.url ?? (img?.path ? `/storage/${img.path}` : null)
}

function setPlaceholder(event) {
  event.target.onerror = null
  event.target.src = '/images/product-placeholder.svg'
}

function minVariantPrice(product) {
  const prices = (product?.variants ?? [])
    .filter((variant) => Number(variant?.stock ?? 0) > 0)
    .map((variant) => Number(variant?.sale_price_effective ?? 0))
    .filter((price) => Number.isFinite(price) && price > 0)

  if (!prices.length) return Number(product?.sale_price ?? product?.sale_price_base ?? 0)
  return Math.min(...prices)
}

function stockLabel(product) {
  const stock = Number(product?.availability?.total_stock ?? 0)
  if (stock <= 0) return 'Agotado'
  if (stock <= 3) return 'Últimas piezas'
  return `Stock ${stock}`
}

const activeFilterCount = computed(
  () => [gender.value, categoryId.value, sizeId.value, colorId.value].filter(Boolean).length,
)

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
  <Head title="Samy Boutique — Catálogo" />

  <div class="min-h-screen bg-stone-50">
    <header class="border-b border-stone-200 bg-white py-12 text-center">
      <h1 class="text-4xl font-semibold tracking-wide text-zinc-900 sm:text-5xl">Samy Boutique</h1>
      <p class="mt-2 text-sm tracking-widest text-stone-500 uppercase">Colección disponible</p>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div
        v-if="isOffline"
        class="mb-6 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-center text-xs font-semibold tracking-wide text-stone-500"
      >
        Offline: mostrando última versión guardada
      </div>

      <div class="mb-8 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
          <div class="md:col-span-2">
            <input
              v-model="q"
              type="text"
              placeholder="Buscar por nombre o SKU"
              class="w-full rounded-xl border border-stone-200 py-2.5 text-sm focus:border-zinc-900 focus:outline-none"
            >
          </div>

          <select v-model="sort" class="rounded-xl border border-stone-200 py-2.5 text-sm focus:border-zinc-900 focus:outline-none">
            <option value="newest">Más nuevos</option>
            <option value="price_asc">Precio: menor a mayor</option>
            <option value="price_desc">Precio: mayor a menor</option>
          </select>

          <select v-model="gender" class="rounded-xl border border-stone-200 py-2.5 text-sm focus:border-zinc-900 focus:outline-none">
            <option value="">Género</option>
            <option value="dama">Dama</option>
            <option value="caballero">Caballero</option>
            <option value="unisex">Unisex</option>
          </select>

          <select v-model="categoryId" class="rounded-xl border border-stone-200 py-2.5 text-sm focus:border-zinc-900 focus:outline-none">
            <option value="">Categoría</option>
            <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
          </select>

          <select v-model="sizeId" class="rounded-xl border border-stone-200 py-2.5 text-sm focus:border-zinc-900 focus:outline-none">
            <option value="">Talla</option>
            <option v-for="size in sizes" :key="size.id" :value="String(size.id)">{{ size.name }}</option>
          </select>

          <select v-model="colorId" class="rounded-xl border border-stone-200 py-2.5 text-sm focus:border-zinc-900 focus:outline-none">
            <option value="">Color</option>
            <option v-for="color in colors" :key="color.id" :value="String(color.id)">{{ color.name }}</option>
          </select>
        </div>

        <button
          v-if="q || activeFilterCount > 0 || sort !== 'newest'"
          type="button"
          class="mt-3 text-xs font-semibold text-stone-500 underline underline-offset-2 hover:text-zinc-900"
          @click="clearFilters"
        >
          Limpiar filtros
        </button>
      </div>

      <section v-if="hasProducts" class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
        <article
          v-for="product in products.data"
          :key="product.id"
          class="overflow-hidden rounded-2xl bg-white shadow-sm"
        >
          <Link :href="route('public.catalog.show', product.sku)" class="block">
            <div class="aspect-[3/4] overflow-hidden bg-stone-100">
              <img
                v-if="imageUrl(product)"
                :src="imageUrl(product)"
                :alt="product.name"
                class="h-full w-full object-cover transition duration-200 hover:scale-105"
                @error="setPlaceholder"
              >
              <div v-else class="flex h-full items-center justify-center text-stone-300">Sin imagen</div>
            </div>
          </Link>

          <div class="p-4">
            <h2 class="line-clamp-2 text-sm font-semibold text-zinc-900">{{ product.name }}</h2>
            <p class="mt-2 text-lg font-semibold text-zinc-900">Desde {{ money(minVariantPrice(product)) }}</p>

            <div class="mt-2 flex flex-wrap gap-1.5">
              <span
                v-for="color in product.availableColors || []"
                :key="`color-${product.id}-${color.id}`"
                class="rounded-full border border-stone-200 px-2 py-0.5 text-xs text-stone-600"
              >
                {{ color.name }}
              </span>
            </div>

            <div class="mt-2 flex flex-wrap gap-1.5">
              <span
                v-for="size in product.availableSizes || []"
                :key="`size-${product.id}-${size.id}`"
                class="rounded-full border border-stone-200 px-2 py-0.5 text-xs text-stone-600"
              >
                {{ size.name }}
              </span>
            </div>

            <p class="mt-3 text-xs font-semibold" :class="product.availability?.is_low_stock ? 'text-amber-700' : 'text-emerald-700'">
              {{ stockLabel(product) }}
            </p>

            <Link
              :href="route('public.catalog.show', product.sku)"
              class="mt-3 block rounded-xl border border-zinc-900 py-2 text-center text-sm font-semibold text-zinc-900 transition hover:bg-zinc-900 hover:text-white"
            >
              Ver detalle
            </Link>
          </div>
        </article>
      </section>

      <section v-else class="rounded-2xl border border-stone-200 bg-white p-10 text-center">
        <p class="text-lg font-semibold text-zinc-900">No hay productos disponibles</p>
        <p class="mt-2 text-sm text-stone-500">Prueba con otros filtros.</p>
      </section>

      <p class="mt-8 text-center text-sm font-medium text-stone-500">
        {{ paginationSummary }}
      </p>

      <nav v-if="showPagination" class="mt-5">
        <div class="hidden sm:flex items-center justify-center gap-2">
          <button
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-semibold transition"
            :class="previousPageLink?.url
              ? 'border-stone-200 bg-white text-stone-700 hover:border-zinc-900 hover:text-zinc-900'
              : 'border-stone-200 bg-stone-100 text-stone-400 cursor-not-allowed'"
            :disabled="!previousPageLink?.url"
            @click="visitPage(previousPageLink?.url)"
          >
            Anterior
          </button>

          <button
            v-for="(link, index) in pageNumberLinks"
            :key="`page-${index}-${link.label}`"
            type="button"
            class="min-w-10 rounded-full border px-3 py-2 text-sm font-semibold transition"
            :class="link.active
              ? 'border-zinc-900 bg-zinc-900 text-white'
              : link.url
                ? 'border-stone-200 bg-white text-stone-700 hover:border-zinc-900'
                : 'border-stone-200 bg-stone-100 text-stone-400 cursor-not-allowed'"
            :disabled="!link.url"
            @click="visitPage(link.url)"
          >
            {{ link.page ?? '…' }}
          </button>

          <button
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-semibold transition"
            :class="nextPageLink?.url
              ? 'border-stone-200 bg-white text-stone-700 hover:border-zinc-900 hover:text-zinc-900'
              : 'border-stone-200 bg-stone-100 text-stone-400 cursor-not-allowed'"
            :disabled="!nextPageLink?.url"
            @click="visitPage(nextPageLink?.url)"
          >
            Siguiente
          </button>
        </div>

        <div class="sm:hidden mt-4 flex items-center justify-center gap-2">
          <button
            type="button"
            class="rounded-full border px-3 py-2 text-xs font-semibold transition"
            :class="previousPageLink?.url
              ? 'border-stone-200 bg-white text-stone-700'
              : 'border-stone-200 bg-stone-100 text-stone-400 cursor-not-allowed'"
            :disabled="!previousPageLink?.url"
            @click="visitPage(previousPageLink?.url)"
          >
            Anterior
          </button>

          <span class="rounded-full border border-stone-200 bg-white px-3 py-2 text-xs font-semibold text-stone-600">
            {{ pagination?.current_page ?? 1 }} / {{ pagination?.last_page ?? 1 }}
          </span>

          <button
            type="button"
            class="rounded-full border px-3 py-2 text-xs font-semibold transition"
            :class="nextPageLink?.url
              ? 'border-stone-200 bg-white text-stone-700'
              : 'border-stone-200 bg-stone-100 text-stone-400 cursor-not-allowed'"
            :disabled="!nextPageLink?.url"
            @click="visitPage(nextPageLink?.url)"
          >
            Siguiente
          </button>
        </div>
      </nav>
    </main>
  </div>
</template>
