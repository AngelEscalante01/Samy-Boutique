<script setup>
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
</script>

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
            <td class="px-3 py-3 text-sm text-right text-gray-900 font-medium">${{ money(l.subtotal) }}</td>
            <td class="px-3 py-3 text-sm text-right text-emerald-600 font-medium">${{ money(l.paid_total) }}</td>
            <td class="px-3 py-3 text-sm text-right font-semibold"
              :class="Number(l.balance) > 0 ? 'text-amber-600' : 'text-gray-500'">${{ money(l.balance) }}</td>
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
            <span>Total: <strong class="text-gray-900">${{ money(l.subtotal) }}</strong></span>
            <span>Abonado: <strong class="text-emerald-600">${{ money(l.paid_total) }}</strong></span>
            <span>Saldo: <strong :class="Number(l.balance) > 0 ? 'text-amber-600' : 'text-gray-500'">${{ money(l.balance) }}</strong></span>
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
