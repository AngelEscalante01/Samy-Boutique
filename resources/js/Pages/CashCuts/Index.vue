<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  selectedDate: { type: String, required: true },
  summary: { type: Object, default: null },
  sales: { type: Array, default: () => [] },
  savedCuts: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash ?? {})

const localDate = ref(props.selectedDate)
const localSummary = ref(props.summary)
const localSales = ref(props.sales)
const generating = ref(false)
const previewError = ref('')

const saveForm = useForm({
  date: props.selectedDate,
})

const cards = computed(() => {
  if (!localSummary.value) return []

  return [
    {
      key: 'total_sales',
      label: 'Total vendido',
      value: money(localSummary.value.total_sold),
      tone: 'text-emerald-700 bg-emerald-50 ring-emerald-100',
      sub: `${Number(localSummary.value.sales_count ?? 0).toLocaleString('es-MX')} ventas`,
    },
    {
      key: 'profit_total',
      label: 'Ganancia',
      value: money(localSummary.value.profit_total),
      tone: 'text-emerald-700 bg-emerald-100 ring-emerald-200',
      sub: 'Ganancia real del dia',
      highlight: true,
    },
    {
      key: 'sales_count',
      label: 'Ventas realizadas',
      value: Number(localSummary.value.sales_count ?? 0).toLocaleString('es-MX'),
      tone: 'text-sky-700 bg-sky-50 ring-sky-100',
      sub: 'Operaciones completadas',
    },
    {
      key: 'canceled_count',
      label: 'Canceladas',
      value: Number(localSummary.value.canceled_count ?? 0).toLocaleString('es-MX'),
      tone: 'text-rose-700 bg-rose-50 ring-rose-100',
      sub: 'No cuentan en total ni ganancia',
    },
    {
      key: 'manual_discount_total',
      label: 'Desc. manuales',
      value: money(localSummary.value.manual_discount_total),
      tone: 'text-amber-700 bg-amber-50 ring-amber-100',
      sub: 'Ajustes manuales',
    },
    {
      key: 'coupon_discount_total',
      label: 'Desc. cupones',
      value: money(localSummary.value.coupon_discount_total),
      tone: 'text-violet-700 bg-violet-50 ring-violet-100',
      sub: 'Promociones aplicadas',
    },
    {
      key: 'loyalty_discount_total',
      label: 'Desc. fidelidad',
      value: money(localSummary.value.loyalty_discount_total),
      tone: 'text-indigo-700 bg-indigo-50 ring-indigo-100',
      sub: 'Beneficios lealtad',
    },
  ]
})

const payments = computed(() => {
  const base = localSummary.value?.payments ?? {}

  return {
    cash: Number(base.cash ?? 0),
    card: Number(base.card ?? 0),
    transfer: Number(base.transfer ?? 0),
    other: Number(base.other ?? 0),
  }
})

const paymentRows = computed(() => [
  { key: 'cash', label: 'Efectivo', className: 'bg-emerald-100 text-emerald-700 ring-emerald-200' },
  { key: 'card', label: 'Tarjeta', className: 'bg-sky-100 text-sky-700 ring-sky-200' },
  { key: 'transfer', label: 'Transferencia', className: 'bg-indigo-100 text-indigo-700 ring-indigo-200' },
  { key: 'other', label: 'Otro', className: 'bg-slate-100 text-slate-600 ring-slate-200' },
])

async function generate() {
  previewError.value = ''
  generating.value = true

  try {
    const response = await window.fetch(route('cashcuts.preview'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document?.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        Accept: 'application/json',
      },
      body: JSON.stringify({ date: localDate.value }),
    })

    const data = await response.json()

    if (!response.ok) {
      previewError.value = data?.errors?.date?.[0] || data?.message || 'No se pudo generar el corte.'
      return
    }

    localSummary.value = data.summary
    localSales.value = data.sales
  } catch (error) {
    previewError.value = 'Error de red al generar el corte.'
  } finally {
    generating.value = false
  }
}

function saveCut() {
  saveForm.date = localDate.value
  saveForm.post(route('cashcuts.store'), {
    preserveScroll: true,
  })
}

