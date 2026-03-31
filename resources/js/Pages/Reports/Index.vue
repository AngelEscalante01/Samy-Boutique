<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  salesSummary: { type: Object, default: () => ({}) },
  dailySummary: { type: Array, default: () => [] },
  topCategories: { type: Array, default: () => [] },
  topProducts: { type: Array, default: () => [] },
  topCustomers: { type: Array, default: () => [] },
  nearLoyaltyCustomers: { type: Array, default: () => [] },
})

const tabs = [
  { key: 'sales', label: 'Ventas' },
  { key: 'products', label: 'Productos' },
  { key: 'customers', label: 'Clientes' },
]

const activeTab = ref('sales')

const localFrom = ref(props.filters.from ?? '')
const localTo = ref(props.filters.to ?? '')
const applying = ref(false)

const chartRows = computed(() => props.dailySummary.slice(-31))

const chartMax = computed(() => {
  const values = chartRows.value.flatMap((row) => [
    Number(row.total_sold ?? 0),
    Number(row.profit_total ?? 0),
  ])

  return Math.max(1, ...values)
})

const hasRange = computed(() => !!(props.filters.from && props.filters.to))

const kpis = computed(() => [
  {
    key: 'total_sold',
    label: 'Total vendido',
    value: money(props.salesSummary.total_sold),
    sub: `${Number(props.salesSummary.sales_count ?? 0).toLocaleString('es-MX')} ventas`,
    color: 'text-emerald-700',
    bg: 'bg-emerald-50 ring-emerald-100',
    compact: false,
  },
  {
    key: 'profit_total',
    label: 'Ganancia total',
    value: money(props.salesSummary.profit_total),
    sub: 'Margen real del periodo',
    color: 'text-emerald-700',
    bg: 'bg-emerald-100 ring-emerald-200',
    compact: false,
    highlight: true,
  },
  {
    key: 'avg_ticket',
    label: 'Ticket promedio',
    value: money(props.salesSummary.avg_ticket),
    sub: 'Promedio por venta',
    color: 'text-sky-700',
    bg: 'bg-sky-50 ring-sky-100',
    compact: true,
  },
  {
    key: 'manual_discount_total',
    label: 'Descuentos manuales',
    value: money(props.salesSummary.manual_discount_total),
    sub: 'Aplicados en caja',
    color: 'text-amber-700',
    bg: 'bg-amber-50 ring-amber-100',
    compact: true,
  },
  {
    key: 'coupon_discount_total',
    label: 'Descuentos cupones',
    value: money(props.salesSummary.coupon_discount_total),
    sub: 'Promociones por codigo',
    color: 'text-violet-700',
    bg: 'bg-violet-50 ring-violet-100',
    compact: true,
  },
  {
    key: 'loyalty_discount_total',
    label: 'Descuentos fidelidad',
    value: money(props.salesSummary.loyalty_discount_total),
    sub: 'Beneficios a clientes',
    color: 'text-indigo-700',
    bg: 'bg-indigo-50 ring-indigo-100',
    compact: true,
  },
  {
    key: 'canceled_count',
    label: 'Canceladas',
    value: Number(props.salesSummary.canceled_count ?? 0).toLocaleString('es-MX'),
    sub: 'Ventas anuladas',
    color: 'text-rose-700',
    bg: 'bg-rose-50 ring-rose-100',
    compact: true,
  },
])

function money(value) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function shortMoney(value) {
  return Number(value ?? 0).toLocaleString('es-MX', {
    maximumFractionDigits: 0,
  })
}

function fmtDate(dateString) {
  if (!dateString) return '—'

  return new Date(`${dateString}T00:00:00`).toLocaleDateString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function barHeight(value) {
  const amount = Number(value ?? 0)
  const ratio = (amount / chartMax.value) * 100
  return `${Math.max(4, Math.round(ratio))}%`
}

function applyFilters() {
  applying.value = true

  router.get(route('reports.index'), {
    from: localFrom.value,
    to: localTo.value,
  }, {
    preserveState: false,
    preserveScroll: false,
    onFinish: () => {
      applying.value = false
    },
  })
}

function quickToday() {
  const today = new Date().toISOString().slice(0, 10)
  localFrom.value = today
  localTo.value = today
  applyFilters()
}

function quickMonth() {
  const now = new Date()
  const first = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)

  localFrom.value = first
  localTo.value = last
  applyFilters()
}

