<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { printLayawayClosed, printLayawayCreated, printLayawayPayment, printSale } from '@/services/printSale'

const props = defineProps({
  layaway: { type: Object, required: true },
  can:     { type: Object, default: () => ({}) },
  customerPhotoUrl: { type: String, default: null },
})

const flash = computed(() => usePage().props.flash ?? {})
const page = usePage()
const isOpen = computed(() => props.layaway.status === 'open')
const pendingDelivery = computed(() => props.layaway.status === 'liquidated' && props.layaway.delivery_tracking_enabled && !props.layaway.delivered_at)
const canEditItems = computed(() => isOpen.value || pendingDelivery.value)
const canAddPayment = computed(() => canEditItems.value && Number(props.layaway.balance) > 0)
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
function fmtDateShort(d) { if (!d) return '—'; const text = String(d); return new Date(/^\d{4}-\d{2}-\d{2}$/.test(text) ? `${text}T12:00:00` : text).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }
function thumbUrl(product) { const img = product?.images?.[0]; return img?.url ?? img?.image_url ?? (img?.path ? '/storage/' + img.path : null) }

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

const addPaymentForm = useForm({ method: 'cash', amount: '', reference: '', payer_name: '' })
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

      const printResult = await printLayawayPayment(props.layaway.id, paymentId, { automatic: true })
      if (printResult.ok) {
        paymentPrintMessage.value = {
          type: 'success',
          text: printResult.mode === 'browser' ? 'Abono registrado. Puedes abrir su comprobante en el historial de pagos.' : 'Abono guardado e impresion enviada correctamente.',
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
const refundStorageKey = `samy_layaway_refund_${page.props?.auth?.user?.id ?? 'guest'}_${props.layaway.id}`
function readRefundAttempt() {
  if (typeof window === 'undefined') return null
  try {
    const saved = JSON.parse(window.localStorage.getItem(refundStorageKey) || 'null')
    return saved?.key && saved?.payload ? saved : null
  } catch { return null }
}
const pendingRefundAttempt = ref(readRefundAttempt())
const refundAttemptMismatch = ref(false)
function clearRefundAttempt() {
  window.localStorage.removeItem(refundStorageKey)
  pendingRefundAttempt.value = null
}
function discardRefundAttemptAfterReview() {
  if (!window.confirm('Confirma que revisaste el historial del apartado y verificaste si la devolución se registró.')) return
  clearRefundAttempt()
  refundAttemptMismatch.value = false
}
const refundForm = useForm({ operation_key: null, amount: '', method: props.layaway.payments?.at(-1)?.method ?? 'cash', reference: '', reason: '' })
function submitRefund() {
  refundAttemptMismatch.value = false
  const payload = JSON.stringify({ amount: refundForm.amount, method: refundForm.method, reference: refundForm.reference, reason: refundForm.reason })
  let attempt
  try {
    attempt = readRefundAttempt()
    if (!attempt) {
      const random = window.crypto?.randomUUID?.().replaceAll('-', '')
        ?? `${Math.random().toString(36).slice(2)}${Math.random().toString(36).slice(2)}`
      attempt = { key: `refund:${Date.now()}:${random}`, payload }
      window.localStorage.setItem(refundStorageKey, JSON.stringify(attempt))
    }
    pendingRefundAttempt.value = attempt
  } catch { refundAttemptMismatch.value = true; return }
  if (attempt.payload !== payload) { refundAttemptMismatch.value = true; return }
  refundForm.operation_key = attempt.key
  refundForm.post(route('layaways.refunds.store', props.layaway.id), {
    preserveScroll: true,
    onError: () => { if (!refundForm.errors.operation_key) clearRefundAttempt() },
    onSuccess: () => { clearRefundAttempt(); refundForm.reset() },
  })
}
const deliveryForm = useForm({})
function confirmDelivery() {
  deliveryForm.post(route('layaways.deliver', props.layaway.id), { preserveScroll: true })
}
const liquidateForm = useForm({ payments: [{ method: 'cash', amount: '', payer_name: '' }] })
watch(remaining, (val) => { if (liquidateForm.payments.length === 1) liquidateForm.payments[0].amount = val > 0 ? val.toFixed(2) : '' }, { immediate: true })
function addLiquidatePayment() { liquidateForm.payments.push({ method: 'cash', amount: '', payer_name: '' }) }
function removeLiquidatePayment(i) { if (liquidateForm.payments.length > 1) liquidateForm.payments.splice(i, 1) }
function submitLiquidate() {
  const payments = liquidateForm.payments
    .map((p) => ({
      method: p.method,
      amount: Number(p.amount || 0),
      reference: p.reference ?? null,
      payer_name: p.payer_name ?? null,
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

      const printLayawayResult = await printLayawayClosed(layawayId, { automatic: true })
      if (!printLayawayResult.ok) {
        paymentPrintMessage.value = {
          type: 'error',
          text: `Liquidacion guardada, pero fallo la impresion del ticket: ${printLayawayResult.message}`,
        }
        return
      }

      if (printLayawayResult.mode === 'browser') {
        paymentPrintMessage.value = { type: 'success', text: 'Liquidación guardada. Puedes abrir el comprobante desde esta ficha.' }
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
          ? 'Liquidación guardada. El ticket de venta final está disponible en el detalle de la venta.'
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
        ? result.message
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
        ? result.message
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
        ? result.message
        : `No se pudo reimprimir ticket de liquidacion: ${result.message}`,
    }
  } finally {
    isReprintingClosed.value = false
  }
}
</script>

<template>
  <Head :title="'Apartado #' + layaway.id" />

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 app-card-stack app-page">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 app-heading">
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
      <Link v-if="canEditItems && can.edit" :href="route('layaways.edit', layaway.id)" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold">Cambiar cliente o prendas</Link>
      <button v-if="pendingDelivery && can.deliver && Number(layaway.balance) === 0 && Number(layaway.excess) === 0" type="button" :disabled="deliveryForm.processing" @click="confirmDelivery" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Confirmar entrega</button>
      <button v-if="isOpen && can.cancel" @click="showCancelConfirm = true"
        class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        Cancelar apartado
      </button>
    </div>

    <!-- Flash -->
    <div v-if="flash.success" class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2 app-surface">
      <svg class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
      {{ flash.success }}
    </div>

    <div
      v-if="paymentPrintMessage"
      class="rounded-xl border px-4 py-3 text-sm app-surface"
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
      class="rounded-xl border px-4 py-3 text-sm app-surface"
      :class="vigenciaMessage.type === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : vigenciaMessage.type === 'warning'
          ? 'border-amber-200 bg-amber-50 text-amber-800'
          : 'border-red-200 bg-red-50 text-red-800'"
    >
      {{ vigenciaMessage.text }}
    </div>

    <div v-if="showExpiredWarning" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 app-surface">
      Este apartado esta vencido. Revisa con el cliente antes de registrar nuevos abonos.
    </div>

    <div v-if="showDueTodayWarning" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 app-surface">
      Este apartado vence hoy. Considera renovar vigencia o liquidarlo hoy mismo.
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 app-card-grid">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 app-surface">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">${{ money(layaway.total) }}</p>
        <p v-if="Number(layaway.manual_discount_total) > 0" class="mt-1 text-xs text-stone-500">Antes de descuento: ${{ money(layaway.subtotal) }}</p>
      </div>
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 app-surface">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Abonado</p>
        <p class="mt-1 text-2xl font-bold text-emerald-600">${{ money(layaway.paid_total) }}</p>
        <p v-if="Number(layaway.refunded_total) > 0" class="mt-1 text-xs text-stone-500">Devuelto: ${{ money(layaway.refunded_total) }} · aplicado: ${{ money(layaway.applied_total) }}</p>
      </div>
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 app-surface">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Saldo</p>
        <p class="mt-1 text-2xl font-bold" :class="remaining > 0 ? 'text-amber-600' : 'text-gray-400'">${{ money(layaway.balance) }}</p>
      </div>
    </div>

    <section v-if="Number(layaway.excess) > 0 || layaway.refunds?.length || pendingRefundAttempt" class="rounded-xl border border-amber-200 bg-amber-50 p-5 app-surface">
      <h2 class="text-sm font-semibold text-amber-900">Excedente y devoluciones</h2>
      <p class="mt-1 text-sm">Excedente pendiente: <strong>{{ money(layaway.excess) }}</strong></p>
      <p v-if="pendingRefundAttempt" class="mt-2 text-xs text-amber-900">
        Hay una devolución por confirmar. Reintenta solo con los mismos datos o revisa el historial antes de iniciar otra.
        <button type="button" class="ml-2 font-semibold underline" @click="discardRefundAttemptAfterReview">Ya revisé; iniciar otra</button>
      </p>
      <p v-if="refundAttemptMismatch" class="mt-2 text-xs font-semibold text-red-700">Los datos cambiaron o no se pudo guardar el intento en este navegador. Revisa el historial antes de continuar.</p>
      <form v-if="can.refund && Number(layaway.excess) > 0" class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="submitRefund">
        <label class="text-xs">Monto<input v-model="refundForm.amount" type="number" min="0.01" :max="layaway.excess" step="0.01" required class="mt-1 block w-32 rounded-lg border-stone-300 text-sm"></label>
        <label class="text-xs">Método<select v-model="refundForm.method" class="mt-1 block rounded-lg border-stone-300 text-sm"><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="other">Otro</option></select></label>
        <label class="text-xs">Referencia<input v-model="refundForm.reference" class="mt-1 block rounded-lg border-stone-300 text-sm"></label>
        <label class="min-w-48 flex-1 text-xs">Motivo<input v-model="refundForm.reason" required maxlength="500" class="mt-1 block w-full rounded-lg border-stone-300 text-sm"></label>
        <button type="submit" :disabled="refundForm.processing" class="rounded-lg bg-amber-900 px-4 py-2 text-sm font-semibold text-white">Registrar devolución</button>
      </form>
      <p v-if="Object.keys(refundForm.errors).length" class="mt-2 text-xs text-red-700">{{ Object.values(refundForm.errors).join(' ') }}</p>
      <ul v-if="layaway.refunds?.length" class="mt-3 space-y-1 text-xs text-stone-700"><li v-for="refund in layaway.refunds" :key="refund.id">{{ fmtDate(refund.refunded_at) }} · {{ money(refund.amount) }} · {{ methodLabel[refund.method] ?? refund.method }} · {{ refund.reason }}</li></ul>
    </section>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 app-surface">
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 app-card-grid">
      <!-- Left column -->
      <div class="min-w-0 lg:col-span-2 space-y-6 app-card-stack">
        <!-- Customer -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 app-surface">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Informacion del cliente</h2>
          <div v-if="layaway.customer" class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
              <img v-if="customerPhotoUrl" :src="customerPhotoUrl" :alt="`Foto de ${layaway.customer.name}`" class="h-10 w-10 rounded-full object-cover">
              <span v-else class="text-sm font-bold text-gray-600">{{ layaway.customer.name.charAt(0).toUpperCase() }}</span>
            </div>
            <div>
              <p class="font-semibold text-gray-800">{{ layaway.customer.name }}</p>
              <p v-if="layaway.customer.phone" class="text-sm text-gray-500">{{ layaway.customer.phone }}</p>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 italic">Sin cliente asignado</p>
        </div>

        <!-- Products -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 app-surface">
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
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 app-surface">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Historial de abonos</h2>
          <div v-if="layaway.payments.length === 0" class="py-6 text-center text-sm text-gray-400">Sin abonos registrados</div>
          <div v-else class="max-w-full overflow-x-auto" role="region" aria-label="Historial de abonos" tabindex="0">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Metodo</th>
                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Pagó / registró</th>
                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Referencia</th>
                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Monto</th>
                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Reimpresion</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="pmt in layaway.payments" :key="pmt.id">
                <td class="py-2 text-gray-500">{{ fmtDateShort(pmt.created_at) }}</td>
                <td class="py-2 text-gray-700">{{ methodLabel[pmt.method] ?? pmt.method }}</td>
                <td class="py-2 text-gray-500 text-xs">{{ pmt.payer_name || 'Sin nombre' }}<span v-if="pmt.creator?.name"> / {{ pmt.creator.name }}</span></td>
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
      </div>

      <!-- Right column: actions -->
      <div class="space-y-4 app-card-stack">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3 app-surface app-card-stack">
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

          <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 app-surface">
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
        <div v-if="canAddPayment" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4 app-surface">
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
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Quién paga <span class="text-gray-400">(opcional; por defecto el cliente actual)</span></label><input v-model="addPaymentForm.payer_name" maxlength="180" class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm" /></div>
          </div>
          <button @click="submitAddPayment" :disabled="addPaymentForm.processing"
            class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition disabled:opacity-50">
            {{ addPaymentForm.processing ? 'Guardando...' : 'Registrar abono' }}
          </button>
        </div>

        <!-- Liquidate -->
        <div v-if="isOpen" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4 app-surface app-card-stack">
          <h2 class="text-sm font-semibold text-gray-700">Liquidar apartado</h2>
          <p v-if="liquidateForm.errors.payments" class="text-xs text-red-600">{{ liquidateForm.errors.payments }}</p>
          <div v-if="remaining > 0" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800 app-surface">
            Saldo pendiente: <strong>${{ money(remaining) }}</strong>
          </div>
          <div v-else class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-xs text-emerald-800 app-surface">
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
            <input v-for="(pmt, i) in liquidateForm.payments" :key="`payer-${i}`" v-model="pmt.payer_name" maxlength="180" placeholder="Quién paga (opcional; por defecto cliente actual)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
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
        <div v-if="!isOpen" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 text-sm text-gray-500 space-y-2 app-surface">
          <p v-if="layaway.status === 'liquidated'">Liquidado el <strong>{{ fmtDateShort(layaway.liquidated_at) }}</strong>. {{ layaway.delivered_at ? `Entregado el ${fmtDate(layaway.delivered_at)}.` : (pendingDelivery ? 'Pendiente de entrega.' : '') }}</p>
          <p v-if="layaway.status === 'cancelled'">Cancelado el <strong>{{ fmtDateShort(layaway.cancelled_at) }}</strong>.</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3 app-surface">
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
            Abre el comprobante para imprimirlo o guardarlo como PDF.
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
