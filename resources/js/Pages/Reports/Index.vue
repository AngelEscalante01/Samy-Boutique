<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import MetricCard    from '@/Components/Reports/MetricCard.vue'
import BarChartSimple from '@/Components/Reports/BarChartSimple.vue'

/* ── Props ───────────────────────────────────────────────────────────────── */
const props = defineProps({
  filters:              { type: Object, default: () => ({}) },
  salesSummary:         { type: Object, default: () => ({}) },
  salesByDay:           { type: Array,  default: () => [] },
  topCategories:        { type: Array,  default: () => [] },
  topProducts:          { type: Array,  default: () => [] },
  inventoryCounts:      { type: Object, default: () => ({}) },
  topCustomers:         { type: Array,  default: () => [] },
  nearLoyaltyCustomers: { type: Array,  default: () => [] },
})

/* ── Tabs ────────────────────────────────────────────────────────────────── */
const TABS = [
  { key: 'sales',    label: 'Ventas'    },
  { key: 'products', label: 'Productos' },
  { key: 'customers',label: 'Clientes'  },
]
const activeTab = ref('sales')

/* ── Filtros locales ─────────────────────────────────────────────────────── */
const localFrom  = ref(props.filters?.from ?? '')
const localTo    = ref(props.filters?.to   ?? '')
const applying   = ref(false)

function applyFilters() {
  applying.value = true
  router.get(route('reports.index'), { from: localFrom.value, to: localTo.value }, {
    preserveScroll: false,
    preserveState:  false,
    onFinish: () => { applying.value = false },
  })
}

function quickToday() {
  const today = new Date().toISOString().slice(0, 10)
  localFrom.value = today
  localTo.value   = today
  applyFilters()
}

function quickMonth() {
  const now   = new Date()
  const first = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  const last  = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
  localFrom.value = first
  localTo.value   = last
  applyFilters()
}

function clearFilters() {
  const today = new Date().toISOString().slice(0, 10)
  const first = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)
  localFrom.value = first
  localTo.value   = today
  applyFilters()
}

/* ── Helpers ─────────────────────────────────────────────────────────────── */
function money(v) {
  return Number(v ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })
}

/* ── Gráfico ─────────────────────────────────────────────────────────────── */
const chartData = computed(() =>
  props.salesByDay.slice(-30).map(d => ({ label: fmtDate(d.date), value: d.total }))
)

/* ── Exportar (placeholder) ──────────────────────────────────────────────── */
function exportPDF()   { alert('Exportación PDF próximamente.') }
function exportExcel() { alert('Exportación Excel próximamente.') }
</script>

