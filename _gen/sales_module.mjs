import { writeFileSync, mkdirSync } from 'fs'
import { join, dirname } from 'path'

const base = 'c:/xampp/htdocs/Samy-Boutique/resources/js'

// ─── SaleStatusBadge.vue ───────────────────────────────────────────────────────
const saleStatusBadge = `<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
  size:   { type: String, default: 'sm' }, // sm | md | lg
})

const statusConfig = {
  completed: { label: 'Pagada',    cls: 'bg-emerald-100 text-emerald-700 ring-emerald-200' },
  cancelled: { label: 'Cancelada', cls: 'bg-red-100    text-red-700     ring-red-200'     },
  pending:   { label: 'Pendiente', cls: 'bg-amber-100  text-amber-700   ring-amber-200'   },
}

const sizeClass = {
  sm: 'px-2   py-0.5 text-xs font-semibold',
  md: 'px-2.5 py-1   text-sm font-semibold',
  lg: 'px-3   py-1   text-sm font-bold',
}

const config = computed(() =>
  statusConfig[props.status] ?? { label: props.status, cls: 'bg-gray-100 text-gray-500 ring-gray-200' }
)
<\/script>

<template>
  <span
    class="inline-flex items-center rounded-full ring-1"
    :class="[config.cls, sizeClass[size]]"
  >
    {{ config.label }}
  </span>
</template>
`

// ─── PaymentsList.vue ──────────────────────────────────────────────────────────
const paymentsList = `<script setup>
import { computed } from 'vue'

const props = defineProps({
  payments: { type: Array, required: true },
  compact:  { type: Boolean, default: false },
})

const METHOD = {
  cash:     { label: 'Efectivo',      color: 'text-emerald-600 bg-emerald-50', dot: 'bg-emerald-400' },
  card:     { label: 'Tarjeta',       color: 'text-blue-600    bg-blue-50',    dot: 'bg-blue-400'    },
  transfer: { label: 'Transferencia', color: 'text-violet-600  bg-violet-50',  dot: 'bg-violet-400'  },
  other:    { label: 'Otro',          color: 'text-gray-600    bg-gray-50',    dot: 'bg-gray-400'    },
}

function cfg(method) { return METHOD[method] ?? METHOD.other }
function money(v)    { return Number(v ?? 0).toFixed(2) }

const isMixed = computed(() => new Set(props.payments.map(p => p.method)).size > 1)
<\/script>

<template>
  <!-- Inline badge list (compact=true): used in Index table -->
  <template v-if="compact">
    <span v-if="isMixed"
      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-purple-50 text-purple-700 ring-1 ring-purple-200">
      Mixto
    </span>
    <span v-else-if="payments.length"
      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
      :class="cfg(payments[0].method).color + ' ring-' + cfg(payments[0].method).dot.replace('bg-', '')">
      {{ cfg(payments[0].method).label }}
    </span>
    <span v-else class="text-xs text-gray-400">—</span>
  </template>

  <!-- Full list (compact=false): used in Show page -->
  <template v-else>
    <div v-if="!payments.length" class="text-sm text-gray-400 italic">Sin pagos registrados</div>
    <ul v-else class="space-y-2">
      <li v-for="pmt in payments" :key="pmt.id"
        class="flex items-start justify-between rounded-lg px-4 py-3 ring-1 ring-gray-100 bg-gray-50">
        <div class="flex items-center gap-3">
          <span class="h-2 w-2 rounded-full flex-shrink-0 mt-1.5" :class="cfg(pmt.method).dot" />
          <div>
            <p class="text-sm font-semibold text-gray-800">{{ cfg(pmt.method).label }}</p>
            <p v-if="pmt.method === 'transfer' && pmt.reference"
              class="text-xs text-gray-400 mt-0.5 font-mono">
              Ref: {{ pmt.reference }}
            </p>
          </div>
        </div>
        <span class="text-sm font-bold text-gray-900">\${{ money(pmt.amount) }}</span>
      </li>
    </ul>
    <div v-if="payments.length > 1" class="flex justify-between border-t border-gray-200 pt-2 mt-2">
      <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total pagado</span>
      <span class="text-sm font-bold text-emerald-700">
        \${{ money(payments.reduce((s, p) => s + Number(p.amount), 0)) }}
      </span>
    </div>
  </template>
</template>
`

