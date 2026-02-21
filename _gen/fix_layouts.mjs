import { writeFileSync } from 'fs'
import { join } from 'path'

const base = 'c:/xampp/htdocs/Samy-Boutique/resources/js'

// ─── Layaways/Index.vue ────────────────────────────────────────────────────────
const layawaysIndex = `<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  layaways: { type: Object, required: true },
  filters:  { type: Object, required: true },
  can:      { type: Object, default: () => ({}) },
})

const search = ref(props.filters.q ?? '')
const activeStatus = ref(props.filters.status ?? 'open')

const tabs = [
  { key: 'open',       label: 'Activos' },
  { key: 'liquidated', label: 'Liquidados' },
  { key: 'cancelled',  label: 'Cancelados' },
]

const badgeClass = {
  open:       'bg-amber-100 text-amber-800',
  liquidated: 'bg-emerald-100 text-emerald-800',
  cancelled:  'bg-red-100 text-red-700',
}
const statusLabel = {
  open:       'Activo',
  liquidated: 'Liquidado',
  cancelled:  'Cancelado',
}

function money(v) { return Number(v).toFixed(2) }

function applyFilters() {
  router.get(route('layaways.index'), {
    status: activeStatus.value,
    q: search.value || undefined,
  }, { preserveState: true, replace: true })
}

function setTab(key) {
  activeStatus.value = key
  applyFilters()
}

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 350)
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
<\/script>

<template>
  <Head title="Apartados" />

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Apartados</h1>
        <p class="text-sm text-gray-500 mt-0.5">Gestion de apartados de productos</p>
      </div>
      <Link
        v-if="can.create"
        :href="route('layaways.create')"
        class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo apartado
      </Link>
    </div>

    <!-- Filters card -->
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4">
      <!-- Tabs -->
      <div class="flex gap-1 border-b border-gray-200">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="setTab(tab.key)"
          class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
          :class="activeStatus === tab.key
            ? 'border-gray-900 text-gray-900'
            : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          {{ tab.label }}
        </button>
      </div>
      <!-- Search -->
      <div class="relative max-w-sm">
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
          fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
        </svg>
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Buscar por folio o cliente..."
          class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
        />
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
      <!-- Desktop table -->
      <table class="min-w-full divide-y divide-gray-100 hidden sm:table">
        <thead class="bg-gray-50">
          <tr>
            <th class="py-3 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Folio</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Cliente</th>
            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Abonado</th>
            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Saldo</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
            <th class="py-3 pl-3 pr-5 text-right" />
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="layaways.data.length === 0">
            <td colspan="8" class="py-12 text-center text-sm text-gray-400">Sin apartados para mostrar</td>
          </tr>
          <tr v-for="l in layaways.data" :key="l.id" class="hover:bg-gray-50 transition">
            <td class="py-3 pl-5 pr-3 text-sm font-mono font-semibold text-gray-900">#{{ l.id }}</td>
            <td class="px-3 py-3 text-sm text-gray-700">{{ l.customer?.name ?? '—' }}</td>
            <td class="px-3 py-3 text-sm text-right text-gray-900 font-medium">\${{ money(l.subtotal) }}</td>
            <td class="px-3 py-3 text-sm text-right text-emerald-600 font-medium">\${{ money(l.paid_total) }}</td>
            <td class="px-3 py-3 text-sm text-right font-semibold"
              :class="Number(l.balance) > 0 ? 'text-amber-600' : 'text-gray-500'">\${{ money(l.balance) }}</td>
            <td class="px-3 py-3 text-sm text-gray-500">{{ fmtDate(l.created_at) }}</td>
            <td class="px-3 py-3 text-center">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="badgeClass[l.status]">{{ statusLabel[l.status] }}</span>
            </td>
            <td class="py-3 pl-3 pr-5 text-right">
              <Link :href="route('layaways.show', l.id)"
                class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 transition">
                Ver
              </Link>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Mobile cards -->
      <ul class="sm:hidden divide-y divide-gray-100">
        <li v-if="layaways.data.length === 0" class="py-10 text-center text-sm text-gray-400">
          Sin apartados para mostrar
        </li>
        <li v-for="l in layaways.data" :key="l.id" class="p-4 space-y-2">
          <div class="flex items-center justify-between">
            <span class="font-mono font-bold text-gray-900">#{{ l.id }}</span>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :class="badgeClass[l.status]">{{ statusLabel[l.status] }}</span>
          </div>
          <p class="text-sm text-gray-700">{{ l.customer?.name ?? '— Sin cliente' }}</p>
          <div class="grid grid-cols-3 text-xs text-gray-500 gap-1">
            <span>Total: <strong class="text-gray-900">\${{ money(l.subtotal) }}</strong></span>
            <span>Abonado: <strong class="text-emerald-600">\${{ money(l.paid_total) }}</strong></span>
            <span>Saldo: <strong :class="Number(l.balance) > 0 ? 'text-amber-600' : 'text-gray-500'">\${{ money(l.balance) }}</strong></span>
          </div>
          <div class="text-right">
            <Link :href="route('layaways.show', l.id)"
              class="text-xs font-medium text-gray-600 hover:text-gray-900 underline">Ver detalles</Link>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="layaways.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">Mostrando {{ layaways.from }}–{{ layaways.to }} de {{ layaways.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in layaways.links" :key="link.label" :href="link.url ?? '#'"
            :class="['px-2.5 py-1 rounded text-xs font-medium transition',
              link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100',
              !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </div>
</template>
`

