<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { printLayawayClosed, printLayawayCreated, printLayawayPayment, printSale } from '@/services/printSale'

const props = defineProps({
  layaway: { type: Object, required: true },
  can:     { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash ?? {})
const page = usePage()
const isOpen = computed(() => props.layaway.status === 'open')
const paymentPrintMessage = ref(null) // { type: 'success' | 'warning' | 'error', text: string }
const vigenciaMessage = ref(null)
const AUTO_PRINT_FINAL_SALE_AFTER_LIQUIDATION = false
const isReprintingCreated = ref(false)
const isReprintingClosed = ref(false)
const reprintingPaymentId = ref(null)
const vigenciaOpciones = [7, 15, 30, 45, 60]

const initialVigencia = Number(props.layaway.vigencia_dias || 0)
const initialInPreset = vigenciaOpciones.includes(initialVigencia)
const vigenciaSeleccion = ref(initialVigencia > 0 ? (initialInPreset ? String(initialVigencia) : 'manual') : '30')
const vigenciaManual = ref(initialVigencia > 0 && !initialInPreset ? String(initialVigencia) : '')
const vigenciaForm = useForm({ vigencia_dias: initialVigencia > 0 ? initialVigencia : 30 })

const badgeClass = { open: 'bg-amber-100 text-amber-800', liquidated: 'bg-emerald-100 text-emerald-800', cancelled: 'bg-red-100 text-red-700' }
const statusLabel = { open: 'Activo', liquidated: 'Liquidado', cancelled: 'Cancelado' }
const methodLabel  = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', other: 'Otro' }

function money(v) { return Number(v).toFixed(2) }
function fmtDate(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
function fmtDateShort(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }
function thumbUrl(product) { const img = product?.images?.[0]; return img ? '/storage/' + img.path : null }

const vigenciaDiasSeleccionados = computed(() => {
  if (vigenciaSeleccion.value === 'manual') {
    return Number(vigenciaManual.value || 0)
  }

  return Number(vigenciaSeleccion.value || 0)
})

const vigenciaEstadoLabel = {
  vigente: 'Vigente',
  vence_hoy: 'Vence hoy',
  vencido: 'Vencido',
  sin_vigencia: 'Sin vigencia',
}

const vigenciaEstadoClass = {
  vigente: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  vence_hoy: 'bg-amber-100 text-amber-800 border-amber-200',
  vencido: 'bg-red-100 text-red-700 border-red-200',
  sin_vigencia: 'bg-slate-100 text-slate-700 border-slate-200',
}

const fechaApartadoBase = computed(() => {
  if (props.layaway.created_at) {
    const date = new Date(props.layaway.created_at)
    date.setHours(0, 0, 0, 0)
    return date
  }

  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return today
})

const fechaVencimientoPreview = computed(() => {
  const dias = vigenciaDiasSeleccionados.value
  if (!Number.isInteger(dias) || dias <= 0) return null

  const due = new Date(fechaApartadoBase.value)
  due.setDate(due.getDate() + dias)
  return due
})

const canEditVigencia = computed(() => props.layaway.status === 'open')

const showExpiredWarning = computed(() => props.layaway.estado_vigencia === 'vencido' && props.layaway.status === 'open')
const showDueTodayWarning = computed(() => props.layaway.estado_vigencia === 'vence_hoy' && props.layaway.status === 'open')

function submitVigencia() {
  vigenciaMessage.value = null

  if (!canEditVigencia.value) {
    vigenciaMessage.value = {
      type: 'warning',
      text: 'La vigencia ya no puede modificarse en apartados liquidados o cancelados.',
    }
    return
  }

  const dias = vigenciaDiasSeleccionados.value
  if (!Number.isInteger(dias) || dias <= 0) {
    vigenciaMessage.value = {
      type: 'error',
      text: 'La vigencia debe ser un numero entero mayor a 0 dias.',
    }
    return
  }

  vigenciaForm.vigencia_dias = dias
  vigenciaForm.patch(route('layaways.vigencia.update', props.layaway.id), {
    preserveScroll: true,
    onError: () => {
      vigenciaMessage.value = {
        type: 'error',
        text: 'No se pudo actualizar la vigencia. Verifica los datos e intenta nuevamente.',
      }
    },
    onSuccess: () => {
      vigenciaMessage.value = {
        type: 'success',
        text: 'Vigencia actualizada correctamente.',
      }
    },
  })
}

const addPaymentForm = useForm({ method: 'cash', amount: '', reference: '' })
function submitAddPayment() {
  paymentPrintMessage.value = null

  addPaymentForm.post(route('layaways.payments.store', props.layaway.id), {
    preserveScroll: true,
    onError: () => {
      paymentPrintMessage.value = {
        type: 'error',
        text: 'No se pudo guardar el abono. Revisa los datos e intenta nuevamente.',
      }
    },
    onSuccess: async (pageResponse) => {
      addPaymentForm.reset()

      const paymentId = pageResponse?.props?.flash?.print_layaway_payment_id
        ?? page.props?.flash?.print_layaway_payment_id
        ?? null

      if (!paymentId) {
        paymentPrintMessage.value = {
          type: 'warning',
          text: 'Abono guardado, pero no se encontro el identificador para imprimir ticket.',
        }
        return
      }

      const printResult = await printLayawayPayment(props.layaway.id, paymentId)
      if (printResult.ok) {
        paymentPrintMessage.value = {
          type: 'success',
          text: 'Abono guardado e impresion enviada correctamente.',
        }
        return
      }

      paymentPrintMessage.value = {
        type: 'error',
        text: `Abono guardado, pero no se pudo imprimir: ${printResult.message}`,
      }
    },
  })
}

const remaining = computed(() => Number(props.layaway.balance))
const liquidateForm = useForm({ payments: [{ method: 'cash', amount: '' }] })
watch(remaining, (val) => { if (liquidateForm.payments.length === 1) liquidateForm.payments[0].amount = val > 0 ? val.toFixed(2) : '' }, { immediate: true })
function addLiquidatePayment() { liquidateForm.payments.push({ method: 'cash', amount: '' }) }
function removeLiquidatePayment(i) { if (liquidateForm.payments.length > 1) liquidateForm.payments.splice(i, 1) }
function submitLiquidate() {
  const payments = liquidateForm.payments
    .map((p) => ({
      method: p.method,
      amount: Number(p.amount || 0),
      reference: p.reference ?? null,
    }))
    .filter((p) => p.amount > 0)

  liquidateForm.transform(() => ({ payments }))
  paymentPrintMessage.value = null

  liquidateForm.post(route('layaways.liquidate', props.layaway.id), {
    preserveScroll: true,
    onError: () => {
      paymentPrintMessage.value = {
        type: 'error',
        text: 'No se pudo liquidar el apartado. Revisa los pagos e intenta nuevamente.',
      }
    },
    onSuccess: async (pageResponse) => {
      const layawayId = pageResponse?.props?.flash?.print_layaway_closed_id
        ?? page.props?.flash?.print_layaway_closed_id
        ?? null

      if (!layawayId) {
        paymentPrintMessage.value = {
          type: 'warning',
          text: 'Liquidacion guardada, pero no se encontro el folio para imprimir ticket de liquidacion.',
        }
        return
      }

      const printLayawayResult = await printLayawayClosed(layawayId)
      if (!printLayawayResult.ok) {
        paymentPrintMessage.value = {
          type: 'error',
          text: `Liquidacion guardada, pero fallo la impresion del ticket: ${printLayawayResult.message}`,
        }
        return
      }

      const saleId = printLayawayResult.ticket?.sale_id
        ?? pageResponse?.props?.flash?.print_sale_id
        ?? page.props?.flash?.print_sale_id
        ?? null

      if (AUTO_PRINT_FINAL_SALE_AFTER_LIQUIDATION && saleId) {
        const printSaleResult = await printSale(saleId)
        if (!printSaleResult.ok) {
          paymentPrintMessage.value = {
            type: 'warning',
            text: `Liquidacion impresa. Ticket de venta final pendiente: ${printSaleResult.message}`,
          }
          return
        }

        paymentPrintMessage.value = {
          type: 'success',
          text: 'Liquidacion guardada e impresiones (liquidacion y venta final) enviadas correctamente.',
        }
        return
      }

      paymentPrintMessage.value = {
        type: saleId ? 'warning' : 'success',
        text: saleId
          ? 'Liquidacion guardada e impresa. Ticket de venta final disponible (activar AUTO_PRINT_FINAL_SALE_AFTER_LIQUIDATION para imprimirlo automaticamente).'
          : 'Liquidacion guardada e impresion de ticket enviada correctamente.',
      }
    },
  })
}

const showCancelConfirm = ref(false)
const cancelForm = useForm({})
function submitCancel() { cancelForm.post(route('layaways.cancel', props.layaway.id)); showCancelConfirm.value = false }

async function reprintCreatedTicket() {
  if (isReprintingCreated.value) return

  paymentPrintMessage.value = null
  isReprintingCreated.value = true

  try {
    const result = await printLayawayCreated(props.layaway.id)
    paymentPrintMessage.value = {
      type: result.ok ? 'success' : 'error',
      text: result.ok
        ? 'Reimpresion de ticket de creacion enviada correctamente.'
        : `No se pudo reimprimir ticket de creacion: ${result.message}`,
    }
  } finally {
    isReprintingCreated.value = false
  }
}

async function reprintPaymentTicket(paymentId) {
  if (!paymentId || reprintingPaymentId.value === paymentId) return

  paymentPrintMessage.value = null
  reprintingPaymentId.value = paymentId

  try {
    const result = await printLayawayPayment(props.layaway.id, paymentId)
    paymentPrintMessage.value = {
      type: result.ok ? 'success' : 'error',
      text: result.ok
        ? 'Reimpresion de ticket de abono enviada correctamente.'
        : `No se pudo reimprimir ticket de abono: ${result.message}`,
    }
  } finally {
    reprintingPaymentId.value = null
  }
}

async function reprintClosedTicket() {
  if (isReprintingClosed.value || props.layaway.status !== 'liquidated') return

  paymentPrintMessage.value = null
  isReprintingClosed.value = true

  try {
    const result = await printLayawayClosed(props.layaway.id)
    paymentPrintMessage.value = {
      type: result.ok ? 'success' : 'error',
      text: result.ok
        ? 'Reimpresion de ticket de liquidacion enviada correctamente.'
        : `No se pudo reimprimir ticket de liquidacion: ${result.message}`,
    }
  } finally {
    isReprintingClosed.value = false
  }
}
</script>

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

    <div
      v-if="paymentPrintMessage"
      class="rounded-xl border px-4 py-3 text-sm"
      :class="paymentPrintMessage.type === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : paymentPrintMessage.type === 'warning'
          ? 'border-amber-200 bg-amber-50 text-amber-800'
          : 'border-red-200 bg-red-50 text-red-800'"
    >
      {{ paymentPrintMessage.text }}
    </div>

    <div
      v-if="vigenciaMessage"
      class="rounded-xl border px-4 py-3 text-sm"
      :class="vigenciaMessage.type === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : vigenciaMessage.type === 'warning'
          ? 'border-amber-200 bg-amber-50 text-amber-800'
          : 'border-red-200 bg-red-50 text-red-800'"
    >
      {{ vigenciaMessage.text }}
    </div>

    <div v-if="showExpiredWarning" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      Este apartado esta vencido. Revisa con el cliente antes de registrar nuevos abonos.
    </div>

    <div v-if="showDueTodayWarning" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      Este apartado vence hoy. Considera renovar vigencia o liquidarlo hoy mismo.
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">${{ money(layaway.subtotal) }}</p>
      </div>
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Abonado</p>
        <p class="mt-1 text-2xl font-bold text-emerald-600">${{ money(layaway.paid_total) }}</p>
      </div>
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Saldo</p>
        <p class="mt-1 text-2xl font-bold" :class="remaining > 0 ? 'text-amber-600' : 'text-gray-400'">${{ money(layaway.balance) }}</p>
      </div>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
      <h2 class="text-sm font-semibold text-gray-700">Vigencia del apartado</h2>
      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha apartado</p>
          <p class="mt-1 text-sm font-semibold text-gray-800">{{ fmtDateShort(layaway.created_at) }}</p>
        </div>
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Vigencia</p>
          <p class="mt-1 text-sm font-semibold text-gray-800">{{ layaway.vigencia_dias ? layaway.vigencia_dias + ' dias' : 'Sin vigencia' }}</p>
        </div>
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Vence el</p>
          <p class="mt-1 text-sm font-semibold text-gray-800">{{ fmtDateShort(layaway.fecha_vencimiento) }}</p>
        </div>
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Estado</p>
          <span class="mt-1 inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
            :class="vigenciaEstadoClass[layaway.estado_vigencia] ?? vigenciaEstadoClass.sin_vigencia">
            {{ vigenciaEstadoLabel[layaway.estado_vigencia] ?? vigenciaEstadoLabel.sin_vigencia }}
          </span>
        </div>
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
                  <span v-if="item.variant?.size"> · {{ item.variant.size.name }}</span>
                  <span v-if="item.variant?.color"> / {{ item.variant.color.name }}</span>
                  <span> · x{{ item.qty ?? item.quantity ?? 1 }}</span>
                </p>
              </div>
              <span class="text-sm font-bold text-gray-900 flex-shrink-0">${{ money((item.unit_price || 0) * (item.qty ?? item.quantity ?? 1)) }}</span>
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
                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Reimpresion</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="pmt in layaway.payments" :key="pmt.id">
                <td class="py-2 text-gray-500">{{ fmtDateShort(pmt.created_at) }}</td>
                <td class="py-2 text-gray-700">{{ methodLabel[pmt.method] ?? pmt.method }}</td>
                <td class="py-2 text-gray-400 font-mono text-xs">{{ pmt.reference ?? '—' }}</td>
                <td class="py-2 text-right font-semibold text-emerald-600">${{ money(pmt.amount) }}</td>
                <td class="py-2 text-right">
                  <button
                    type="button"
                    class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="reprintingPaymentId === pmt.id"
                    @click="reprintPaymentTicket(pmt.id)"
                  >
                    {{ reprintingPaymentId === pmt.id ? 'Imprimiendo...' : 'Reimprimir' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right column: actions -->
      <div class="space-y-4">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Editar vigencia</h2>
          <p class="text-xs text-gray-500">Solo disponible para apartados abiertos.</p>

          <select v-model="vigenciaSeleccion" :disabled="!canEditVigencia"
            class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400 disabled:bg-gray-50">
            <option v-for="dias in vigenciaOpciones" :key="dias" :value="String(dias)">{{ dias }} dias</option>
            <option value="manual">Otro (captura manual)</option>
          </select>

          <div v-if="vigenciaSeleccion === 'manual'">
            <label class="mb-1 block text-xs font-medium text-gray-600">Dias de vigencia</label>
            <input v-model="vigenciaManual" type="number" min="1" step="1" :disabled="!canEditVigencia"
              class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400 disabled:bg-gray-50" />
          </div>

          <p v-if="vigenciaForm.errors.vigencia_dias" class="text-xs text-red-600">{{ vigenciaForm.errors.vigencia_dias }}</p>

          <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <p>Fecha apartado: <strong>{{ fmtDateShort(fechaApartadoBase) }}</strong></p>
            <p>Vigencia seleccionada: <strong>{{ Number.isInteger(vigenciaDiasSeleccionados) && vigenciaDiasSeleccionados > 0 ? vigenciaDiasSeleccionados + ' dias' : 'Invalida' }}</strong></p>
            <p>Nuevo vencimiento: <strong>{{ fechaVencimientoPreview ? fmtDateShort(fechaVencimientoPreview) : 'Fecha invalida' }}</strong></p>
          </div>

          <button @click="submitVigencia" :disabled="vigenciaForm.processing || !canEditVigencia"
            class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 transition disabled:opacity-50">
            {{ vigenciaForm.processing ? 'Actualizando...' : 'Actualizar vigencia' }}
          </button>
        </div>

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
          <p v-if="liquidateForm.errors.payments" class="text-xs text-red-600">{{ liquidateForm.errors.payments }}</p>
          <div v-if="remaining > 0" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
            Saldo pendiente: <strong>${{ money(remaining) }}</strong>
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
            <p v-for="(msg, key) in liquidateForm.errors" :key="key" v-show="String(key).startsWith('payments.') && String(key).endsWith('.amount')" class="text-xs text-red-600">
              {{ msg }}
            </p>
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

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Reimpresion manual</h2>

          <button
            type="button"
            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isReprintingCreated"
            @click="reprintCreatedTicket"
          >
            {{ isReprintingCreated ? 'Imprimiendo...' : 'Reimprimir ticket de creacion' }}
          </button>

          <button
            type="button"
            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isReprintingClosed || layaway.status !== 'liquidated'"
            @click="reprintClosedTicket"
          >
            {{ isReprintingClosed ? 'Imprimiendo...' : 'Reimprimir ticket de liquidacion' }}
          </button>

          <p class="text-xs text-gray-500">
            La reimpresion consulta solo endpoints JSON y no altera saldos ni inventario.
          </p>
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
