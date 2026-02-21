<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3'
import SummaryCards    from '@/Components/CashCuts/SummaryCards.vue'
import PaymentBreakdown from '@/Components/CashCuts/PaymentBreakdown.vue'

const props = defineProps({
  selectedDate: { type: String, required: true },
  summary:      { type: Object,  default: null  },
  sales:        { type: Array,   default: () => [] },
  savedCuts:    { type: Array,   default: () => [] },
})

const flash = computed(() => usePage().props.flash ?? {})

// ── Estado local ─────────────────────────────────────────────────────────────
const localDate    = ref(props.selectedDate)
const localSummary = ref(props.summary)
const localSales   = ref(props.sales)
const generating   = ref(false)
const salesOpen    = ref(true)

// ── Generar resumen (AJAX) ────────────────────────────────────────────────────
async function generate() {
  generating.value = true
  try {
    const res = await fetch(route('cashcuts.preview'), {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept':       'application/json',
      },
      body: JSON.stringify({ date: localDate.value }),
    })
    if (!res.ok) throw new Error('Error al generar')
    const json = await res.json()
    localSummary.value = json.summary
    localSales.value   = json.sales
  } catch (e) {
    alert('No se pudo generar el resumen. Intenta de nuevo.')
  } finally {
    generating.value = false
  }
}

// ── Guardar corte ─────────────────────────────────────────────────────────────
const saveForm = useForm({ date: props.selectedDate })