// ─── Layaways/Create.vue ───────────────────────────────────────────────────────
const layawaysCreate = `<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  products:  { type: Array, required: true },
  customers: { type: Array, required: true },
})

const productQuery = ref('')
const filteredProducts = computed(() => {
  const q = productQuery.value.toLowerCase().trim()
  if (!q) return props.products
  return props.products.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.sku && p.sku.toLowerCase().includes(q))
  )
})

const cart = ref([])
const inCartIds = computed(() => new Set(cart.value.map(i => i.id)))
function addToCart(product) { if (!inCartIds.value.has(product.id)) cart.value.push(product) }
function removeFromCart(id) { cart.value = cart.value.filter(i => i.id !== id) }
const cartTotal = computed(() => cart.value.reduce((s, p) => s + Number(p.sale_price), 0).toFixed(2))

const customerQuery = ref('')
const selectedCustomer = ref(null)
const customerSuggestions = computed(() => {
  const q = customerQuery.value.toLowerCase().trim()
  if (!q || selectedCustomer.value) return []
  return props.customers.filter(c =>
    c.name.toLowerCase().includes(q) || (c.phone && c.phone.includes(q))
  ).slice(0, 6)
})
function selectCustomer(c) { selectedCustomer.value = c; customerQuery.value = c.name }
function clearCustomer() { selectedCustomer.value = null; customerQuery.value = '' }

const initialPaymentAmount = ref('')
const initialPaymentMethod = ref('cash')

const form = useForm({ customer_id: null, items: [], payments: [] })

function submit() {
  form.customer_id = selectedCustomer.value?.id ?? null
  form.items = cart.value.map(p => ({ product_id: p.id }))
  const amt = parseFloat(initialPaymentAmount.value)
  form.payments = (!isNaN(amt) && amt > 0) ? [{ method: initialPaymentMethod.value, amount: amt }] : []
  form.post(route('layaways.store'))
}

function money(v) { return Number(v).toFixed(2) }
function thumbUrl(product) {
  const img = product.images?.[0]
  return img ? '/storage/' + img.path : null
}
<\/script>

<template>
  <Head title="Nuevo apartado" />

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('layaways.index')" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <h1 class="text-2xl font-bold text-gray-900">Nuevo apartado</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Product grid -->
      <div class="lg:col-span-2 space-y-4">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Productos disponibles</h2>
          <div class="relative mb-4">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
              fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <input v-model="productQuery" type="text" placeholder="Buscar por nombre o SKU..."
              class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>
          <div v-if="filteredProducts.length === 0" class="py-10 text-center text-sm text-gray-400">Sin productos disponibles</div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[520px] overflow-y-auto pr-1">
            <button v-for="p in filteredProducts" :key="p.id" @click="addToCart(p)"
              :disabled="inCartIds.has(p.id)"
              class="relative flex flex-col rounded-xl border text-left transition focus:outline-none"
              :class="inCartIds.has(p.id) ? 'border-emerald-300 bg-emerald-50 opacity-80 cursor-default' : 'border-gray-200 bg-white hover:border-gray-900 hover:shadow-sm'">
              <div class="aspect-square w-full overflow-hidden rounded-t-xl bg-gray-100">
                <img v-if="thumbUrl(p)" :src="thumbUrl(p)" :alt="p.name" class="h-full w-full object-cover" />
                <div v-else class="flex h-full items-center justify-center text-gray-300">
                  <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                  </svg>
                </div>
              </div>
              <div v-if="inCartIds.has(p.id)" class="absolute inset-0 flex items-center justify-center rounded-xl bg-emerald-600/20">
                <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </div>
              <div class="p-2 space-y-0.5">
                <p class="text-xs font-semibold text-gray-800 line-clamp-2 leading-tight">{{ p.name }}</p>
                <div class="flex flex-wrap gap-1 mt-1">
                  <span v-if="p.size" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ p.size.name }}</span>
                  <span v-if="p.color" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ p.color.name }}</span>
                </div>
                <p class="text-sm font-bold text-gray-900 mt-1">\${{ money(p.sale_price) }}</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Summary -->
      <div class="space-y-4">
        <!-- Customer -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Cliente</h2>
          <div class="relative">
            <input v-model="customerQuery" type="text" placeholder="Buscar cliente..."
              :disabled="!!selectedCustomer"
              class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400 disabled:bg-gray-50" />
            <ul v-if="customerSuggestions.length"
              class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-md max-h-48 overflow-y-auto">
              <li v-for="c in customerSuggestions" :key="c.id" @click="selectCustomer(c)"
                class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                <span class="font-medium text-gray-800">{{ c.name }}</span>
                <span v-if="c.phone" class="ml-2 text-gray-400 text-xs">{{ c.phone }}</span>
              </li>
            </ul>
          </div>
          <div v-if="selectedCustomer" class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
            <div>
              <p class="text-sm font-semibold text-gray-800">{{ selectedCustomer.name }}</p>
              <p v-if="selectedCustomer.phone" class="text-xs text-gray-500">{{ selectedCustomer.phone }}</p>
            </div>
            <button @click="clearCustomer" class="text-gray-400 hover:text-red-500 transition">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <p class="text-xs text-gray-400">Opcional</p>
        </div>

        <!-- Cart -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Carrito <span class="text-gray-400 font-normal">({{ cart.length }})</span></h2>
          <p v-if="form.errors.items" class="text-xs text-red-600">{{ form.errors.items }}</p>
          <div v-if="cart.length === 0" class="py-6 text-center text-sm text-gray-400">Agrega productos desde la lista</div>
          <ul v-else class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
            <li v-for="item in cart" :key="item.id" class="flex items-center gap-3 py-2">
              <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-lg bg-gray-100">
                <img v-if="thumbUrl(item)" :src="thumbUrl(item)" :alt="item.name" class="h-full w-full object-cover" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-gray-800 truncate">{{ item.name }}</p>
                <p class="text-xs text-gray-400">
                  <span v-if="item.size">{{ item.size.name }}</span>
                  <span v-if="item.size && item.color"> · </span>
                  <span v-if="item.color">{{ item.color.name }}</span>
                </p>
              </div>
              <span class="text-sm font-semibold text-gray-900 flex-shrink-0">\${{ money(item.sale_price) }}</span>
              <button @click="removeFromCart(item.id)" class="text-gray-300 hover:text-red-500 transition flex-shrink-0">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </li>
          </ul>
          <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
            <span class="text-sm font-semibold text-gray-700">Total</span>
            <span class="text-lg font-bold text-gray-900">\${{ cartTotal }}</span>
          </div>
        </div>

        <!-- Initial payment -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Abono inicial <span class="text-gray-400 font-normal">(opcional)</span></h2>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
            <input v-model="initialPaymentAmount" type="number" min="0" step="0.01" placeholder="0.00"
              class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>
          <select v-model="initialPaymentMethod"
            class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400">
            <option value="cash">Efectivo</option>
            <option value="card">Tarjeta</option>
            <option value="transfer">Transferencia</option>
            <option value="other">Otro</option>
          </select>
        </div>

        <button @click="submit" :disabled="cart.length === 0 || form.processing"
          class="w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
          {{ form.processing ? 'Creando...' : 'Crear apartado' }}
        </button>
      </div>
    </div>
  </div>
</template>
`