// ─── Sales/Index.vue ───────────────────────────────────────────────────────────
const salesIndex = `<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import SaleStatusBadge from '@/Components/Sales/SaleStatusBadge.vue'
import PaymentsList    from '@/Components/Sales/PaymentsList.vue'

const props = defineProps({
  sales:      { type: Object, required: true },
  filters:    { type: Object, default: () => ({}) },
  quickStats: { type: Object, default: () => ({}) },
  can:        { type: Object, default: () => ({}) },
})

// ── Reactive filters ────────────────────────────────────────────────────────
const q      = ref(props.filters.q      ?? '')
const status = ref(props.filters.status ?? '')
const from   = ref(props.filters.from   ?? '')
const to     = ref(props.filters.to     ?? '')

const statusTabs = [
  { key: '',           label: 'Todas'     },
  { key: 'completed',  label: 'Pagadas'   },
  { key: 'cancelled',  label: 'Canceladas'},
]

function apply() {
  router.get(route('sales.index'), {
    q:      q.value      || undefined,
    status: status.value || undefined,
    from:   from.value   || undefined,
    to:     to.value     || undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  q.value = ''; status.value = ''; from.value = ''; to.value = ''
  router.get(route('sales.index'), {}, { preserveState: false, replace: true })
}

let searchTimer = null
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(apply, 350)
}

const hasFilters = computed(() =>
  !!q.value || !!status.value || !!from.value || !!to.value
)

// ── Helpers ──────────────────────────────────────────────────────────────────
function money(v)   { return Number(v ?? 0).toFixed(2) }
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
<\/script>

<template>
  <Head title="Ventas" />

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Ventas</h1>
      <p class="text-sm text-gray-500 mt-0.5">Historial y consulta</p>
    </div>

    <!-- ── Quick stats ────────────────────────────────────────────────── -->
    <div class="grid grid-cols-3 gap-3 sm:gap-4">
      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100 text-center">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1"># Ventas hoy</p>
        <p class="text-2xl font-bold text-gray-900">{{ quickStats.today_count ?? 0 }}</p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100 text-center">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total hoy</p>
        <p class="text-xl font-bold text-emerald-600 sm:text-2xl">\${{ money(quickStats.today_total ?? 0) }}</p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100 text-center">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Canceladas hoy</p>
        <p class="text-2xl font-bold" :class="(quickStats.today_cancelled ?? 0) > 0 ? 'text-red-600' : 'text-gray-400'">
          {{ quickStats.today_cancelled ?? 0 }}
        </p>
      </div>
    </div>

    <!-- ── Filters card ───────────────────────────────────────────────── -->
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4">

      <!-- Status tabs -->
      <div class="flex gap-0.5 border-b border-gray-200">
        <button
          v-for="tab in statusTabs" :key="tab.key"
          @click="status = tab.key; apply()"
          class="px-3 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
          :class="status === tab.key
            ? 'border-gray-900 text-gray-900'
            : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Inputs row -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Search -->
        <div class="relative lg:col-span-2">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
          </svg>
          <input v-model="q" @input="onSearch" type="text"
            placeholder="Buscar por folio, cliente o teléfono..."
            class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
        </div>

        <!-- From -->
        <div>
          <label class="block text-xs text-gray-500 mb-1 font-medium">Desde</label>
          <input v-model="from" @change="apply" type="date"
            class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm text-gray-700 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
        </div>

        <!-- To -->
        <div>
          <label class="block text-xs text-gray-500 mb-1 font-medium">Hasta</label>
          <input v-model="to" @change="apply" type="date"
            class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm text-gray-700 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
        </div>
      </div>

      <!-- Clear filters -->
      <div class="flex justify-end">
        <button v-if="hasFilters" @click="clearFilters"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
          Limpiar filtros
        </button>
      </div>
    </div>

    <!-- ── Table ──────────────────────────────────────────────────────── -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">

      <!-- Desktop table -->
      <table class="min-w-full divide-y divide-gray-100 hidden sm:table">
        <thead class="bg-gray-50">
          <tr>
            <th class="py-3 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Folio</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha / Hora</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Cliente</th>
            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Método pago</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
            <th class="py-3 pl-3 pr-5 text-right" />
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <!-- Empty state -->
          <tr v-if="sales.data.length === 0">
            <td colspan="7" class="py-16 text-center">
              <svg class="mx-auto h-10 w-10 text-gray-200 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.836l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.93-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.273M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
              </svg>
              <p class="text-sm text-gray-400">No hay ventas con estos filtros</p>
            </td>
          </tr>
          <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-gray-50 transition">
            <td class="py-3 pl-5 pr-3">
              <span class="font-mono text-sm font-bold text-gray-900">#{{ sale.id }}</span>
            </td>
            <td class="px-3 py-3 text-sm text-gray-500 whitespace-nowrap">{{ fmtDate(sale.created_at) }}</td>
            <td class="px-3 py-3">
              <p v-if="sale.customer" class="text-sm font-medium text-gray-800">{{ sale.customer.name }}</p>
              <p v-else class="text-sm text-gray-400 italic">Sin cliente</p>
            </td>
            <td class="px-3 py-3 text-right">
              <span class="text-sm font-bold text-gray-900">\${{ money(sale.total) }}</span>
            </td>
            <td class="px-3 py-3">
              <PaymentsList :payments="sale.payments ?? []" compact />
            </td>
            <td class="px-3 py-3 text-center">
              <SaleStatusBadge :status="sale.status" />
            </td>
            <td class="py-3 pl-3 pr-5 text-right">
              <Link :href="route('sales.show', sale.id)"
                class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 transition">
                Ver
              </Link>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Mobile cards -->
      <ul class="sm:hidden divide-y divide-gray-100">
        <li v-if="sales.data.length === 0" class="py-12 text-center">
          <p class="text-sm text-gray-400">No hay ventas con estos filtros</p>
        </li>
        <li v-for="sale in sales.data" :key="sale.id" class="p-4 space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="font-mono font-bold text-gray-900">#{{ sale.id }}</span>
              <p class="text-xs text-gray-400 mt-0.5">{{ fmtDate(sale.created_at) }}</p>
            </div>
            <SaleStatusBadge :status="sale.status" />
          </div>
          <p class="text-sm text-gray-700">{{ sale.customer?.name ?? 'Sin cliente' }}</p>
          <div class="flex items-center justify-between pt-1">
            <PaymentsList :payments="sale.payments ?? []" compact />
            <span class="text-base font-bold text-gray-900">\${{ money(sale.total) }}</span>
          </div>
          <div class="text-right">
            <Link :href="route('sales.show', sale.id)"
              class="text-xs font-medium text-gray-600 hover:text-gray-900 underline">Ver detalle</Link>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="sales.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">
          Mostrando {{ sales.from }}–{{ sales.to }} de {{ sales.total }} ventas
        </p>
        <div class="flex flex-wrap gap-1">
          <Link v-for="link in sales.links" :key="link.label" :href="link.url ?? '#'"
            :class="[
              'px-2.5 py-1 rounded text-xs font-medium transition',
              link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100',
              !link.url  ? 'opacity-40 pointer-events-none' : '',
            ]"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </div>
</template>
`