function clearFilters() {
  const now = new Date()
  localFrom.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  localTo.value = now.toISOString().slice(0, 10)
  applyFilters()
}

function exportPdf() {
  window.alert('Exportacion PDF disponible proximamente.')
}

function exportExcel() {
  window.alert('Exportacion Excel disponible proximamente.')
}
</script>

<template>
  <Head title="Reportes" />

  <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reportes</h1>
        <p class="mt-0.5 text-sm text-slate-500">Analiza ventas, descuentos, ganancias y rendimiento</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="exportPdf"
        >
          PDF
        </button>
        <button
          type="button"
          class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="exportExcel"
        >
          Excel
        </button>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-3.5 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:px-4">
      <div class="flex flex-wrap items-end gap-2">
        <div class="flex min-w-[170px] flex-col gap-1">
          <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Desde</label>
          <input
            v-model="localFrom"
            type="date"
            class="h-9 rounded-lg border border-slate-200 px-2.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
          >
        </div>

        <div class="flex min-w-[170px] flex-col gap-1">
          <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Hasta</label>
          <input
            v-model="localTo"
            type="date"
            class="h-9 rounded-lg border border-slate-200 px-2.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
          >
        </div>

        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="quickToday"
        >
          Hoy
        </button>
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="quickMonth"
        >
          Este mes
        </button>
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="clearFilters"
        >
          Limpiar
        </button>

        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3.5 text-xs font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="applying"
          @click="applyFilters"
        >
          {{ applying ? 'Aplicando...' : 'Aplicar' }}
        </button>

        <span v-if="hasRange" class="ml-auto rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
          Rango activo: {{ filters.from }} - {{ filters.to }}
        </span>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div class="inline-flex w-full rounded-xl bg-slate-100 p-1 sm:w-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold tracking-wide transition sm:text-sm"
          :class="activeTab === tab.key
            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
            : 'text-slate-500 hover:text-slate-700'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </section>

    <template v-if="activeTab === 'sales'">
      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="kpi in kpis"
          :key="kpi.key"
          class="rounded-xl border border-slate-200 bg-white px-3.5 py-3 ring-1"
          :class="[kpi.bg, kpi.highlight ? 'xl:col-span-2' : '']"
        >
          <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ kpi.label }}</p>
          <p class="mt-1.5 text-xl font-bold tracking-tight" :class="kpi.color">{{ kpi.value }}</p>
          <p class="mt-1 text-xs text-slate-500">{{ kpi.sub }}</p>
        </article>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-800">Ventas y ganancias por dia</h3>
          <div class="flex items-center gap-3 text-[11px] text-slate-500">
            <span class="inline-flex items-center gap-1">
              <span class="h-2 w-2 rounded-full bg-emerald-500" /> Ventas
            </span>
            <span class="inline-flex items-center gap-1">
              <span class="h-2 w-2 rounded-full bg-sky-500" /> Ganancias
            </span>
          </div>
        </div>

        <div v-if="chartRows.length" class="overflow-x-auto pb-1">
          <div class="flex min-w-max items-end gap-1" style="height: 180px">
            <div
              v-for="row in chartRows"
              :key="row.date"
              class="group flex w-8 flex-col items-center"
            >
              <div class="relative flex h-36 w-full items-end gap-0.5">
                <div
                  class="w-1/2 rounded-t-sm bg-emerald-500 transition-opacity group-hover:opacity-85"
                  :style="{ height: barHeight(row.total_sold) }"
                  :title="`${fmtDate(row.date)} Ventas: ${money(row.total_sold)}`"
                />
                <div
                  class="w-1/2 rounded-t-sm bg-sky-500 transition-opacity group-hover:opacity-85"
                  :style="{ height: barHeight(row.profit_total) }"
                  :title="`${fmtDate(row.date)} Ganancia: ${money(row.profit_total)}`"
                />
              </div>
              <p class="mt-1 w-full truncate text-center text-[10px] text-slate-400">{{ fmtDate(row.date) }}</p>
            </div>
          </div>
        </div>
        <div v-else class="flex h-28 items-center justify-center text-sm text-slate-400">
          Sin informacion para el rango seleccionado.
        </div>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50/80">
              <tr>
                <th class="py-2.5 pl-4 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
                <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ventas</th>
                <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total vendido</th>
                <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ganancia</th>
                <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Descuentos</th>
                <th class="py-2.5 pl-2 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Canceladas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="dailySummary.length === 0">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">Sin datos para el rango seleccionado.</td>
              </tr>

              <tr v-for="row in dailySummary" :key="row.date" class="transition-colors duration-150 hover:bg-slate-50/80">
                <td class="py-2.5 pl-4 pr-2 text-sm font-medium text-slate-800">{{ fmtDate(row.date) }}</td>
                <td class="px-2 py-2.5 text-right text-sm text-slate-600">{{ row.sales_count }}</td>
                <td class="px-2 py-2.5 text-right text-sm font-semibold text-emerald-700">{{ money(row.total_sold) }}</td>
                <td class="px-2 py-2.5 text-right text-sm font-semibold text-sky-700">{{ money(row.profit_total) }}</td>
                <td class="px-2 py-2.5 text-right text-sm font-semibold text-violet-700">{{ money(row.discount_total) }}</td>
                <td class="py-2.5 pl-2 pr-4 text-right text-sm font-semibold" :class="Number(row.canceled_count) > 0 ? 'text-rose-600' : 'text-slate-500'">
                  {{ row.canceled_count }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <template v-if="activeTab === 'products'">
      <section class="grid grid-cols-1 gap-3 xl:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
          <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-700">Top productos</div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
              <thead>
                <tr class="text-xs uppercase tracking-wide text-slate-500">
                  <th class="py-2.5 pl-4 pr-2 text-left">Producto</th>
                  <th class="px-2 py-2.5 text-right">Uds.</th>
                  <th class="py-2.5 pl-2 pr-4 text-right">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="topProducts.length === 0">
                  <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">Sin datos en el rango.</td>
                </tr>
                <tr v-for="item in topProducts" :key="`${item.sku}-${item.name}`" class="hover:bg-slate-50/70">
                  <td class="py-2.5 pl-4 pr-2 text-sm font-medium text-slate-800">{{ item.name }}</td>
                  <td class="px-2 py-2.5 text-right text-sm text-slate-600">{{ item.qty }}</td>
                  <td class="py-2.5 pl-2 pr-4 text-right text-sm font-semibold text-emerald-700">{{ money(item.total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
          <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-700">Top categorias</div>
          <ul class="divide-y divide-slate-100">
            <li v-if="topCategories.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">Sin datos en el rango.</li>
            <li v-for="item in topCategories" :key="item.name" class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-slate-50/70">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ item.name }}</p>
                <p class="text-xs text-slate-500">{{ item.qty }} uds.</p>
              </div>
              <p class="text-sm font-semibold text-emerald-700">{{ money(item.total) }}</p>
            </li>
          </ul>
        </article>
      </section>
    </template>

    <template v-if="activeTab === 'customers'">
      <section class="grid grid-cols-1 gap-3 xl:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
          <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-700">Clientes con mayor compra</div>
          <ul class="divide-y divide-slate-100">
            <li v-if="topCustomers.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">Sin datos en el rango.</li>
            <li v-for="item in topCustomers" :key="`${item.name}-${item.phone}`" class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-slate-50/70">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ item.name }}</p>
                <p class="text-xs text-slate-500">{{ item.purchases_in_range }} compras</p>
              </div>
              <p class="text-sm font-semibold text-emerald-700">{{ money(item.total_spent) }}</p>
            </li>
          </ul>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
          <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-700">Proximos a fidelidad</div>
          <ul class="divide-y divide-slate-100">
            <li v-if="nearLoyaltyCustomers.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">No hay clientes proximos al beneficio.</li>
            <li v-for="item in nearLoyaltyCustomers" :key="`${item.name}-${item.phone}`" class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-slate-50/70">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ item.name }}</p>
                <p class="text-xs text-slate-500">{{ item.phone ?? 'Sin telefono' }}</p>
              </div>
              <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ item.purchases_count }}/5</span>
            </li>
          </ul>
        </article>
      </section>
    </template>
  </div>
</template>