// ─── Layaways/Show.vue ─────────────────────────────────────────────────────────
const layawaysShow = `<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  layaway: { type: Object, required: true },
  can:     { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash ?? {})
const isOpen = computed(() => props.layaway.status === 'open')

const badgeClass = { open: 'bg-amber-100 text-amber-800', liquidated: 'bg-emerald-100 text-emerald-800', cancelled: 'bg-red-100 text-red-700' }
const statusLabel = { open: 'Activo', liquidated: 'Liquidado', cancelled: 'Cancelado' }
const methodLabel  = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', other: 'Otro' }

function money(v) { return Number(v).toFixed(2) }
function fmtDate(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
function fmtDateShort(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }
function thumbUrl(product) { const img = product?.images?.[0]; return img ? '/storage/' + img.path : null }

const addPaymentForm = useForm({ method: 'cash', amount: '', reference: '' })
function submitAddPayment() { addPaymentForm.post(route('layaways.payments.store', props.layaway.id), { onSuccess: () => addPaymentForm.reset() }) }

const remaining = computed(() => Number(props.layaway.balance))
const liquidateForm = useForm({ payments: [{ method: 'cash', amount: '' }] })
watch(remaining, (val) => { if (liquidateForm.payments.length === 1) liquidateForm.payments[0].amount = val > 0 ? val.toFixed(2) : '' }, { immediate: true })
function addLiquidatePayment() { liquidateForm.payments.push({ method: 'cash', amount: '' }) }
function removeLiquidatePayment(i) { if (liquidateForm.payments.length > 1) liquidateForm.payments.splice(i, 1) }
function submitLiquidate() { liquidateForm.post(route('layaways.liquidate', props.layaway.id)) }

const showCancelConfirm = ref(false)
const cancelForm = useForm({})
function submitCancel() { cancelForm.post(route('layaways.cancel', props.layaway.id)); showCancelConfirm.value = false }
<\/script>

<template>
  <Head :title="'Apartado #' + layaway.id" />

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <Link :href="route('layaways.index')" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </Link>
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">Apartado #{{ layaway.id }}</h1>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold" :class="badgeClass[layaway.status]">{{ statusLabel[layaway.status] }}</span>
          </div>
          <p class="text-sm text-gray-500 mt-0.5">Creado {{ fmtDate(layaway.created_at) }}<span v-if="layaway.creator"> por {{ layaway.creator.name }}</span></p>
        </div>
      </div>
      <button v-if="isOpen && can.cancel" @click="showCancelConfirm = true"
        class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        Cancelar apartado
      </button>
    </div>

    <!-- Flash -->
    <div v-if="flash.success" class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
      <svg class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
      {{ flash.success }}
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">\${{ money(layaway.subtotal) }}</p>
      </div>
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Abonado</p>
        <p class="mt-1 text-2xl font-bold text-emerald-600">\${{ money(layaway.paid_total) }}</p>
      </div>
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Saldo</p>
        <p class="mt-1 text-2xl font-bold" :class="remaining > 0 ? 'text-amber-600' : 'text-gray-400'">\${{ money(layaway.balance) }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left column -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Customer -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Informacion del cliente</h2>
          <div v-if="layaway.customer" class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
              <span class="text-sm font-bold text-gray-600">{{ layaway.customer.name.charAt(0).toUpperCase() }}</span>
            </div>
            <div>
              <p class="font-semibold text-gray-800">{{ layaway.customer.name }}</p>
              <p v-if="layaway.customer.phone" class="text-sm text-gray-500">{{ layaway.customer.phone }}</p>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 italic">Sin cliente asignado</p>
        </div>

        <!-- Products -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Productos ({{ layaway.items.length }})</h2>
          <ul class="divide-y divide-gray-100">
            <li v-for="item in layaway.items" :key="item.id" class="flex items-center gap-3 py-3">
              <div class="h-12 w-12 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                <img v-if="thumbUrl(item.product)" :src="thumbUrl(item.product)" :alt="item.name" class="h-full w-full object-cover" />
                <div v-else class="flex h-full items-center justify-center text-gray-300">
                  <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                </div>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-800">{{ item.name }}</p>
                <p class="text-xs text-gray-400 mt-0.5">
                  <span v-if="item.sku" class="font-mono">{{ item.sku }}</span>
                  <span v-if="item.product?.size"> · {{ item.product.size.name }}</span>
                  <span v-if="item.product?.color"> / {{ item.product.color.name }}</span>
                </p>
              </div>
              <span class="text-sm font-bold text-gray-900 flex-shrink-0">\${{ money(item.unit_price) }}</span>
            </li>
          </ul>
        </div>

        <!-- Payments history -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Historial de abonos</h2>
          <div v-if="layaway.payments.length === 0" class="py-6 text-center text-sm text-gray-400">Sin abonos registrados</div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Metodo</th>
                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Referencia</th>
                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Monto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="pmt in layaway.payments" :key="pmt.id">
                <td class="py-2 text-gray-500">{{ fmtDateShort(pmt.created_at) }}</td>
                <td class="py-2 text-gray-700">{{ methodLabel[pmt.method] ?? pmt.method }}</td>
                <td class="py-2 text-gray-400 font-mono text-xs">{{ pmt.reference ?? '—' }}</td>
                <td class="py-2 text-right font-semibold text-emerald-600">\${{ money(pmt.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right column: actions -->
      <div class="space-y-4">
        <!-- Add payment -->
        <div v-if="isOpen" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700">Registrar abono</h2>
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Metodo</label>
              <select v-model="addPaymentForm.method" class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400">
                <option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="other">Otro</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Monto</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                <input v-model="addPaymentForm.amount" type="number" min="0.01" step="0.01" placeholder="0.00"
                  class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
              </div>
              <p v-if="addPaymentForm.errors.amount" class="mt-1 text-xs text-red-600">{{ addPaymentForm.errors.amount }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Referencia <span class="text-gray-400">(opcional)</span></label>
              <input v-model="addPaymentForm.reference" type="text" placeholder="No. transaccion..."
                class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
            </div>
          </div>
          <button @click="submitAddPayment" :disabled="addPaymentForm.processing"
            class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition disabled:opacity-50">
            {{ addPaymentForm.processing ? 'Guardando...' : 'Registrar abono' }}
          </button>
        </div>

        <!-- Liquidate -->
        <div v-if="isOpen" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700">Liquidar apartado</h2>
          <div v-if="remaining > 0" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
            Saldo pendiente: <strong>\${{ money(remaining) }}</strong>
          </div>
          <div v-else class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-xs text-emerald-800">
            Saldo en cero. El apartado puede liquidarse.
          </div>
          <div class="space-y-3">
            <div v-for="(pmt, i) in liquidateForm.payments" :key="i" class="flex gap-2">
              <select v-model="pmt.method" class="flex-shrink-0 w-28 rounded-lg border border-gray-200 py-2 px-2 text-xs focus:border-gray-400 focus:outline-none">
                <option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transf.</option><option value="other">Otro</option>
              </select>
              <div class="relative flex-1">
                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                <input v-model="pmt.amount" type="number" min="0" step="0.01" placeholder="0.00"
                  class="w-full rounded-lg border border-gray-200 py-2 pl-5 pr-2 text-sm focus:border-gray-400 focus:outline-none" />
              </div>
              <button v-if="liquidateForm.payments.length > 1" @click="removeLiquidatePayment(i)" class="text-gray-300 hover:text-red-500 transition px-1">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <button @click="addLiquidatePayment" class="text-xs text-gray-500 hover:text-gray-800 underline underline-offset-2 transition">+ Agregar forma de pago</button>
          </div>
          <button @click="submitLiquidate" :disabled="liquidateForm.processing"
            class="w-full rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition disabled:opacity-50">
            {{ liquidateForm.processing ? 'Procesando...' : 'Liquidar y generar venta' }}
          </button>
        </div>

        <!-- Info when not open -->
        <div v-if="!isOpen" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 text-sm text-gray-500 space-y-2">
          <p v-if="layaway.status === 'liquidated'">Liquidado el <strong>{{ fmtDateShort(layaway.liquidated_at) }}</strong>.</p>
          <p v-if="layaway.status === 'cancelled'">Cancelado el <strong>{{ fmtDateShort(layaway.cancelled_at) }}</strong>.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Cancel confirm modal -->
  <Teleport to="body">
    <div v-if="showCancelConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-gray-900">Cancelar apartado</h3>
        <p class="text-sm text-gray-600">Esta accion cancelara el apartado <strong>#{{ layaway.id }}</strong> y los productos regresaran al estado <em>disponible</em>. No se puede deshacer.</p>
        <div class="flex gap-3 justify-end pt-2">
          <button @click="showCancelConfirm = false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Volver</button>
          <button @click="submitCancel" :disabled="cancelForm.processing"
            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition disabled:opacity-50">
            {{ cancelForm.processing ? 'Cancelando...' : 'Confirmar cancelacion' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
`