// ─── Sales/Show.vue ────────────────────────────────────────────────────────────
const salesShow = `<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import SaleStatusBadge from '@/Components/Sales/SaleStatusBadge.vue'
import PaymentsList    from '@/Components/Sales/PaymentsList.vue'

const props = defineProps({
  sale: { type: Object, required: true },
  can:  { type: Object, default: () => ({}) },
})

const flash  = computed(() => usePage().props.flash ?? {})
const isPaid = computed(() => props.sale.status === 'completed')

// ── Cancel modal ─────────────────────────────────────────────────────────────
const showCancelModal = ref(false)
const cancelForm = useForm({ reason: '' })

function submitCancel() {
  cancelForm.post(route('sales.cancel', props.sale.id), {
    onSuccess: () => { showCancelModal.value = false; cancelForm.reset() }
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function money(v) { return Number(v ?? 0).toFixed(2) }
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
function thumbUrl(product) {
  const img = product?.images?.[0]
  return img ? '/storage/' + img.path : null
}

// ── Totals ────────────────────────────────────────────────────────────────────
const hasItemDiscount  = computed(() => props.sale.items?.some(i => Number(i.discount_amount) > 0))
const hasCoupon        = computed(() => !!props.sale.coupon_code)
const hasLoyalty       = computed(() => props.sale.loyalty_applied && Number(props.sale.loyalty_discount_total) > 0)
const totalDiscounts   = computed(() =>
  Number(props.sale.discount_total ?? 0) +
  Number(props.sale.coupon_discount_total ?? 0) +
  Number(props.sale.loyalty_discount_total ?? 0)
)
<\/script>

<template>
  <Head :title="'Venta #' + sale.id" />

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- ── Header ───────────────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <Link :href="route('sales.index')" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </Link>
        <div>
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900 font-mono">Venta #{{ sale.id }}</h1>
            <SaleStatusBadge :status="sale.status" size="md" />
          </div>
          <p class="text-sm text-gray-500 mt-0.5">{{ fmtDate(sale.created_at) }}</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Print (placeholder) -->
        <button
          class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition"
          title="Próximamente"
        >
          <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.056 48.056 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
          </svg>
          Imprimir ticket
        </button>

        <!-- Cancel -->
        <button v-if="can.cancel && isPaid"
          @click="showCancelModal = true"
          class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
          Cancelar venta
        </button>
      </div>
    </div>

    <!-- ── Flash ─────────────────────────────────────────────────────────────── -->
    <div v-if="flash.success"
      class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
      <svg class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
      </svg>
      {{ flash.success }}
    </div>

    <!-- ── Cancellation reason (if cancelled) ───────────────────────────────── -->
    <div v-if="sale.status === 'cancelled' && sale.cancellation_reason"
      class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
      <span class="font-semibold">Motivo de cancelación:</span> {{ sale.cancellation_reason }}
    </div>

    <!-- ── Meta info row ─────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Cajero -->
      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Cajero</p>
        <div class="flex items-center gap-2 mt-1">
          <div class="h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <span class="text-xs font-bold text-gray-600">{{ sale.creator?.name?.charAt(0).toUpperCase() ?? '?' }}</span>
          </div>
          <p class="text-sm font-semibold text-gray-800">{{ sale.creator?.name ?? '—' }}</p>
        </div>
      </div>

      <!-- Cliente -->
      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Cliente</p>
        <div v-if="sale.customer" class="flex items-center gap-2 mt-1">
          <div class="h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <span class="text-xs font-bold text-gray-600">{{ sale.customer.name.charAt(0).toUpperCase() }}</span>
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-800">{{ sale.customer.name }}</p>
            <p v-if="sale.customer.phone" class="text-xs text-gray-400 font-mono">{{ sale.customer.phone }}</p>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 italic mt-1">Sin cliente</p>
      </div>

      <!-- Total destacado -->
      <div class="rounded-xl bg-gray-900 p-4 shadow-sm text-white">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Total de la venta</p>
        <p class="text-3xl font-bold mt-1">\${{ money(sale.total) }}</p>
        <p v-if="totalDiscounts > 0" class="text-xs text-gray-400 mt-1">
          Ahorro total: \${{ money(totalDiscounts) }}
        </p>
      </div>
    </div>

    <!-- ── Main content grid ─────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left: Products + Payments -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Products table -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">
              Productos
              <span class="ml-1 text-gray-400 font-normal">({{ sale.items?.length ?? 0 }})</span>
            </h2>
          </div>

          <table class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="py-2.5 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Precio</th>
                <th v-if="hasItemDiscount" class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Descuento</th>
                <th class="py-2.5 pl-3 pr-5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total línea</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="item in sale.items" :key="item.id" class="group">
                <!-- Producto -->
                <td class="py-3 pl-5 pr-3">
                  <div class="flex items-center gap-3">
                    <div class="h-10 w-10 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                      <img v-if="thumbUrl(item.product)"
                        :src="thumbUrl(item.product)" :alt="item.name"
                        class="h-full w-full object-cover" />
                      <div v-else class="h-full w-full flex items-center justify-center text-gray-300">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                      </div>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-gray-800 truncate">{{ item.name }}</p>
                      <div class="flex flex-wrap gap-1 mt-0.5">
                        <span v-if="item.sku" class="text-xs text-gray-400 font-mono">{{ item.sku }}</span>
                        <span v-if="item.product?.size" class="text-xs text-gray-400">· {{ item.product.size.name }}</span>
                        <span v-if="item.product?.color" class="text-xs text-gray-400">/ {{ item.product.color.name }}</span>
                      </div>
                    </div>
                  </div>
                </td>
                <!-- Precio unitario -->
                <td class="px-3 py-3 text-right text-sm text-gray-700">
                  \${{ money(item.unit_price) }}
                  <span v-if="item.quantity > 1" class="block text-xs text-gray-400">× {{ item.quantity }}</span>
                </td>
                <!-- Descuento línea -->
                <td v-if="hasItemDiscount" class="px-3 py-3 text-right text-sm">
                  <span v-if="Number(item.discount_amount) > 0" class="text-red-500">
                    − \${{ money(item.discount_amount) }}
                  </span>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <!-- Total línea -->
                <td class="py-3 pl-3 pr-5 text-right text-sm font-bold text-gray-900">
                  \${{ money(item.line_total) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Payments -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700">Pagos registrados</h2>
          <PaymentsList :payments="sale.payments ?? []" />
        </div>
      </div>

      <!-- Right: Totals -->
      <div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3 sticky top-6">
          <h2 class="text-sm font-semibold text-gray-700 pb-2 border-b border-gray-100">Resumen de totales</h2>

          <!-- Subtotal -->
          <div class="flex justify-between items-center text-sm">
            <span class="text-gray-500">Subtotal</span>
            <span class="font-medium text-gray-900">\${{ money(sale.subtotal) }}</span>
          </div>

          <!-- Descuento manual -->
          <div v-if="Number(sale.discount_total) > 0"
            class="flex justify-between items-center text-sm">
            <span class="text-gray-500">
              Descuento manual
              <span v-if="sale.global_discount_type === 'percent'" class="text-xs text-gray-400">
                ({{ sale.global_discount_value }}%)
              </span>
            </span>
            <span class="text-red-500 font-medium">− \${{ money(sale.discount_total) }}</span>
          </div>

          <!-- Cupón -->
          <div v-if="hasCoupon && Number(sale.coupon_discount_total) > 0"
            class="flex justify-between items-center text-sm">
            <span class="text-gray-500 flex items-center gap-1">
              Cupón
              <span class="font-mono text-xs bg-amber-50 text-amber-700 rounded px-1.5 py-0.5 ring-1 ring-amber-200">
                {{ sale.coupon_code }}
              </span>
            </span>
            <span class="text-red-500 font-medium">− \${{ money(sale.coupon_discount_total) }}</span>
          </div>

          <!-- Fidelidad -->
          <div v-if="hasLoyalty"
            class="flex justify-between items-center text-sm">
            <span class="text-gray-500 flex items-center gap-1">
              Descuento fidelidad
              <span class="text-xs bg-purple-50 text-purple-700 rounded px-1.5 py-0.5 ring-1 ring-purple-200 font-medium">VIP</span>
            </span>
            <span class="text-red-500 font-medium">− \${{ money(sale.loyalty_discount_total) }}</span>
          </div>

          <!-- Divider + Total -->
          <div class="border-t border-gray-200 pt-3 mt-1">
            <div class="flex justify-between items-center">
              <span class="text-base font-bold text-gray-900">TOTAL</span>
              <span class="text-2xl font-bold text-gray-900">\${{ money(sale.total) }}</span>
            </div>
            <p v-if="totalDiscounts > 0" class="text-right text-xs text-emerald-600 mt-1">
              Ahorro total: \${{ money(totalDiscounts) }}
            </p>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Cancel modal ──────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showCancelModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-5">
        <!-- Modal header -->
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
            <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-900">Cancelar venta #{{ sale.id }}</h3>
            <p class="text-sm text-gray-500 mt-0.5">Esta acción es irreversible. La venta quedará marcada como cancelada.</p>
          </div>
        </div>

        <!-- Reason textarea -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Motivo de cancelación
            <span class="text-gray-400 font-normal">(opcional)</span>
          </label>
          <textarea v-model="cancelForm.reason" rows="3"
            placeholder="Ej. Devolución por talla incorrecta..."
            class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm text-gray-800 placeholder-gray-400 resize-none focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          <p v-if="cancelForm.errors.reason" class="mt-1 text-xs text-red-600">{{ cancelForm.errors.reason }}</p>
        </div>

        <!-- Buttons -->
        <div class="flex gap-3 justify-end pt-1">
          <button @click="showCancelModal = false; cancelForm.reset()"
            class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            Volver
          </button>
          <button @click="submitCancel" :disabled="cancelForm.processing"
            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition disabled:opacity-50">
            {{ cancelForm.processing ? 'Cancelando...' : 'Confirmar cancelación' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
`

const files = [
  ['Components/Sales/SaleStatusBadge.vue', saleStatusBadge],
  ['Components/Sales/PaymentsList.vue',    paymentsList],
  ['Pages/Sales/Index.vue',               salesIndex],
  ['Pages/Sales/Show.vue',                salesShow],
]

files.forEach(([rel, content]) => {
  const fullPath = join(base, rel)
  mkdirSync(dirname(fullPath), { recursive: true })
  writeFileSync(fullPath, content, 'utf-8')
  console.log('OK', rel)
})