function money(value) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function fmtDate(value) {
  if (!value) return '—'

  return new Date(value).toLocaleDateString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function fmtDateTime(value) {
  if (!value) return '—'

  return new Date(value).toLocaleString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <Head title="Corte diario" />

  <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Corte diario</h1>
        <p class="mt-0.5 text-sm text-slate-500">Resumen de ventas y ganancias por dia</p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="localDate"
          type="date"
          class="h-9 rounded-lg border border-slate-200 px-2.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
        >

        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="generating"
          @click="generate"
        >
          {{ generating ? 'Generando...' : 'Generar' }}
        </button>

        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!localSummary || saveForm.processing"
          @click="saveCut"
        >
          {{ saveForm.processing ? 'Guardando...' : 'Guardar corte' }}
        </button>
      </div>
    </section>

    <section v-if="flash.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
      {{ flash.success }}
    </section>
    <section v-if="previewError" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">
      {{ previewError }}
    </section>

    <section
      v-if="!localSummary"
      class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-16 text-center"
    >
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-slate-400 ring-1 ring-slate-200">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25M6 7.5h12M6 12h12M6 16.5h8.25" />
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-700">Aun no hay corte generado</p>
        <p class="mt-0.5 text-xs text-slate-500">Selecciona una fecha y presiona Generar para calcular ventas, descuentos y ganancias.</p>
      </div>
    </section>

    <template v-else>
      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="card in cards"
          :key="card.key"
          class="rounded-xl border border-slate-200 bg-white px-3.5 py-3 ring-1"
          :class="[card.tone, card.highlight ? 'xl:col-span-2' : '']"
        >
          <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ card.label }}</p>
          <p class="mt-1.5 text-xl font-bold tracking-tight">{{ card.value }}</p>
          <p class="mt-1 text-xs text-slate-500">{{ card.sub }}</p>
        </article>
      </section>

      <section class="grid grid-cols-1 gap-3 xl:grid-cols-3">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] xl:col-span-2">
          <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-700">Ventas del dia</div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50/60">
                <tr>
                  <th class="py-2.5 pl-4 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Folio</th>
                  <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                  <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                  <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ganancia</th>
                  <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
                  <th class="py-2.5 pl-2 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="localSales.length === 0">
                  <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">Sin ventas para la fecha seleccionada.</td>
                </tr>
                <tr v-for="sale in localSales" :key="sale.id" class="transition-colors duration-150 hover:bg-slate-50/80">
                  <td class="py-2.5 pl-4 pr-2 text-sm font-mono text-slate-700">#{{ sale.id }}</td>
                  <td class="px-2 py-2.5">
                    <span
                      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                      :class="sale.status === 'completed'
                        ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
                        : 'bg-rose-100 text-rose-700 ring-rose-200'"
                    >
                      {{ sale.status === 'completed' ? 'Pagada' : 'Cancelada' }}
                    </span>
                  </td>
                  <td class="px-2 py-2.5 text-right text-sm font-semibold text-slate-800">{{ money(sale.total) }}</td>
                  <td class="px-2 py-2.5 text-right text-sm font-semibold" :class="sale.status === 'completed' ? 'text-sky-700' : 'text-slate-400'">
                    {{ sale.status === 'completed' ? money(sale.profit) : '—' }}
                  </td>
                  <td class="px-2 py-2.5 text-sm text-slate-500">{{ fmtDateTime(sale.created_at) }}</td>
                  <td class="py-2.5 pl-2 pr-4 text-right">
                    <Link
                      :href="route('sales.show', sale.id)"
                      class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                      Ver
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
          <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-700">Metodos de pago</div>
          <ul class="divide-y divide-slate-100">
            <li v-for="row in paymentRows" :key="row.key" class="flex items-center justify-between px-4 py-2.5">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="row.className">
                {{ row.label }}
              </span>
              <span class="text-sm font-semibold text-slate-800">{{ money(payments[row.key]) }}</span>
            </li>
            <li class="flex items-center justify-between border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</span>
              <span class="text-sm font-bold text-slate-900">
                {{ money(payments.cash + payments.card + payments.transfer + payments.other) }}
              </span>
            </li>
          </ul>
        </article>
      </section>
    </template>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-700">Historial de cortes guardados</div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50/60">
            <tr>
              <th class="py-2.5 pl-4 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
              <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total ventas</th>
              <th class="px-2 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ganancia</th>
              <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Creado por</th>
              <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Guardado</th>
              <th class="py-2.5 pl-2 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="savedCuts.length === 0">
              <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">Todavia no hay cortes guardados.</td>
            </tr>

            <tr v-for="cut in savedCuts" :key="cut.id" class="transition-colors duration-150 hover:bg-slate-50/80">
              <td class="py-2.5 pl-4 pr-2 text-sm font-medium text-slate-800">{{ fmtDate(`${cut.cut_date}T00:00:00`) }}</td>
              <td class="px-2 py-2.5 text-right text-sm font-semibold text-emerald-700">{{ money(cut.totals_json?.total_sold ?? cut.totals_json?.total_sales ?? 0) }}</td>
              <td class="px-2 py-2.5 text-right text-sm font-semibold text-sky-700">{{ money(cut.totals_json?.profit_total ?? 0) }}</td>
              <td class="px-2 py-2.5 text-sm text-slate-600">{{ cut.created_by }}</td>
              <td class="px-2 py-2.5 text-sm text-slate-500">{{ fmtDateTime(cut.created_at) }}</td>
              <td class="py-2.5 pl-2 pr-4 text-right">
                <Link
                  :href="route('cashcuts.show', cut.id)"
                  class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                  Ver
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