// ─── Customers/Index.vue ───────────────────────────────────────────────────────
const customersIndex = `<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import LoyaltyBadge from '@/Components/Customers/LoyaltyBadge.vue'

const props = defineProps({
  customers: { type: Object, required: true },
  filters:   { type: Object, required: true },
  can:       { type: Object, default: () => ({}) },
})

const search = ref(props.filters.q ?? '')

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(route('customers.index'), { q: search.value || undefined }, { preserveState: true, replace: true })
  }, 300)
}
function clearSearch() {
  search.value = ''
  router.get(route('customers.index'), {}, { preserveState: true, replace: true })
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
<\/script>

<template>
  <Head title="Clientes" />

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Clientes</h1>
        <p class="text-sm text-gray-500 mt-0.5">Directorio y fidelidad de clientes</p>
      </div>
      <Link v-if="can.create" :href="route('customers.create')"
        class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo cliente
      </Link>
    </div>

    <!-- Search -->
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
      <div class="relative max-w-md">
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
          fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
        </svg>
        <input v-model="search" @input="onSearchInput" type="text"
          placeholder="Buscar por nombre o telefono..."
          class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-8 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
        <button v-if="search" @click="clearSearch"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
      <!-- Desktop -->
      <table class="min-w-full divide-y divide-gray-100 hidden sm:table">
        <thead class="bg-gray-50">
          <tr>
            <th class="py-3 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nombre</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Telefono</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Compras</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fidelidad</th>
            <th class="py-3 pl-3 pr-5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="customers.data.length === 0">
            <td colspan="5" class="py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <svg class="h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <p class="text-sm text-gray-500">No hay clientes registrados</p>
                <Link v-if="can.create" :href="route('customers.create')"
                  class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 transition">
                  Crear primer cliente
                </Link>
              </div>
            </td>
          </tr>
          <tr v-for="c in customers.data" :key="c.id" class="hover:bg-gray-50 transition">
            <td class="py-3 pl-5 pr-3">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                  <span class="text-xs font-bold text-gray-600">{{ c.name.charAt(0).toUpperCase() }}</span>
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">{{ c.name }}</p>
                  <p v-if="c.email" class="text-xs text-gray-400">{{ c.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-3 py-3 text-sm text-gray-600 font-mono">{{ c.phone ?? '—' }}</td>
            <td class="px-3 py-3 text-center"><span class="text-sm font-bold text-gray-900">{{ c.purchases_count }}</span></td>
            <td class="px-3 py-3"><LoyaltyBadge :count="c.purchases_count" size="sm" /></td>
            <td class="py-3 pl-3 pr-5 text-right">
              <div class="flex items-center justify-end gap-3">
                <Link :href="route('customers.show', c.id)" class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 transition">Ver</Link>
                <Link v-if="can.update" :href="route('customers.edit', c.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 underline underline-offset-2 transition">Editar</Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Mobile cards -->
      <ul class="sm:hidden divide-y divide-gray-100">
        <li v-if="customers.data.length === 0" class="py-12 text-center">
          <p class="text-sm text-gray-400 mb-3">No hay clientes registrados</p>
          <Link v-if="can.create" :href="route('customers.create')"
            class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 transition">Crear cliente</Link>
        </li>
        <li v-for="c in customers.data" :key="c.id" class="p-4 space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="h-9 w-9 rounded-full bg-gray-200 flex items-center justify-center">
                <span class="text-sm font-bold text-gray-600">{{ c.name.charAt(0).toUpperCase() }}</span>
              </div>
              <div>
                <p class="text-sm font-semibold text-gray-900">{{ c.name }}</p>
                <p class="text-xs text-gray-400 font-mono">{{ c.phone ?? '—' }}</p>
              </div>
            </div>
            <span class="text-sm font-bold text-gray-700">{{ c.purchases_count }} compras</span>
          </div>
          <LoyaltyBadge :count="c.purchases_count" size="sm" />
          <div class="flex gap-3 pt-1">
            <Link :href="route('customers.show', c.id)" class="text-xs font-medium text-gray-600 hover:text-gray-900 underline">Ver</Link>
            <Link v-if="can.update" :href="route('customers.edit', c.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 underline">Editar</Link>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="customers.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">Mostrando {{ customers.from }}–{{ customers.to }} de {{ customers.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in customers.links" :key="link.label" :href="link.url ?? '#'"
            :class="['px-2.5 py-1 rounded text-xs font-medium transition',
              link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100',
              !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </div>
</template>
`

