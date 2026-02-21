<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import SummaryCards    from '@/Components/CashCuts/SummaryCards.vue'
import PaymentBreakdown from '@/Components/CashCuts/PaymentBreakdown.vue'

const props = defineProps({
  cashCut:      { type: Object, required: true },
  parsedTotals: { type: Object, default: () => ({}) },
})

// Normalizar para que SummaryCards y PaymentBreakdown siempre tengan datos
const summary = computed(() => ({
  date:                   props.cashCut.cut_date,
  sales_count:            props.parsedTotals?.sales_count            ?? 0,
  canceled_count:         props.parsedTotals?.canceled_count         ?? 0,
  total_sales:            props.parsedTotals?.total_sales            ?? 0,
  discount_manual_total:  props.parsedTotals?.discount_manual_total  ?? 0,
  discount_coupon_total:  props.parsedTotals?.discount_coupon_total  ?? 0,
  discount_loyalty_total: props.parsedTotals?.discount_loyalty_total ?? 0,
}))

const payments = computed(() => {
  const p = props.parsedTotals?.payments ?? {}
  return {
    cash:     p.cash     ?? 0,
    card:     p.card     ?? 0,
    transfer: p.transfer ?? 0,
    other:    p.other    ?? 0,
  }
})

function money(v) {
  return Number(v ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('es-MX', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
}

function fmtDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function printPage() {
  window.print()
}
</script>

<template>
  <Head :title="`Corte ${cashCut.cut_date}`" />

  <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

    <!-- ── Breadcrumb ──────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
      <Link :href="route('dashboard')" class="hover:text-gray-700 transition-colors">Inicio</Link>
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <Link :href="route('cashcuts.index')" class="hover:text-gray-700 transition-colors">
        Corte diario
      </Link>
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <span class="font-medium text-gray-700">{{ cashCut.cut_date }}</span>
    </nav>

    <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 capitalize">{{ fmtDate(cashCut.cut_date + 'T00:00:00') }}</h1>
        <p class="mt-1 text-sm text-gray-500">Corte de caja guardado</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2
               text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors print:hidden"
        @click="printPage"
      >
        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34
                   18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0
                   1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0
                   21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25
                   2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.056 48.056 0 0 1 1.913-.247m10.5
                   0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621
                   0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
        </svg>
        Imprimir
      </button>
    </div>

    <!-- ── Meta info ──────────────────────────────────────────────────────── -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 px-5 py-4">
      <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha del corte</dt>
          <dd class="mt-1 text-sm font-medium text-gray-900">{{ cashCut.cut_date }}</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Creado por</dt>
          <dd class="mt-1 text-sm font-medium text-gray-900">{{ cashCut.created_by }}</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Guardado el</dt>
          <dd class="mt-1 text-sm font-medium text-gray-900">{{ fmtDateTime(cashCut.created_at) }}</dd>
        </div>
      </dl>
    </div>

    <!-- ── Métricas ───────────────────────────────────────────────────────── -->
    <SummaryCards :summary="summary" />

    <!-- ── Desglose por método ────────────────────────────────────────────── -->
    <PaymentBreakdown :payments="payments" />

    <!-- ── Totales finales ────────────────────────────────────────────────── -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50">
        <h3 class="text-sm font-semibold text-gray-700">Totales del corte</h3>
      </div>
      <dl class="divide-y divide-gray-50 text-sm">
        <div class="flex justify-between px-5 py-3">
          <dt class="text-gray-600">Subtotal (sin descuentos)</dt>
          <dd class="font-medium tabular-nums text-gray-900">${{ money(parsedTotals?.subtotal_sum) }}</dd>
        </div>
        <div class="flex justify-between px-5 py-3">
          <dt class="text-gray-600">Descuentos manuales</dt>
          <dd class="font-medium tabular-nums text-amber-600">- ${{ money(parsedTotals?.discount_manual_total) }}</dd>
        </div>
        <div class="flex justify-between px-5 py-3">
          <dt class="text-gray-600">Descuentos por cupón</dt>
          <dd class="font-medium tabular-nums text-purple-600">- ${{ money(parsedTotals?.discount_coupon_total) }}</dd>
        </div>
        <div class="flex justify-between px-5 py-3">
          <dt class="text-gray-600">Descuentos fidelidad</dt>
          <dd class="font-medium tabular-nums text-blue-600">- ${{ money(parsedTotals?.discount_loyalty_total) }}</dd>
        </div>
        <div class="flex justify-between bg-gray-50/80 px-5 py-4 border-t border-gray-200">
          <dt class="font-bold text-gray-900 text-base">Total cobrado</dt>
          <dd class="font-bold tabular-nums text-gray-900 text-xl">${{ money(summary.total_sales) }}</dd>
        </div>
      </dl>
    </div>

    <!-- ── Acciones ───────────────────────────────────────────────────────── -->
    <div class="flex justify-end print:hidden">
      <Link
        :href="route('cashcuts.index')"
        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700
               hover:bg-gray-50 transition-colors"
      >← Volver al historial</Link>
    </div>

  </div>
</template>
