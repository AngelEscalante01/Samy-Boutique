<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  products: { type: Object, required: true },
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

const showFilters = ref(
  !!(props.filters.gender || props.filters.category_id || props.filters.size_id || props.filters.color_id)
)

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
  searchTimer = setTimeout(() => applyFilters(), 380)
})

watch([gender, categoryId, sizeId, colorId, sort], () => applyFilters())

function clearFilters() {
  q.value = ''
  gender.value = ''
  categoryId.value = ''
  sizeId.value = ''
  colorId.value = ''
  sort.value = 'newest'
  showFilters.value = false
  applyFilters()
}

const activeFilterCount = computed(
  () =>
    [gender.value, categoryId.value, sizeId.value, colorId.value].filter(Boolean).length,
)

function money(v) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0)
}

function imageUrl(product) {
  const img = product?.images?.[0]
  return img?.url ?? (img?.path ? `/storage/${img.path}` : null)
}

const hasProducts = computed(() => (props.products?.data?.length ?? 0) > 0)

const whatsappFloatLink = computed(() => {
  const number = (props.whatsappNumber || '').replace(/\D+/g, '')
  if (!number) return null
  const text = '¡Hola! Me interesa ver el catálogo de Samy Boutique.'
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
  <Head title="Samy Boutique — Catálogo">
    <meta name="description" content="Descubre la colección disponible de Samy Boutique. Moda con estilo y elegancia." />
    <meta name="robots" content="noindex,nofollow" />
  </Head>

  <div class="min-h-screen bg-stone-50">

    <!-- ── HERO ─────────────────────────────────────────────── -->
    <header class="border-b border-stone-200 bg-white py-16 text-center">
      <p class="mb-3 text-xs font-semibold uppercase tracking-[0.25em] text-stone-400">
        Boutique de moda
      </p>
      <h1 class="text-5xl font-semibold tracking-wide text-zinc-900 sm:text-6xl">
        Samy Boutique
      </h1>
      <p class="mt-4 text-base font-light tracking-widest text-stone-500 sm:text-lg">
        Colección disponible
      </p>
      <p class="mt-2 text-xs tracking-wide text-stone-400">
        Estilo que habla por ti
      </p>
    </header>

    <!-- ── CONTENIDO ──────────────────────────────────────────── -->
    <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <div
        v-if="isOffline"
        class="mb-6 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-center text-xs font-semibold tracking-wide text-stone-500"
      >
        Offline: mostrando última versión guardada
      </div>

      <!-- Barra de búsqueda & filtros -->
      <div class="mb-10">
        <!-- Fila principal -->
        <div class="flex items-center gap-3">
          <!-- Search -->
          <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0Z" />
            </svg>
            <input
              v-model="q"
              type="text"
              placeholder="Buscar por nombre o SKU…"
              class="w-full rounded-full border border-stone-200 bg-white py-3 pl-11 pr-4 text-sm text-zinc-900 shadow-sm placeholder:text-stone-400 focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
            />
          </div>

          <!-- Toggle filtros -->
          <button
            type="button"
            class="relative flex shrink-0 items-center gap-2 rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-900 shadow-sm transition duration-200 hover:border-zinc-900"
            @click="showFilters = !showFilters"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
            </svg>
            <span class="hidden sm:inline">Filtros</span>
            <span
              v-if="activeFilterCount > 0"
              class="flex h-5 w-5 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-bold text-white"
            >{{ activeFilterCount }}</span>
          </button>
        </div>

        <!-- Panel de filtros avanzados -->
        <div v-if="showFilters" class="mt-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-stone-400">Ordenar</label>
              <select
                v-model="sort"
                class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm text-zinc-900 focus:border-zinc-900 focus:outline-none"
              >
                <option value="newest">Más nuevos</option>
                <option value="price_asc">Precio: menor a mayor</option>
                <option value="price_desc">Precio: mayor a menor</option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-stone-400">Género</label>
              <select
                v-model="gender"
                class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm text-zinc-900 focus:border-zinc-900 focus:outline-none"
              >
                <option value="">Todos</option>
                <option value="dama">Dama</option>
                <option value="caballero">Caballero</option>
                <option value="unisex">Unisex</option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-stone-400">Categoría</label>
              <select
                v-model="categoryId"
                class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm text-zinc-900 focus:border-zinc-900 focus:outline-none"
              >
                <option value="">Todas</option>
                <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-stone-400">Talla</label>
              <select
                v-model="sizeId"
                class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm text-zinc-900 focus:border-zinc-900 focus:outline-none"
              >
                <option value="">Todas</option>
                <option v-for="s in sizes" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-stone-400">Color</label>
              <select
                v-model="colorId"
                class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm text-zinc-900 focus:border-zinc-900 focus:outline-none"
              >
                <option value="">Todos</option>
                <option v-for="c in colors" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
            </div>
          </div>

          <button
            v-if="activeFilterCount > 0 || q"
            type="button"
            class="mt-4 text-xs font-semibold text-stone-500 underline underline-offset-2 hover:text-zinc-900"
            @click="clearFilters"
          >
            Limpiar filtros
          </button>
        </div>
      </div>

      <!-- Grid de productos -->
      <section v-if="hasProducts" class="grid grid-cols-2 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 lg:gap-8">
        <article
          v-for="product in products.data"
          :key="product.id"
          class="group relative overflow-hidden rounded-2xl bg-white shadow-sm transition duration-300 hover:shadow-md"
        >
          <!-- Imagen -->
          <Link :href="route('public.catalog.show', product.sku)" class="block">
            <div class="relative aspect-[3/4] overflow-hidden bg-stone-100">
              <img
                v-if="imageUrl(product)"
                :src="imageUrl(product)"
                :alt="product.name"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
              />
              <div v-else class="flex h-full items-center justify-center">
                <svg class="h-14 w-14 text-stone-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-6.75-4.5h.008v.008H14.25V7.5Z" />
                </svg>
              </div>
              <!-- Badge disponible -->
              <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-700 shadow-sm">
                Disponible
              </span>
            </div>
          </Link>

          <!-- Info -->
          <div class="p-4">
            <h3 class="line-clamp-2 text-sm font-semibold tracking-wide text-zinc-900">
              {{ product.name }}
            </h3>

            <div class="mt-2 flex flex-wrap gap-1.5">
              <span v-if="product.size" class="rounded-full border border-stone-200 px-2.5 py-0.5 text-xs text-stone-500">
                {{ product.size.name }}
              </span>
              <span v-if="product.color" class="rounded-full border border-stone-200 px-2.5 py-0.5 text-xs text-stone-500">
                {{ product.color.name }}
              </span>
            </div>

            <p class="mt-3 text-xl font-semibold tracking-wide text-zinc-900">
              {{ money(product.sale_price) }}
            </p>

            <Link
              :href="route('public.catalog.show', product.sku)"
              class="mt-3 block rounded-xl border border-zinc-900 py-2.5 text-center text-sm font-semibold tracking-wide text-zinc-900 transition duration-200 hover:bg-zinc-900 hover:text-white"
            >
              Ver detalle
            </Link>
          </div>
        </article>
      </section>

      <!-- Estado vacío -->
      <section v-else class="flex flex-col items-center justify-center py-24 text-center">
        <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-stone-100">
          <svg class="h-9 w-9 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold tracking-wide text-zinc-900">
          No hay productos disponibles
        </h3>
        <p class="mt-2 text-sm text-stone-500">
          en este momento
        </p>
        <button
          v-if="q || activeFilterCount > 0"
          type="button"
          class="mt-6 rounded-xl bg-zinc-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800"
          @click="clearFilters"
        >
          Limpiar filtros
        </button>
      </section>

      <!-- Paginación -->
      <nav v-if="(products.links?.length ?? 0) > 3" class="mt-12 flex flex-wrap items-center justify-center gap-2">
        <template v-for="link in products.links" :key="link.label">
          <span
            v-if="!link.url"
            class="flex h-10 min-w-[2.5rem] items-center justify-center rounded-full border border-stone-200 bg-white px-3 text-sm text-stone-300"
            v-html="link.label"
          />
          <button
            v-else
            type="button"
            class="flex h-10 min-w-[2.5rem] items-center justify-center rounded-full border px-3 text-sm font-semibold transition duration-150"
            :class="link.active
              ? 'border-zinc-900 bg-zinc-900 text-white'
              : 'border-stone-200 bg-white text-stone-700 hover:border-zinc-900 hover:text-zinc-900'"
            v-html="link.label"
            @click="router.visit(link.url, { preserveScroll: true, preserveState: true })"
          />
        </template>
      </nav>
    </main>

    <!-- ── FOOTER ─────────────────────────────────────────────── -->
    <footer class="mt-16 border-t border-stone-200 bg-white py-8 text-center">
      <p class="text-xs tracking-widest text-stone-400 uppercase">Samy Boutique · {{ new Date().getFullYear() }}</p>
    </footer>

    <!-- ── WHATSAPP FLOTANTE ───────────────────────────────────── -->
    <a
      v-if="whatsappFloatLink"
      :href="whatsappFloatLink"
      target="_blank"
      rel="noopener noreferrer"
      class="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-green-500 shadow-lg transition duration-200 hover:bg-green-600 hover:shadow-xl"
      title="Escríbenos por WhatsApp"
    >
      <svg class="h-7 w-7 text-white" fill="currentColor" viewBox="0 0 24 24">
        <path d="M20.52 3.48A11.82 11.82 0 0 0 12.1 0C5.54 0 .2 5.34.2 11.9c0 2.1.55 4.16 1.6 5.97L0 24l6.32-1.75a11.86 11.86 0 0 0 5.68 1.45h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.17-3.49-8.32Zm-8.42 18.2h-.01a9.9 9.9 0 0 1-5.05-1.39l-.36-.21-3.75 1.04 1-3.65-.23-.38a9.92 9.92 0 0 1-1.52-5.2c0-5.47 4.45-9.92 9.92-9.92 2.65 0 5.14 1.03 7.01 2.9a9.86 9.86 0 0 1 2.9 7.01c0 5.47-4.45 9.92-9.91 9.92Zm5.44-7.4c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.47-.89-.79-1.5-1.76-1.67-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.48-.5-.67-.51l-.57-.01c-.2 0-.52.07-.8.37-.27.3-1.04 1.01-1.04 2.45 0 1.44 1.06 2.84 1.2 3.03.15.2 2.07 3.16 5.02 4.43.7.3 1.25.48 1.67.61.7.22 1.33.19 1.83.12.56-.08 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35Z"/>
      </svg>
    </a>
  </div>
</template>