// ─── Customers/Create.vue ──────────────────────────────────────────────────────
const customersCreate = `<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({ name: '', phone: '', email: '', active: true })
function submit() { form.post(route('customers.store')) }
<\/script>

<template>
  <Head title="Nuevo cliente" />

  <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('customers.index')" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <h1 class="text-2xl font-bold text-gray-900">Nuevo cliente</h1>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre <span class="text-red-500">*</span></label>
        <input v-model="form.name" type="text" placeholder="Nombre completo"
          class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          :class="form.errors.name ? 'border-red-400' : ''" />
        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Telefono <span class="ml-1 text-xs font-normal text-gray-400">(recomendado)</span></label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 6.75Z" />
          </svg>
          <input v-model="form.phone" type="tel" placeholder="10 digitos"
            class="w-full rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
            :class="form.errors.phone ? 'border-red-400' : ''" />
        </div>
        <p v-if="form.errors.phone" class="mt-1.5 text-xs text-red-600">{{ form.errors.phone }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Correo electronico</label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
          </svg>
          <input v-model="form.email" type="email" placeholder="correo@ejemplo.com"
            class="w-full rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
            :class="form.errors.email ? 'border-red-400' : ''" />
        </div>
        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
      </div>

      <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
        <button @click="submit" :disabled="form.processing"
          class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition disabled:opacity-50">
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Guardando...' : 'Guardar cliente' }}
        </button>
        <Link :href="route('customers.index')" class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancelar</Link>
      </div>
    </div>
  </div>
</template>
`

