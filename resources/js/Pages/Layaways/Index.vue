<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  layaways: { type: Object, required: true },
  filters:  { type: Object, required: true },
  can:      { type: Object, default: () => ({}) },
})

const search = ref(props.filters.q ?? '')
const activeStatus = ref(props.filters.status ?? 'open')
const activeVigencia = ref(props.filters.vigencia ?? 'all')

const tabs = [
  { key: 'open',       label: 'Activos' },
  { key: 'liquidated', label: 'Liquidados' },
  { key: 'cancelled',  label: 'Cancelados' },
]

const badgeClass = {
  open:       'bg-amber-100 text-amber-800 ring-amber-200',
  liquidated: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
  cancelled:  'bg-rose-100 text-rose-700 ring-rose-200',
}
const statusLabel = {
  open:       'Activo',
  liquidated: 'Liquidado',
  cancelled:  'Cancelado',
}

const rowMenuId = ref(null)

const paginationMeta = computed(() => ({
  from: props.layaways?.from ?? 0,
  to: props.layaways?.to ?? 0,
  total: props.layaways?.total ?? 0,
  lastPage: props.layaways?.last_page ?? 1,
}))

const pagination = computed(() => {
  const links = props.layaways?.links ?? []
  if (links.length < 3) {
    return { prev: null, pages: [], next: null }
  }

  return {
    prev: links[0],
    pages: links.slice(1, -1),
    next: links[links.length - 1],
  }
})

function money(v) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 2,
  }).format(Number(v ?? 0))
}

function applyFilters() {
  router.get(route('layaways.index'), {
    status: activeStatus.value,
    vigencia: activeVigencia.value,
    q: search.value || undefined,
  }, { preserveState: true, replace: true })
}

function setTab(key) {
  activeStatus.value = key
  rowMenuId.value = null
  applyFilters()
}

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 350)
}

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
})

function toggleRowMenu(id) {
  rowMenuId.value = rowMenuId.value === id ? null : id
}

function closeRowMenu() {
  rowMenuId.value = null
}

function pageLabel(label) {
  return String(label ?? '').replace(/<[^>]*>/g, '').trim()
}

