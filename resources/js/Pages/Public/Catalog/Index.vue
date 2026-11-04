<script setup>
import { Link, router } from '@inertiajs/vue3'
import PublicSeoHead from '@/Components/PublicSeoHead.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  products: { type: Object, required: true },
  seo: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  categories: { type: Array, default: () => [] },
  sizes: { type: Array, default: () => [] },
  colors: { type: Array, default: () => [] },
  occasions: { type: Array, default: () => [] },
  slides: { type: Array, default: () => [] },
  whatsappNumber: { type: String, default: '' },
})

const q = ref(props.filters.q ?? '')
const gender = ref(props.filters.gender ?? '')
const categoryId = ref(String(props.filters.category_id ?? '') === '0' ? '' : String(props.filters.category_id ?? ''))
const sizeId = ref(String(props.filters.size_id ?? '') === '0' ? '' : String(props.filters.size_id ?? ''))
const colorId = ref(String(props.filters.color_id ?? '') === '0' ? '' : String(props.filters.color_id ?? ''))
const sort = ref(props.filters.sort ?? 'newest')
const activeSlide = ref(0)
const slideDirection = ref('next')
const mobileFiltersOpen = ref(false)
const mobileSearchOpen = ref(false)
const mobileMenuOpen = ref(false)
const mobileMenuButton = ref(null)
const mobileMenuCloseButton = ref(null)
let touchStartX = null
let carouselTimer = null
const currentSlide = computed(() => props.slides[activeSlide.value] ?? null)
function advanceSlide(step) {
  slideDirection.value = step < 0 ? 'previous' : 'next'
  if (props.slides.length) activeSlide.value = (activeSlide.value + step + props.slides.length) % props.slides.length
}
function goToSlide(index) {
  slideDirection.value = index < activeSlide.value ? 'previous' : 'next'
  activeSlide.value = index
}
function startSwipe(event) {
  touchStartX = event.changedTouches?.[0]?.clientX ?? null
}
function endSwipe(event) {
  if (touchStartX === null) return
  const distance = (event.changedTouches?.[0]?.clientX ?? touchStartX) - touchStartX
  if (Math.abs(distance) > 40) advanceSlide(distance < 0 ? 1 : -1)
  touchStartX = null
}
const occasionIds = ref((props.filters.occasion_ids ?? []).map(String))
const selectedStyle = computed({
  get: () => occasionIds.value[0] ?? '',
  set: (value) => { occasionIds.value = value ? [String(value)] : [] },
})
const departments = [
  { value: '', label: 'Todo' },
  { value: 'dama', label: 'Dama' },
  { value: 'caballero', label: 'Caballero' },
  { value: 'unisex', label: 'Unisex' },
]
const filterGroups = computed(() => [
  { key: 'category', label: 'Categoría', options: props.categories.map((item) => ({ value: String(item.id), label: item.name })) },
  { key: 'size', label: 'Talla', options: props.sizes.map((item) => ({ value: String(item.id), label: item.name })) },
  { key: 'color', label: 'Color', options: props.colors.map((item) => ({ value: String(item.id), label: item.name, hex: item.hex })) },
  { key: 'style', label: 'Estilo', options: props.occasions.map((item) => ({ value: String(item.id), label: item.name })) },
])
const selectedFilterValue = (key) => ({ category: categoryId.value, size: sizeId.value, color: colorId.value, style: selectedStyle.value })[key]
const departmentTitle = computed(() => ({ dama: 'Moda para dama', caballero: 'Moda para caballero', unisex: 'Moda unisex' })[gender.value] ?? 'Toda la colección')
const activeFilters = computed(() => {
  const filters = []
  if (gender.value) filters.push({ key: 'gender', label: departments.find((item) => item.value === gender.value)?.label ?? gender.value })
  if (q.value.trim()) filters.push({ key: 'q', label: `Búsqueda: ${q.value.trim()}` })
  for (const group of filterGroups.value) {
    const value = selectedFilterValue(group.key)
    if (value) filters.push({ key: group.key, label: `${group.label}: ${group.options.find((item) => item.value === value)?.label ?? value}` })
  }
  if (sort.value !== 'newest') filters.push({ key: 'sort', label: `Orden: ${sort.value === 'price_asc' ? 'menor precio' : 'mayor precio'}` })
  return filters
})
const activeFilterCount = computed(() => activeFilters.value.length)
const hasRefinements = computed(() => Boolean(q.value || categoryId.value || sizeId.value || colorId.value || selectedStyle.value || sort.value !== 'newest'))