<template>
  <Head title="Reportes" />

  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

    <!-- ── Breadcrumb ──────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
      <Link :href="route('dashboard')" class="hover:text-gray-700 transition-colors">Inicio</Link>
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <span class="font-medium text-gray-700">Reportes</span>
    </nav>

    <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Reportes</h1>
        <p class="mt-1 text-sm text-gray-500">Analiza ventas, descuentos y rendimiento</p>
      </div>
      <!-- Exportar -->
      <div class="flex items-center gap-2">
        <button type="button" @click="exportPDF"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2
                 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">
          <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375
                 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125
                 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0
                 0-9-9Z" />
          </svg>
          PDF
        </button>
        <button type="button" @click="exportExcel"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2
                 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">
          <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12
                 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Excel
        </button>
      </div>
    </div>

    <!-- ── Panel de filtros ───────────────────────────────────────────────── -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 px-5 py-4">
      <div class="flex flex-wrap items-end gap-3">
        <!-- Desde -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Desde</label>
          <input
            v-model="localFrom"
            type="date"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                   focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
          />
        </div>
        <!-- Hasta -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Hasta</label>
          <input
            v-model="localTo"
            type="date"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                   focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        <!-- Separador -->
        <div class="flex items-end gap-2 pb-0.5">
          <!-- Acceso rápido -->
          <button type="button" @click="quickToday"
            class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600
                   hover:bg-gray-100 transition-colors">
            Hoy
          </button>
          <button type="button" @click="quickMonth"
            class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600
                   hover:bg-gray-100 transition-colors">
            Este mes
          </button>
          <button type="button" @click="clearFilters"
            class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-500
                   hover:bg-gray-100 transition-colors">
            Limpiar
          </button>

          <!-- Aplicar -->
          <button
            type="button"
            :disabled="applying"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold
                   text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed
                   transition-colors"
            @click="applyFilters"
          >
            <svg v-if="applying" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            {{ applying ? 'Cargando…' : 'Aplicar' }}
          </button>
        </div>

        <!-- Rango activo badge -->
        <div class="ml-auto self-end text-xs text-gray-400 hidden sm:block">
          {{ filters.from }} → {{ filters.to }}
        </div>
      </div>
    </div>

    <!-- ── Tabs ───────────────────────────────────────────────────────────── -->
    <div class="flex gap-1 rounded-xl bg-gray-100/70 p-1.5 w-fit">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        type="button"
        :class="[
          activeTab === tab.key
            ? 'bg-white text-gray-900 shadow-sm'
            : 'text-gray-500 hover:text-gray-700 hover:bg-white/60',
          'rounded-lg px-4 py-2 text-sm font-medium transition-colors'
        ]"
        @click="activeTab = tab.key"
      >{{ tab.label }}</button>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════
         TAB: VENTAS
    ═════════════════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'sales'">

      <!-- Métricas -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <MetricCard
          label="Total vendido"
          :value="money(salesSummary.total)"
          :sub="salesSummary.count + ' ventas'"
          color="emerald"
          is-money
        />
        <MetricCard
          label="Ticket promedio"
          :value="money(salesSummary.avg_ticket)"
          color="blue"
          is-money
        />
        <MetricCard
          label="Desc. manuales"
          :value="money(salesSummary.discounts_total)"
          color="amber"
          is-money
        />
        <MetricCard
          label="Desc. cupones"
          :value="money(salesSummary.coupons_total)"
          color="purple"
          is-money
        />
        <MetricCard
          label="Desc. fidelidad"
          :value="money(salesSummary.loyalty_total)"
          color="blue"
          is-money
        />
        <MetricCard
          v-if="salesSummary.canceled_count > 0"
          label="Canceladas"
          :value="salesSummary.canceled_count"
          color="red"
        />
      </div>

      <!-- Gráfico de barras -->
      <div v-if="chartData.length" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 px-5 py-4">
        <h3 class="mb-4 text-sm font-semibold text-gray-700">
          Ventas por día
          <span class="ml-2 text-xs font-normal text-gray-400">(últimos {{ chartData.length }} días)</span>
        </h3>
        <BarChartSimple :data="chartData" color-class="bg-blue-500" height="h-28" />
      </div>
      <div v-else
           class="flex items-center justify-center rounded-xl border-2 border-dashed border-gray-200
                  bg-gray-50/50 py-10 text-sm text-gray-400">
        Sin ventas en el rango seleccionado.
      </div>

      <!-- Tabla ventas por día -->
      <div v-if="salesByDay.length" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-3">
          <h3 class="text-sm font-semibold text-gray-700">Resumen diario</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-50 text-sm">
            <thead>
              <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3 text-left">Fecha</th>
                <th class="px-5 py-3 text-right">Ventas</th>
                <th class="px-5 py-3 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 bg-white">
              <tr v-for="day in salesByDay" :key="day.date" class="hover:bg-gray-50/60 transition-colors">
                <td class="px-5 py-3 font-medium text-gray-800">
                  {{ new Date(day.date + 'T00:00:00').toLocaleDateString('es-MX', { weekday: 'short', day: '2-digit', month: 'short' }) }}
                </td>
                <td class="px-5 py-3 text-right text-gray-600 tabular-nums">{{ day.count }}</td>
                <td class="px-5 py-3 text-right font-semibold tabular-nums text-gray-900">
                  ${{ money(day.total) }}
                </td>
              </tr>
            </tbody>
            <!-- Total pie -->
            <tfoot>
              <tr class="border-t border-gray-200 bg-gray-50/80">
                <td class="px-5 py-3 font-bold text-gray-900">Total</td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 tabular-nums">
                  {{ salesByDay.reduce((a, d) => a + d.count, 0) }}
                </td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 tabular-nums text-base">
                  ${{ money(salesByDay.reduce((a, d) => a + d.total, 0)) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </template>

    <!-- ════════════════════════════════════════════════════════════════════
         TAB: PRODUCTOS
    ═════════════════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'products'">

      <!-- Inventario counts -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <MetricCard
          label="Disponibles"
          :value="inventoryCounts.available ?? 0"
          :sub="'Productos con stock'"
          color="emerald"
        />
        <MetricCard
          label="En apartado"
          :value="inventoryCounts.layaway ?? 0"
          :sub="'Items en layaway activo'"
          color="amber"
        />
      </div>

      <!-- Top productos -->
      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-700">Top productos vendidos</h3>
          <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">
            {{ topProducts.length }}
          </span>
        </div>

        <div v-if="!topProducts.length" class="px-5 py-8 text-center text-sm text-gray-400">
          Sin datos en este rango.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-50 text-sm">
            <thead>
              <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3 text-left w-8">#</th>
                <th class="px-5 py-3 text-left">Producto</th>
                <th class="px-5 py-3 text-left">SKU</th>
                <th class="px-5 py-3 text-right">Uds.</th>
                <th class="px-5 py-3 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 bg-white">
              <tr
                v-for="(p, i) in topProducts"
                :key="p.sku + i"
                class="hover:bg-gray-50/60 transition-colors"
              >
                <td class="px-5 py-3 text-gray-400 font-mono text-xs">{{ i + 1 }}</td>
                <td class="px-5 py-3 font-medium text-gray-800 max-w-[240px] truncate">{{ p.name }}</td>
                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ p.sku ?? '—' }}</td>
                <td class="px-5 py-3 text-right tabular-nums text-gray-600">{{ p.qty }}</td>
                <td class="px-5 py-3 text-right font-semibold tabular-nums text-gray-900">
                  ${{ money(p.total) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top categorías -->
      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-700">Top categorías</h3>
          <span class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-700">
            {{ topCategories.length }}
          </span>
        </div>

        <div v-if="!topCategories.length" class="px-5 py-8 text-center text-sm text-gray-400">
          Sin datos en este rango.
        </div>

        <div v-else>
          <!-- Barras visuales proporcionales -->
          <div class="px-5 pt-4 pb-2">
            <div
              v-for="(cat, i) in topCategories"
              :key="cat.name + i"
              class="mb-2.5"
            >
              <div class="flex justify-between text-xs mb-0.5">
                <span class="font-medium text-gray-700 truncate max-w-[160px]">{{ cat.name }}</span>
                <span class="tabular-nums text-gray-500">${{ money(cat.total) }} · {{ cat.qty }} uds.</span>
              </div>
              <div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                <div
                  class="h-2 rounded-full bg-purple-500 transition-all duration-500"
                  :style="{ width: Math.max(2, Math.round((cat.total / topCategories[0].total) * 100)) + '%' }"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

    </template>

    <!-- ════════════════════════════════════════════════════════════════════
         TAB: CLIENTES
    ═════════════════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'customers'">

      <!-- Top clientes -->
      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-700">Clientes más activos</h3>
          <span class="text-xs text-gray-400">en el rango seleccionado</span>
        </div>

        <div v-if="!topCustomers.length" class="px-5 py-8 text-center text-sm text-gray-400">
          Sin datos de clientes en este rango.
        </div>

        <div v-else>
          <!-- Desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-50 text-sm">
              <thead>
                <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                  <th class="px-5 py-3 text-left w-8">#</th>
                  <th class="px-5 py-3 text-left">Cliente</th>
                  <th class="px-5 py-3 text-left">Teléfono</th>
                  <th class="px-5 py-3 text-right">Compras</th>
                  <th class="px-5 py-3 text-right">Total gastado</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 bg-white">
                <tr
                  v-for="(c, i) in topCustomers"
                  :key="c.name + i"
                  class="hover:bg-gray-50/60 transition-colors"
                >
                  <td class="px-5 py-3 text-gray-400 text-xs">{{ i + 1 }}</td>
                  <td class="px-5 py-3 font-medium text-gray-800">{{ c.name }}</td>
                  <td class="px-5 py-3 text-gray-500 font-mono text-xs">{{ c.phone ?? '—' }}</td>
                  <td class="px-5 py-3 text-right tabular-nums text-gray-700">{{ c.purchases_in_range }}</td>
                  <td class="px-5 py-3 text-right font-semibold tabular-nums text-gray-900">
                    ${{ money(c.total_spent) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- Móvil -->
          <ul class="sm:hidden divide-y divide-gray-100">
            <li v-for="(c, i) in topCustomers" :key="'mc' + i" class="flex items-center gap-3 px-4 py-3">
              <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full
                           bg-blue-50 text-xs font-bold text-blue-600">{{ i + 1 }}</span>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-gray-800 truncate">{{ c.name }}</p>
                <p class="text-xs text-gray-400">{{ c.purchases_in_range }} compras</p>
              </div>
              <span class="font-bold text-gray-900 text-sm flex-shrink-0">${{ money(c.total_spent) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Cerca del punto de fidelidad -->
      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-amber-200">
        <div class="border-b border-amber-100 bg-amber-50/40 px-5 py-3 flex items-center gap-2">
          <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988
                 l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586
                 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1
                 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
          </svg>
          <h3 class="text-sm font-semibold text-amber-700">Próximos a fidelidad</h3>
          <span class="ml-auto rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
            {{ nearLoyaltyCustomers.length }}
          </span>
        </div>

        <div v-if="!nearLoyaltyCustomers.length" class="px-5 py-6 text-center text-sm text-gray-400">
          Ningún cliente con 4 compras acumuladas actualmente.
        </div>

        <ul v-else class="divide-y divide-gray-50">
          <li
            v-for="c in nearLoyaltyCustomers"
            :key="c.name"
            class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-amber-50/30 transition-colors"
          >
            <div>
              <p class="font-medium text-gray-800 text-sm">{{ c.name }}</p>
              <p class="text-xs text-gray-400">{{ c.phone ?? '—' }}</p>
            </div>
            <div class="flex items-center gap-3">
              <!-- Progreso 4/5 -->
              <div class="flex gap-1">
                <span
                  v-for="n in 5"
                  :key="n"
                  :class="n <= c.purchases_count ? 'bg-amber-400' : 'bg-gray-200'"
                  class="h-2.5 w-2.5 rounded-full"
                />
              </div>
              <span class="text-xs font-semibold text-amber-600">{{ c.purchases_count }}/5</span>
            </div>
          </li>
        </ul>
      </div>

    </template>

  </div>
</template>
