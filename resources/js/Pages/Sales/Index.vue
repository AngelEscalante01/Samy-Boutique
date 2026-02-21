<script setup>
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
</script>

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
        <p class="text-xl font-bold text-emerald-600 sm:text-2xl">${{ money(quickStats.today_total ?? 0) }}</p>
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
              <span class="text-sm font-bold text-gray-900">${{ money(sale.total) }}</span>
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
            <span class="text-base font-bold text-gray-900">${{ money(sale.total) }}</span>
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