// ─── Customers/Edit.vue ────────────────────────────────────────────────────────
const customersEdit = `<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ customer: { type: Object, required: true } })

const form = useForm({
  name:   props.customer.name,
  phone:  props.customer.phone ?? '',
  email:  props.customer.email ?? '',
  active: props.customer.active ?? true,
})

function submit() { form.put(route('customers.update', props.customer.id)) }
<\/script>

<template>
  <Head :title="'Editar: ' + customer.name" />

  <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('customers.show', customer.id)" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Editar cliente</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ customer.name }}</p>
      </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre <span class="text-red-500">*</span></label>
        <input v-model="form.name" type="text" placeholder="Nombre completo"
          class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          :class="form.errors.name ? 'border-red-400' : ''" />
        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Telefono</label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 6.75Z" />
          </svg>
          <input v-model="form.phone" type="tel" placeholder="10 digitos"
            class="w-full rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
            :class="form.errors.phone ? 'border-red-400' : ''" />
        </div>
        <p v-if="form.errors.phone" class="mt-1.5 text-xs text-red-600">{{ form.errors.phone }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Correo electronico</label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
          </svg>
          <input v-model="form.email" type="email" placeholder="correo@ejemplo.com"
            class="w-full rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
            :class="form.errors.email ? 'border-red-400' : ''" />
        </div>
        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
      </div>

      <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
        <div>
          <p class="text-sm font-medium text-gray-700">Cliente activo</p>
          <p class="text-xs text-gray-400 mt-0.5">Los clientes inactivos no aparecen en busquedas del POS</p>
        </div>
        <button @click="form.active = !form.active" type="button"
          class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
          :class="form.active ? 'bg-emerald-500' : 'bg-gray-200'">
          <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
            :class="form.active ? 'translate-x-5' : 'translate-x-0'" />
        </button>
      </div>

      <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
        <button @click="submit" :disabled="form.processing"
          class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition disabled:opacity-50">
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
        </button>
        <Link :href="route('customers.show', customer.id)" class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancelar</Link>
      </div>
    </div>
  </div>
</template>
`