function savecut() {
  saveForm.date = localDate.value
  saveForm.post(route('cashcuts.store'), { preserveScroll: true })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function money(v) {
  return Number(v ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

function fmtTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}

function methodLabel(methods) {
  if (!methods || methods.length === 0) return '—'
  if (methods.length > 1) return 'Mixto'
  const map = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', other: 'Otro' }
  return map[methods[0]] ?? methods[0]
}

function methodColor(methods) {
  if (!methods || methods.length === 0) return 'bg-gray-100 text-gray-500'
  if (methods.length > 1) return 'bg-slate-100 text-slate-600'
  const map = {
    cash:     'bg-emerald-50 text-emerald-700 ring-emerald-200',
    card:     'bg-blue-50    text-blue-700    ring-blue-200',
    transfer: 'bg-violet-50  text-violet-700  ring-violet-200',
    other:    'bg-gray-50    text-gray-600    ring-gray-200',
  }
  return (map[methods[0]] ?? 'bg-gray-100 text-gray-500') + ' ring-1'
}

function statusColor(status) {
  return status === 'completed'
    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
    : 'bg-red-50    text-red-600    ring-1 ring-red-200'
}
</script>

<template>
  <Head title="Corte diario" />

  <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

    <!-- ── Breadcrumb ──────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
      <Link :href="route('dashboard')" class="hover:text-gray-700 transition-colors">Inicio</Link>
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <span class="font-medium text-gray-700">Corte diario</span>
    </nav>

    <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Corte diario</h1>
        <p class="mt-1 text-sm text-gray-500">Resumen de ventas por día</p>
      </div>

      <!-- Selector de fecha + acciones -->
      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="localDate"
          type="date"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500
                 focus:ring-1 focus:ring-blue-500 focus:outline-none"
        />
        <button
          type="button"
          :disabled="generating"
          class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold
                 text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed
                 transition-colors"
          @click="generate"
        >
          <svg v-if="generating" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-3.866-3.134-7-7-7S5.5 8.134 5.5 12s3.134 7 7 7"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="m16.5 9 3 3-3 3"/>
          </svg>
          {{ generating ? 'Generando…' : 'Generar' }}
        </button>

        <button
          type="button"
          :disabled="!localSummary || saveForm.processing"
          class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold
                 text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed
                 transition-colors"
          @click="savecut"
        >
          <svg v-if="saveForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          {{ saveForm.processing ? 'Guardando…' : 'Guardar corte' }}
        </button>
      </div>
    </div>

    <!-- ── Flash ──────────────────────────────────────────────────────────── -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0"
    >
      <div v-if="flash.success"
           class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <svg class="h-4 w-4 flex-shrink-0 mt-0.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        {{ flash.success }}
      </div>
    </Transition>

    <!-- ── Estado vacío ───────────────────────────────────────────────────── -->
    <div v-if="!localSummary"
         class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed
                border-gray-200 bg-gray-50/50 py-16 text-center">
      <svg class="h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504
                 1.125-1.125m-8.625 0V5.625m0 0A1.125 1.125 0 0 1 4.5 4.5h6.75a1.125 1.125 0 0 1 1.125
                 1.125v2.25m-8.625-2.25H5.625m5.625 0h6.75a1.125 1.125 0 0 1 1.125 1.125v9.75M12 12.75h.008v.008H12v-.008Z"/>
      </svg>
      <p class="text-sm text-gray-500">Selecciona una fecha y presiona <strong>Generar</strong> para ver el resumen.</p>
    </div>

    <!-- ── Contenido generado ──────────────────────────────────────────────── -->
    <template v-if="localSummary">

      <!-- Fecha del corte -->
      <p class="text-sm font-medium text-gray-600">
        Mostrando resultados para:
        <span class="font-bold text-gray-900">{{ fmtDate(localSummary.date + 'T00:00:00') }}</span>
      </p>

      <!-- Cards de métricas -->
      <SummaryCards :summary="localSummary" />

      <!-- Desglose por método -->
      <PaymentBreakdown :payments="localSummary.payments" />

      <!-- ── Ventas del día ────────────────────────────────────────────────── -->
      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <button
          type="button"
          class="flex w-full items-center justify-between px-5 py-3 border-b border-gray-100
                 bg-gray-50/50 hover:bg-gray-100/60 transition-colors text-left"
          @click="salesOpen = !salesOpen"
        >
          <h3 class="text-sm font-semibold text-gray-700">
            Ventas del día
            <span class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
              {{ localSales.length }}
            </span>
          </h3>
          <svg
            :class="salesOpen ? 'rotate-180' : ''"
            class="h-4 w-4 text-gray-400 transition-transform"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </button>

        <div v-show="salesOpen">
          <!-- Tabla desktop -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-50 text-sm">
              <thead>
                <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                  <th class="px-5 py-3 text-left w-16">Folio</th>
                  <th class="px-5 py-3 text-left">Hora</th>
                  <th class="px-5 py-3 text-left">Cliente</th>
                  <th class="px-5 py-3 text-left w-28">Estado</th>
                  <th class="px-5 py-3 text-left">Método</th>
                  <th class="px-5 py-3 text-right">Total</th>
                  <th class="px-5 py-3 w-16"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 bg-white">
                <tr v-if="!localSales.length">
                  <td colspan="7" class="px-5 py-6 text-center text-sm text-gray-400">Sin ventas en esta fecha.</td>
                </tr>
                <tr v-for="sale in localSales" :key="sale.id" class="hover:bg-gray-50/60 transition-colors">
                  <td class="px-5 py-3 font-mono text-gray-500">#{{ sale.id }}</td>
                  <td class="px-5 py-3 text-gray-600 tabular-nums">{{ fmtTime(sale.created_at) }}</td>
                  <td class="px-5 py-3 text-gray-700">{{ sale.customer ?? '—' }}</td>
                  <td class="px-5 py-3">
                    <span :class="statusColor(sale.status)"
                          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                      {{ sale.status === 'completed' ? 'Pagada' : 'Cancelada' }}
                    </span>
                  </td>
                  <td class="px-5 py-3">
                    <span :class="methodColor(sale.methods)"
                          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                      {{ methodLabel(sale.methods) }}
                    </span>
                  </td>
                  <td class="px-5 py-3 text-right font-semibold tabular-nums text-gray-900">
                    ${{ money(sale.total) }}
                  </td>
                  <td class="px-5 py-3 text-center">
                    <Link
                      :href="route('sales.show', sale.id)"
                      class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600
                             hover:bg-gray-100 transition-colors"
                    >Ver</Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Lista móvil -->
          <ul class="sm:hidden divide-y divide-gray-100">
            <li v-if="!localSales.length" class="px-4 py-6 text-center text-sm text-gray-400">Sin ventas.</li>
            <li v-for="sale in localSales" :key="sale.id" class="px-4 py-3">
              <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs text-gray-400">#{{ sale.id }}</span>
                    <span :class="statusColor(sale.status)"
                          class="rounded-full px-2 py-0.5 text-xs font-medium">
                      {{ sale.status === 'completed' ? 'Pagada' : 'Cancelada' }}
                    </span>
                    <span :class="methodColor(sale.methods)"
                          class="rounded-full px-2 py-0.5 text-xs font-medium">
                      {{ methodLabel(sale.methods) }}
                    </span>
                  </div>
                  <p class="mt-0.5 text-xs text-gray-500">
                    {{ fmtTime(sale.created_at) }}
                    <template v-if="sale.customer"> · {{ sale.customer }}</template>
                  </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <span class="font-bold text-gray-900">${{ money(sale.total) }}</span>
                  <Link :href="route('sales.show', sale.id)"
                        class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600
                               hover:bg-gray-100">Ver</Link>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </template>

    <!-- ════════════════════════════════════════════════════════════════════════
         Historial de cortes guardados
    ═══════════════════════════════════════════════════════════════════════════ -->
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Historial de cortes guardados</h3>
        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">
          {{ savedCuts.length }}
        </span>
      </div>

      <!-- Vacío -->
      <div v-if="!savedCuts.length" class="px-5 py-8 text-center text-sm text-gray-400">
        Aún no hay cortes guardados.
      </div>

      <!-- Tabla desktop -->
      <div v-else class="hidden sm:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-50 text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3 text-left">Fecha</th>
              <th class="px-5 py-3 text-right">Total ventas</th>
              <th class="px-5 py-3 text-left">Creado por</th>
              <th class="px-5 py-3 text-left">Guardado</th>
              <th class="px-5 py-3 w-16"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50 bg-white">
            <tr v-for="cut in savedCuts" :key="cut.id" class="hover:bg-gray-50/60 transition-colors">
              <td class="px-5 py-3 font-medium text-gray-800">{{ fmtDate(cut.cut_date + 'T00:00:00') }}</td>
              <td class="px-5 py-3 text-right font-semibold tabular-nums text-gray-900">
                ${{ money(cut.totals_json?.total_sales) }}
              </td>
              <td class="px-5 py-3 text-gray-600">{{ cut.created_by }}</td>
              <td class="px-5 py-3 text-gray-500 tabular-nums text-xs">
                {{ fmtDate(cut.created_at) }} {{ fmtTime(cut.created_at) }}
              </td>
              <td class="px-5 py-3 text-center">
                <Link
                  :href="route('cashcuts.show', cut.id)"
                  class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600
                         hover:bg-gray-100 transition-colors"
                >Ver</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Lista móvil -->
      <ul v-if="savedCuts.length" class="sm:hidden divide-y divide-gray-100">
        <li v-for="cut in savedCuts" :key="cut.id" class="flex items-center justify-between gap-3 px-4 py-3">
          <div>
            <p class="font-medium text-gray-800 text-sm">{{ fmtDate(cut.cut_date + 'T00:00:00') }}</p>
            <p class="text-xs text-gray-400">{{ cut.created_by }} · {{ fmtTime(cut.created_at) }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="font-bold text-gray-900 text-sm">${{ money(cut.totals_json?.total_sales) }}</span>
            <Link :href="route('cashcuts.show', cut.id)"
                  class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">
              Ver
            </Link>
          </div>
        </li>
      </ul>
    </div>

  </div>
</template>