function copyFolio(id) {
  const folio = `#${String(id ?? '').padStart(6, '0')}`
  if (navigator?.clipboard?.writeText) {
    navigator.clipboard.writeText(folio).catch(() => {})
  }
  closeRowMenu()
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

function folio(id) {
  return String(id ?? '').padStart(6, '0')
}

function vigenciaLabel(status) {
  if (status === 'vigente') return 'Vigente'
  if (status === 'vence_hoy') return 'Vence hoy'
  if (status === 'vencido') return 'Vencido'
  return 'Sin vigencia'
}

function vigenciaBadgeClass(status) {
  if (status === 'vigente') return 'bg-emerald-100 text-emerald-700 ring-emerald-200'
  if (status === 'vence_hoy') return 'bg-amber-100 text-amber-800 ring-amber-200'
  if (status === 'vencido') return 'bg-rose-100 text-rose-700 ring-rose-200'
  return 'bg-slate-100 text-slate-600 ring-slate-200'
}

function rowClass(layaway) {
  if (layaway.status === 'open' && layaway.estado_vigencia === 'vencido') {
    return 'bg-rose-50/50 hover:bg-rose-50/80'
  }

  return 'hover:bg-slate-50/80'
}
</script>

<template>
  <Head title="Apartados" />

  <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Apartados</h1>
        <p class="mt-0.5 text-sm text-slate-500">Gestión de apartados de productos</p>
      </div>
      <Link
        v-if="can.create"
        :href="route('layaways.create')"
        class="inline-flex h-9 items-center gap-2 rounded-lg bg-slate-900 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo apartado
      </Link>
    </div>

    <!-- Filters card -->
    <div class="rounded-2xl border border-slate-200 bg-white px-3.5 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:px-4">
      <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <!-- Tabs -->
        <div class="inline-flex w-full rounded-xl bg-slate-100 p-1 lg:w-auto">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="setTab(tab.key)"
            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold tracking-wide transition sm:text-sm"
            :class="activeStatus === tab.key
              ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
              : 'text-slate-500 hover:text-slate-700'"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Search -->
        <div class="flex w-full flex-col gap-2 lg:max-w-2xl lg:flex-row lg:items-center lg:justify-end">
          <select
            v-model="activeVigencia"
            @change="applyFilters"
            class="h-9 rounded-lg border border-slate-200 px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
          >
            <option value="all">Todas las vigencias</option>
            <option value="expired">Solo vencidos</option>
            <option value="upcoming">Proximos a vencer (7 dias)</option>
          </select>

          <div class="relative w-full lg:max-w-sm">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
          </svg>
          <input
            v-model="search"
            @input="onSearchInput"
            type="text"
            placeholder="Buscar por folio o cliente"
            class="h-9 w-full rounded-lg border border-slate-200 py-1.5 pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
          />
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <!-- Desktop table -->
      <table class="hidden min-w-full divide-y divide-slate-200 md:table">
        <thead class="bg-slate-50/80">
          <tr>
            <th class="py-2.5 pl-4 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Folio</th>
            <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</th>
            <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
            <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Abonado</th>
            <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo</th>
            <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
            <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Vigencia</th>
            <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Vence el</th>
            <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Estado vigencia</th>
            <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Estado apartado</th>
            <th class="py-2.5 pl-2 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="layaways.data.length === 0">
            <td colspan="11" class="px-5 py-10 text-center text-sm text-slate-400">Sin apartados para mostrar</td>
          </tr>
          <tr v-for="l in layaways.data" :key="l.id" class="transition-colors duration-150" :class="rowClass(l)">
            <td class="py-2.5 pl-4 pr-2">
              <span class="font-mono text-sm font-bold text-slate-900">#{{ folio(l.id) }}</span>
            </td>
            <td class="px-2 py-2.5 text-sm font-medium text-slate-700">{{ l.customer?.name ?? '—' }}</td>
            <td class="px-2 py-2.5 text-right">
              <span class="text-sm font-semibold text-slate-900">{{ money(l.subtotal) }}</span>
            </td>
            <td class="px-2 py-2.5 text-right">
              <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-sm font-semibold text-emerald-700">{{ money(l.paid_total) }}</span>
            </td>
            <td class="px-2 py-2.5 text-right">
              <span
                class="rounded-md px-1.5 py-0.5 text-sm font-semibold"
                :class="Number(l.balance) > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ money(l.balance) }}
              </span>
            </td>
            <td class="px-2 py-2.5 text-sm text-slate-500">{{ fmtDate(l.created_at) }}</td>
            <td class="px-2 py-2.5 text-center text-sm text-slate-600">{{ l.vigencia_dias ? `${l.vigencia_dias} dias` : '—' }}</td>
            <td class="px-2 py-2.5 text-sm text-slate-500">{{ fmtDate(l.fecha_vencimiento) }}</td>
            <td class="px-2 py-2.5 text-center">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                :class="vigenciaBadgeClass(l.estado_vigencia)">{{ vigenciaLabel(l.estado_vigencia) }}</span>
            </td>
            <td class="px-2 py-2.5 text-center">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                :class="badgeClass[l.status]">{{ statusLabel[l.status] }}</span>
            </td>
            <td class="py-2.5 pl-2 pr-4">
              <div class="flex items-center justify-end gap-1.5">
                <Link
                  :href="route('layaways.show', l.id)"
                  class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                  Ver detalle
                </Link>
                <div class="relative">
                  <button
                    type="button"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                    title="Mas acciones"
                    @click="toggleRowMenu(l.id)"
                  >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 5h.01M12 12h.01M12 19h.01" />
                    </svg>
                  </button>

                  <div
                    v-if="rowMenuId === l.id"
                    class="absolute right-0 z-20 mt-1.5 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                  >
                    <Link
                      :href="route('layaways.show', l.id)"
                      class="block px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                      @click="closeRowMenu"
                    >
                      Abrir detalle
                    </Link>
                    <button
                      type="button"
                      class="block w-full px-3 py-1.5 text-left text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                      @click="copyFolio(l.id)"
                    >
                      Copiar folio
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Mobile cards -->
      <ul class="divide-y divide-slate-100 md:hidden">
        <li v-if="layaways.data.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
          Sin apartados para mostrar
        </li>
        <li v-for="l in layaways.data" :key="l.id" class="space-y-2.5 px-4 py-3"
          :class="l.status === 'open' && l.estado_vigencia === 'vencido' ? 'bg-rose-50/60' : ''">
          <div class="flex items-center justify-between">
            <span class="font-mono text-sm font-bold text-slate-900">#{{ folio(l.id) }}</span>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
              :class="badgeClass[l.status]">{{ statusLabel[l.status] }}</span>
          </div>
          <p class="text-sm font-medium text-slate-700">{{ l.customer?.name ?? '— Sin cliente' }}</p>
          <div class="grid grid-cols-3 gap-1 text-[11px] text-slate-500">
            <span>Total: <strong class="text-slate-900">{{ money(l.subtotal) }}</strong></span>
            <span>Abonado: <strong class="text-emerald-700">{{ money(l.paid_total) }}</strong></span>
            <span>Saldo: <strong :class="Number(l.balance) > 0 ? 'text-amber-700' : 'text-slate-500'">{{ money(l.balance) }}</strong></span>
          </div>
          <div class="grid grid-cols-1 gap-1 text-[11px] text-slate-500">
            <span>Vigencia: <strong class="text-slate-800">{{ l.vigencia_dias ? `${l.vigencia_dias} dias` : '—' }}</strong></span>
            <span>Vence el: <strong class="text-slate-800">{{ fmtDate(l.fecha_vencimiento) }}</strong></span>
            <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="vigenciaBadgeClass(l.estado_vigencia)">
              {{ vigenciaLabel(l.estado_vigencia) }}
            </span>
          </div>
          <div class="flex items-center justify-end gap-2">
            <Link
              :href="route('layaways.show', l.id)"
              class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              Ver detalle
            </Link>
            <button
              type="button"
              class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 transition hover:bg-slate-50"
              title="Copiar folio"
              @click="copyFolio(l.id)"
            >
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 8h10v10H8z" />
                <path d="M6 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" />
              </svg>
            </button>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
      <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5">
        <p class="text-xs text-slate-500">
          Mostrando {{ paginationMeta.from }} a {{ paginationMeta.to }} de {{ paginationMeta.total }} apartados
        </p>

        <nav
          v-if="paginationMeta.lastPage > 1"
          class="flex items-center gap-1"
          aria-label="Paginacion de apartados"
        >
          <Link
            :href="pagination.prev?.url ?? '#'"
            class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
            :class="!pagination.prev?.url ? 'pointer-events-none opacity-40' : ''"
          >
            Anterior
          </Link>

          <Link
            v-for="link in pagination.pages"
            :key="link.label"
            :href="link.url ?? '#'"
            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-2 text-xs font-semibold transition"
            :class="[
              link.active
                ? 'border-slate-900 bg-slate-900 text-white'
                : 'border-slate-200 text-slate-600 hover:bg-slate-50',
              !link.url ? 'pointer-events-none opacity-40' : '',
            ]"
          >
            {{ pageLabel(link.label) }}
          </Link>

          <Link
            :href="pagination.next?.url ?? '#'"
            class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
            :class="!pagination.next?.url ? 'pointer-events-none opacity-40' : ''"
          >
            Siguiente
          </Link>
        </nav>
      </div>
    </div>
  </div>
</template>