// ─── Customers/Show.vue ────────────────────────────────────────────────────────
const customersShow = `<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import LoyaltyBadge from '@/Components/Customers/LoyaltyBadge.vue'

const props = defineProps({
  customer:       { type: Object, required: true },
  recentSales:    { type: Array,  default: () => [] },
  recentLayaways: { type: Array,  default: () => [] },
  stats:          { type: Object, default: () => ({}) },
  can:            { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash ?? {})

function money(v) { return Number(v ?? 0).toFixed(2) }
function fmtDate(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }

const saleStatusLabel = { completed: 'Completada', pending: 'Pendiente', cancelled: 'Cancelada' }
const saleStatusClass  = { completed: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', cancelled: 'bg-red-100 text-red-700' }
const layawayStatusLabel = { open: 'Activo', liquidated: 'Liquidado', cancelled: 'Cancelado' }
const layawayStatusClass  = { open: 'bg-amber-100 text-amber-700', liquidated: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-red-100 text-red-700' }
<\/script>

<template>
  <Head :title="customer.name" />

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <Link :href="route('customers.index')" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </Link>
        <div class="flex items-center gap-3">
          <div class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <span class="text-lg font-bold text-gray-600">{{ customer.name.charAt(0).toUpperCase() }}</span>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-2xl font-bold text-gray-900">{{ customer.name }}</h1>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="customer.active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                {{ customer.active ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5 font-mono">{{ customer.phone ?? '— Sin telefono' }}</p>
            <p v-if="customer.email" class="text-xs text-gray-400">{{ customer.email }}</p>
          </div>
        </div>
      </div>
      <Link v-if="can.update" :href="route('customers.edit', customer.id)"
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
        </svg>
        Editar
      </Link>
    </div>

    <!-- Flash -->
    <div v-if="flash.success" class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
      <svg class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
      {{ flash.success }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left column -->
      <div class="space-y-6">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
          <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Compras totales</p>
            <p class="mt-1 text-3xl font-bold text-gray-900">{{ customer.purchases_count }}</p>
          </div>
          <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total gastado</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">\${{ money(stats.total_spent) }}</p>
          </div>
          <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 col-span-2 lg:col-span-1">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ultima compra</p>
            <p class="mt-1 text-base font-semibold text-gray-700">{{ fmtDate(stats.last_sale) }}</p>
          </div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
          <LoyaltyBadge :count="customer.purchases_count" size="md" />
        </div>
      </div>

      <!-- Right column -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Ultimas ventas -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Ultimas ventas</h2>
            <span class="text-xs text-gray-400">Ultimas {{ recentSales.length }}</span>
          </div>
          <div v-if="recentSales.length === 0" class="py-8 text-center text-sm text-gray-400">Sin ventas registradas</div>
          <table v-else class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="py-2.5 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Folio</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                <th class="py-2.5 pl-3 pr-5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="sale in recentSales" :key="sale.id" class="hover:bg-gray-50 transition">
                <td class="py-3 pl-5 pr-3 text-xs font-mono font-semibold text-gray-800">#{{ sale.id }}</td>
                <td class="px-3 py-3 text-xs text-gray-500">{{ fmtDate(sale.created_at) }}</td>
                <td class="px-3 py-3">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="saleStatusClass[sale.status] ?? 'bg-gray-100 text-gray-500'">
                    {{ saleStatusLabel[sale.status] ?? sale.status }}
                  </span>
                </td>
                <td class="py-3 pl-3 pr-5 text-right text-sm font-semibold text-gray-900">\${{ money(sale.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Ultimos apartados -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Ultimos apartados</h2>
            <span class="text-xs text-gray-400">Ultimos {{ recentLayaways.length }}</span>
          </div>
          <div v-if="recentLayaways.length === 0" class="py-8 text-center text-sm text-gray-400">Sin apartados registrados</div>
          <table v-else class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="py-2.5 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Folio</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Saldo</th>
                <th class="py-2.5 pl-3 pr-5 text-right"/>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="la in recentLayaways" :key="la.id" class="hover:bg-gray-50 transition">
                <td class="py-3 pl-5 pr-3 text-xs font-mono font-semibold text-gray-800">#{{ la.id }}</td>
                <td class="px-3 py-3">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="layawayStatusClass[la.status] ?? 'bg-gray-100 text-gray-500'">
                    {{ layawayStatusLabel[la.status] ?? la.status }}
                  </span>
                </td>
                <td class="px-3 py-3 text-right text-sm font-semibold text-gray-900">\${{ money(la.subtotal) }}</td>
                <td class="px-3 py-3 text-right text-sm font-semibold"
                  :class="Number(la.balance) > 0 ? 'text-amber-600' : 'text-gray-400'">
                  \${{ money(la.balance) }}
                </td>
                <td class="py-3 pl-3 pr-5 text-right">
                  <Link :href="route('layaways.show', la.id)"
                    class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 transition">Ver</Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
`

const writes = [
  ['Pages/Layaways/Index.vue',    layawaysIndex],
  ['Pages/Layaways/Create.vue',   layawaysCreate],
  ['Pages/Layaways/Show.vue',     layawaysShow],
  ['Pages/Customers/Index.vue',   customersIndex],
  ['Pages/Customers/Create.vue',  customersCreate],
  ['Pages/Customers/Edit.vue',    customersEdit],
  ['Pages/Customers/Show.vue',    customersShow],
]

writes.forEach(([rel, content]) => {
  writeFileSync(join(base, rel), content, 'utf-8')
  console.log('OK', rel)
})