function chooseFilter(key, value) {
  if (key === 'category') categoryId.value = value
  if (key === 'size') sizeId.value = value
  if (key === 'color') colorId.value = value
  if (key === 'style') selectedStyle.value = value
}

function selectDepartment(value) {
  gender.value = value
  q.value = ''
  categoryId.value = ''
  sizeId.value = ''
  colorId.value = ''
  occasionIds.value = []
  sort.value = 'newest'
  mobileFiltersOpen.value = false
  mobileMenuOpen.value = false
}

function toggleMobileMenu() {
  mobileSearchOpen.value = false
  mobileMenuOpen.value = !mobileMenuOpen.value
}

watch(mobileMenuOpen, async (open) => {
  await nextTick()
  if (open) mobileMenuCloseButton.value?.focus()
  else mobileMenuButton.value?.focus()
})

function removeFilter(key) {
  if (key === 'gender') gender.value = ''
  if (key === 'q') q.value = ''
  if (key === 'category') categoryId.value = ''
  if (key === 'size') sizeId.value = ''
  if (key === 'color') colorId.value = ''
  if (key === 'style') occasionIds.value = []
  if (key === 'sort') sort.value = 'newest'
}

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
      occasion_ids: occasionIds.value.length ? occasionIds.value : undefined,
    },
    { preserveScroll: true, preserveState: true, replace: true },
  )
}

watch(q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => applyFilters(), 350)
})

watch([gender, categoryId, sizeId, colorId, sort, occasionIds], () => applyFilters(), { deep: true })

function clearRefinements() {
  q.value = ''
  categoryId.value = ''
  sizeId.value = ''
  colorId.value = ''
  sort.value = 'newest'
  occasionIds.value = []
}

function clearFilters() {
  clearRefinements()
  gender.value = ''
}

const hasProducts = computed(() => (props.products?.data?.length ?? 0) > 0)

const paginationSummary = computed(() => {
  const from = Number(props.products?.meta?.from ?? 0)
  const to = Number(props.products?.meta?.to ?? 0)
  const total = Number(props.products?.meta?.total ?? 0)

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
  return (Number(props.products?.meta?.last_page ?? 1) > 1)
})

function money(v) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0)
}

function imageUrl(product) {
  const img = product?.images?.[0]
  return img?.thumbnail_url ?? img?.image_url ?? img?.url ?? (img?.path ? `/storage/${img.path}` : null)
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
  return `Stock ${stock}`
}

const currentPage = computed(() => Number(props.products?.meta?.current_page ?? 1))
const lastPage = computed(() => Number(props.products?.meta?.last_page ?? 1))

const isOffline = ref(typeof window !== 'undefined' ? !window.navigator.onLine : false)

function syncConnectivityState() {
  if (typeof window === 'undefined') return
  isOffline.value = !window.navigator.onLine
}

onMounted(() => {
  syncConnectivityState()
  window.addEventListener('online', syncConnectivityState)
  window.addEventListener('offline', syncConnectivityState)
  carouselTimer = window.setInterval(() => advanceSlide(1), 6500)
})

onBeforeUnmount(() => {
  window.clearInterval(carouselTimer)
  clearTimeout(searchTimer)
  window.removeEventListener('online', syncConnectivityState)
  window.removeEventListener('offline', syncConnectivityState)
})
</script>

<template>
  <PublicSeoHead :seo="seo" />

  <div class="min-h-screen bg-stone-50">
    <header class="relative z-30 border-b border-stone-200 bg-white">
      <div class="mx-auto flex h-[4.5rem] max-w-[1500px] items-center gap-2 px-4 sm:px-6 md:h-20 md:gap-6 lg:px-8">
        <Link :href="route('public.catalog.index')" class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden md:h-20 md:w-20" aria-label="Samy Boutique, ver toda la colección">
          <img :src="$page.props.logoUrl" alt="" class="h-16 w-16 max-w-none object-contain md:h-[76px] md:w-[76px]" @error="(event) => { event.target.onerror = null; event.target.src = '/logo-source.png' }">
        </Link>

        <nav aria-label="Colecciones" class="hidden h-full flex-1 items-center justify-center gap-9 md:flex">
          <button v-for="department in departments" :key="department.value || 'todo'" type="button" class="relative flex h-full items-center text-sm font-semibold tracking-wide transition hover:text-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-5px] focus-visible:outline-rose-700" :class="gender === department.value ? 'text-rose-800' : 'text-zinc-700'" :aria-current="gender === department.value ? 'page' : undefined" @click="selectDepartment(department.value)">
            {{ department.label }}
            <span v-if="gender === department.value" class="absolute bottom-0 left-0 right-0 h-0.5 bg-rose-800"></span>
          </button>
        </nav>

        <form role="search" class="relative ml-auto hidden w-48 shrink-0 md:block xl:w-64" @submit.prevent="applyFilters">
          <label for="catalog-search" class="sr-only">Buscar productos por nombre o SKU</label>
          <svg aria-hidden="true" class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7" /><path d="m16 16 5 5" /></svg>
          <input id="catalog-search" v-model="q" type="search" placeholder="Buscar prendas" class="w-full rounded-full border border-stone-200 bg-stone-100 py-2.5 pl-4 pr-10 text-sm focus:border-zinc-900 focus:bg-white focus:outline-none">
        </form>
        <button type="button" class="ml-auto flex h-11 w-11 items-center justify-center text-zinc-900 md:hidden" aria-label="Buscar productos" :aria-expanded="mobileSearchOpen" @click="mobileSearchOpen = !mobileSearchOpen">
          <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="10.5" cy="10.5" r="7" /><path d="m16 16 5 5" /></svg>
        </button>
        <button ref="mobileMenuButton" type="button" class="flex h-11 w-11 items-center justify-center text-zinc-900 md:hidden" aria-label="Abrir menú de colecciones" aria-controls="catalog-mobile-menu" :aria-expanded="mobileMenuOpen" @click="toggleMobileMenu">
          <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
      </div>
      <div v-if="mobileSearchOpen" class="border-t border-stone-100 px-5 py-3 md:hidden">
        <form role="search" @submit.prevent="applyFilters"><label for="catalog-search-mobile" class="sr-only">Buscar productos por nombre o SKU</label><input id="catalog-search-mobile" v-model="q" type="search" placeholder="Buscar prendas o SKU" class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:border-zinc-900 focus:outline-none"></form>
      </div>
    </header>

    <Teleport to="body">
      <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 md:hidden" @keydown.esc="mobileMenuOpen = false">
        <button type="button" class="absolute inset-0 w-full bg-zinc-950/45" aria-label="Cerrar menú" @click="mobileMenuOpen = false"></button>
        <div id="catalog-mobile-menu" role="dialog" aria-modal="true" aria-label="Menú de colecciones" class="absolute inset-y-0 right-0 flex w-[min(88vw,360px)] flex-col bg-white text-zinc-900 shadow-2xl">
          <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-800">Samy Boutique</span>
            <button ref="mobileMenuCloseButton" type="button" class="flex h-10 w-10 items-center justify-center rounded-full hover:bg-stone-100" aria-label="Cerrar menú" @click="mobileMenuOpen = false">
              <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19" /></svg>
            </button>
          </div>
          <nav aria-label="Colecciones" class="flex-1 px-5 py-7">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">Explora la colección</p>
            <button v-for="department in departments" :key="department.value || 'todo'" type="button" class="flex min-h-16 w-full items-center justify-between border-b border-stone-200 text-left text-xl font-medium transition hover:text-rose-800" :class="gender === department.value ? 'text-rose-800' : 'text-zinc-900'" :aria-current="gender === department.value ? 'page' : undefined" @click="selectDepartment(department.value)">
              <span>{{ department.label }}</span><span aria-hidden="true" class="text-lg text-stone-400">→</span>
            </button>
          </nav>
          <p class="border-t border-stone-200 px-5 py-5 text-xs text-stone-500">Encuentra prendas y accesorios que van contigo.</p>
        </div>
      </div>
    </Teleport>

    <section v-if="currentSlide && !gender && !hasRefinements" class="group relative w-full overflow-hidden bg-stone-900 text-white" aria-label="Destacados" @touchstart.passive="startSwipe" @touchend.passive="endSwipe">
      <div class="relative h-[calc(100svh-4.5rem)] min-h-[26rem] w-full overflow-hidden sm:aspect-[3/1] sm:h-auto sm:min-h-0">
        <Transition enter-active-class="transition-transform duration-500 ease-out motion-reduce:transition-none" leave-active-class="transition-transform duration-500 ease-out motion-reduce:transition-none" :enter-from-class="slideDirection === 'next' ? 'translate-x-full' : '-translate-x-full'" :leave-to-class="slideDirection === 'next' ? '-translate-x-full' : 'translate-x-full'">
          <div :key="currentSlide.id" class="absolute inset-0">
            <picture class="absolute inset-0 block">
              <source media="(max-width: 639px)" :srcset="currentSlide.mobile_url">
              <img :src="currentSlide.desktop_url" :alt="currentSlide.title || 'Colección Samy Boutique'" class="h-full w-full object-cover object-center">
            </picture>
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent sm:bg-gradient-to-r sm:from-black/65 sm:via-black/15"></div>
            <div class="absolute inset-0 flex flex-col justify-end px-6 pb-12 pt-8 sm:p-[4%]">
              <div class="max-w-[90%] sm:max-w-[62%]">
                <h2 v-if="currentSlide.title" class="line-clamp-2 text-2xl font-semibold leading-tight drop-shadow-sm sm:text-[clamp(1.25rem,3vw,3rem)]">{{ currentSlide.title }}</h2>
                <p v-if="currentSlide.subtitle" class="mt-2 line-clamp-3 text-sm drop-shadow-sm sm:mt-[1%] sm:line-clamp-2 sm:text-[clamp(0.75rem,1.25vw,1.125rem)]">{{ currentSlide.subtitle }}</p>
                <a v-if="currentSlide.destination && currentSlide.button_text" :href="currentSlide.destination" class="mt-5 inline-flex min-h-10 w-fit items-center gap-3 bg-white px-5 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-stone-100 sm:mt-[2%]"><span>{{ currentSlide.button_text }}</span><span aria-hidden="true">→</span></a>
              </div>
            </div>
          </div>
        </Transition>
        <div v-if="slides.length > 1" class="pointer-events-none absolute inset-0 hidden items-center justify-between opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100 sm:flex">
          <button type="button" class="pointer-events-auto rounded-r-full bg-white/90 px-3 py-4 text-xl text-zinc-900 shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-zinc-900" aria-label="Diapositiva anterior" @click="advanceSlide(-1)">‹</button>
          <button type="button" class="pointer-events-auto rounded-l-full bg-white/90 px-3 py-4 text-xl text-zinc-900 shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-zinc-900" aria-label="Diapositiva siguiente" @click="advanceSlide(1)">›</button>
        </div>
        <div v-if="slides.length > 1" class="absolute bottom-[4%] left-1/2 hidden -translate-x-1/2 items-center gap-2 sm:flex">
          <button v-for="(slide, index) in slides" :key="slide.id" type="button" class="h-2 w-2 rounded-full border border-white" :class="index === activeSlide ? 'bg-white' : 'bg-white/30'" :aria-label="'Ir a diapositiva ' + (index + 1)" :aria-pressed="index === activeSlide" @click="goToSlide(index)"></button>
        </div>
      </div>
      <div v-if="slides.length > 1" class="flex items-center justify-center gap-1 bg-white py-1 text-zinc-900 sm:hidden">
        <button v-for="(slide, index) in slides" :key="slide.id" type="button" class="flex h-8 w-8 items-center justify-center" :aria-label="'Ir a diapositiva ' + (index + 1)" :aria-pressed="index === activeSlide" @click="goToSlide(index)">
          <span class="h-2.5 w-2.5 rounded-full border border-zinc-900" :class="index === activeSlide ? 'bg-zinc-900' : 'bg-white'"></span>
        </button>
      </div>
    </section>

    <div v-if="isOffline" class="mx-auto mt-4 max-w-7xl rounded-xl border border-stone-200 bg-white px-4 py-3 text-center text-xs font-semibold text-stone-500">
      Offline: mostrando última versión guardada
    </div>

    <section class="border-b border-stone-200 bg-stone-50" aria-label="Ubicación de la tienda">
      <div class="mx-auto grid max-w-[1500px] items-center gap-5 px-4 py-6 sm:px-6 md:grid-cols-[minmax(240px,0.8fr)_minmax(0,1.4fr)] md:gap-8 lg:px-8">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-800">Visítanos en tienda</p>
          <h2 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900">Samy Boutique</h2>
          <address class="mt-3 text-sm not-italic leading-relaxed text-stone-600">C. Carl.G.Mendez 10, San Luis<br>Jalpa de Méndez, Tabasco · C.P. 86200</address>
          <a
            href="https://maps.app.goo.gl/aYFV7HQkruVWgCeu5"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Cómo llegar a Samy Boutique en Google Maps (abre en otra pestaña)"
            class="mt-4 inline-flex min-h-11 items-center gap-3 rounded-full bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-rose-800"
          >Cómo llegar <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg></a>
        </div>
        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
          <iframe
            title="Ubicación de Samy Boutique en Google Maps"
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3790.708987295457!2d-93.0629967!3d18.177315!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x85ee7d737e2b4101%3A0x3803c711f7208282!2sSamy%20Boutique!5e0!3m2!1ses-419!2smx!4v1793731068383!5m2!1ses-419!2smx"
            class="block h-64 w-full border-0 sm:h-72"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
          ></iframe>
        </div>
      </div>
    </section>
    <main class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <div class="lg:flex lg:items-start lg:gap-8 xl:gap-10">
        <div v-if="mobileFiltersOpen" class="fixed inset-0 z-40 bg-zinc-950/40 lg:hidden" @click="mobileFiltersOpen = false"></div>
        <aside :class="mobileFiltersOpen ? 'fixed inset-y-0 right-0 z-50 flex w-[min(90vw,380px)] flex-col bg-white shadow-2xl' : 'hidden'" class="lg:static lg:block lg:w-52 lg:shrink-0 lg:bg-transparent lg:shadow-none xl:w-56" :role="mobileFiltersOpen ? 'dialog' : 'complementary'" :aria-modal="mobileFiltersOpen ? 'true' : undefined" aria-label="Filtros del catálogo" @keydown.esc="mobileFiltersOpen = false">
          <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4 lg:px-0 lg:pb-4 lg:pt-1">
            <div>
              <h3 class="text-lg font-semibold text-zinc-900 lg:text-xs lg:uppercase lg:tracking-[0.16em]">Filtros <span v-if="activeFilterCount" class="text-sm font-normal text-stone-500 lg:text-xs">({{ activeFilterCount }})</span></h3>
            </div>
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-stone-500 hover:bg-stone-100 lg:hidden" aria-label="Cerrar filtros" @click="mobileFiltersOpen = false">✕</button>
          </div>
          <div class="min-h-0 flex-1 overflow-y-auto px-5 lg:overflow-visible lg:px-0">
            <section v-for="group in filterGroups" :key="group.key" class="border-b border-stone-200 py-5" :aria-label="group.label">
              <div v-if="group.options.length">
                <div class="mb-3 flex items-center justify-between gap-2">
                  <h4 class="text-sm font-semibold text-zinc-900">{{ group.label }}</h4>
                </div>
                <div class="space-y-0.5">
                  <button type="button" class="flex min-h-9 w-full items-center justify-between text-left text-sm transition hover:text-rose-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700" :class="!selectedFilterValue(group.key) ? 'font-semibold text-rose-800' : 'text-stone-600'" :aria-pressed="!selectedFilterValue(group.key)" @click="chooseFilter(group.key, '')"><span>Todas</span><span v-if="!selectedFilterValue(group.key)" aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-rose-700"></span></button>
                  <button v-for="option in group.options" :key="option.value" type="button" class="flex min-h-9 w-full items-center justify-between gap-2 text-left text-sm transition hover:text-rose-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700" :class="selectedFilterValue(group.key) === option.value ? 'font-semibold text-rose-800' : 'text-stone-600'" :aria-pressed="selectedFilterValue(group.key) === option.value" @click="chooseFilter(group.key, option.value)">
                    <span class="flex items-center gap-2"><span v-if="group.key === 'color' && option.hex" class="h-3.5 w-3.5 rounded-full border border-stone-300" :style="{ backgroundColor: option.hex }"></span>{{ option.label }}</span>
                    <span v-if="selectedFilterValue(group.key) === option.value" aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-700"></span>
                  </button>
                </div>
              </div>
            </section>
          </div>
          <div class="border-t border-stone-200 p-5 lg:border-0 lg:px-0 lg:py-5">
            <button v-if="hasRefinements" type="button" class="text-sm font-semibold text-rose-800 underline underline-offset-4 hover:text-rose-950" @click="clearRefinements">Limpiar filtros</button>
            <p v-else class="text-xs text-stone-500 lg:hidden">Elige una opción para refinar la colección.</p>
            <button type="button" class="mt-5 w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white lg:hidden" @click="mobileFiltersOpen = false">Ver {{ products?.meta?.total ?? 0 }} productos</button>
          </div>
        </aside>

        <div class="min-w-0 flex-1">
          <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
              <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-stone-300 bg-white px-4 text-sm font-semibold text-zinc-900 lg:hidden" :aria-expanded="mobileFiltersOpen" @click="mobileFiltersOpen = true">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h16M7 12h10M10 17h4" /></svg>
                Filtros <span v-if="activeFilterCount" class="rounded-full bg-rose-800 px-1.5 text-xs text-white">{{ activeFilterCount }}</span>
              </button>
              <h1 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">{{ departmentTitle }}</h1>
              <span class="text-sm text-stone-500">{{ paginationSummary }}</span>
            </div>
            <div class="flex items-center gap-2">
              <label for="catalog-sort" class="hidden text-sm text-stone-500 sm:block">Ordenar por</label>
              <select id="catalog-sort" v-model="sort" class="rounded-lg border-stone-200 bg-white py-2 pl-3 pr-9 text-sm font-medium text-zinc-900 focus:border-rose-700 focus:ring-rose-700">
                <option value="newest">Más recientes</option>
                <option value="price_asc">Menor precio</option>
                <option value="price_desc">Mayor precio</option>
              </select>
            </div>
          </div>

          <div class="mb-6 flex flex-wrap items-center gap-2" aria-label="Filtros aplicados">
            <span class="mr-1 text-xs font-semibold uppercase tracking-[0.12em] text-stone-500">Filtros activos</span>
            <span v-if="!activeFilters.length" class="text-sm text-stone-500">Ninguno</span>
            <button v-for="filter in activeFilters" :key="filter.key" type="button" class="inline-flex min-h-9 items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-900 transition hover:border-rose-400 hover:bg-rose-100" :aria-label="'Quitar filtro ' + filter.label" @click="removeFilter(filter.key)">{{ filter.label }} <span aria-hidden="true" class="text-base leading-none">×</span></button>
            <button v-if="activeFilters.length" type="button" class="ml-1 text-xs font-semibold text-stone-600 underline underline-offset-4 hover:text-zinc-900" @click="clearFilters">Limpiar todo</button>
          </div>

      <section v-if="hasProducts" class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 2xl:grid-cols-4">
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

          <div class="p-3 sm:p-4">
            <h2 class="line-clamp-2 text-sm font-semibold text-zinc-900">{{ product.name }}</h2>
            <div v-if="product.occasions?.length" class="mt-2 flex flex-wrap gap-1">
              <span v-for="occasion in product.occasions" :key="occasion.id" class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-900">{{ occasion.name }}</span>
            </div>
            <p class="mt-2 text-base font-semibold text-zinc-900 sm:text-lg">Desde {{ money(minVariantPrice(product)) }}</p>

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
        <p class="text-lg font-semibold text-zinc-900">No encontramos productos con esta selección</p>
        <p class="mt-2 text-sm text-stone-500">Prueba con otros filtros o vuelve a ver la colección completa.</p>
        <button v-if="activeFilters.length" type="button" class="mt-5 rounded-full border border-zinc-900 px-5 py-2 text-sm font-semibold text-zinc-900 hover:bg-zinc-900 hover:text-white" @click="clearFilters">Limpiar filtros</button>
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
            {{ currentPage }} / {{ lastPage }}
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
        </div>
      </div>
    </main>
  </div>
</template>
