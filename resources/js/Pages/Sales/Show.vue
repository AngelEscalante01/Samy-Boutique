<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import SaleStatusBadge from '@/Components/Sales/SaleStatusBadge.vue'
import PaymentsList    from '@/Components/Sales/PaymentsList.vue'
import { printSale } from '@/services/printSale'

const props = defineProps({
  sale: { type: Object, required: true },
  can:  { type: Object, default: () => ({}) },
})

const flash  = computed(() => usePage().props.flash ?? {})
const isPaid = computed(() => props.sale.status === 'completed')
const isPrinting = ref(false)
const printFeedback = ref(null) // { type: 'success' | 'error', text: string }

async function handlePrintSale() {
  if (isPrinting.value) return

  isPrinting.value = true
  printFeedback.value = null

  const result = await printSale(props.sale.id)

  printFeedback.value = {
    type: result.ok ? 'success' : 'error',
    text: result.message,
  }

  isPrinting.value = false
}

// ── Cancel modal ─────────────────────────────────────────────────────────────
const showCancelModal = ref(false)
const cancelForm = useForm({
  cancel_reason: '',
  cancel_type: 'ajuste',
  return_condition: null,
  inventory_action: 'no_regresar',
})

const isReturnCancel = computed(() => cancelForm.cancel_type === 'devolucion')

function submitCancel() {
  const reason = (cancelForm.cancel_reason ?? '').trim()

  cancelForm.clearErrors()

  if (!reason || reason.length < 5) {
    cancelForm.setError('cancel_reason', 'El motivo es obligatorio (mínimo 5 caracteres).')
    return
  }

  cancelForm.transform(() => ({
    cancel_reason: reason,
    cancel_type: cancelForm.cancel_type,
    inventory_action: cancelForm.inventory_action,
    return_condition: cancelForm.cancel_type === 'devolucion' ? cancelForm.return_condition : null,
  })).patch(route('sales.cancel', props.sale.id), {
    preserveScroll: true,
    onSuccess: () => {
      showCancelModal.value = false
      cancelForm.reset()
      cancelForm.clearErrors()
    }
  })
}

function closeCancelModal() {
  showCancelModal.value = false
  cancelForm.reset()
  cancelForm.clearErrors()
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function money(v) { return Number(v ?? 0).toFixed(2) }
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
function thumbUrl(product) {
  const img = product?.images?.[0]
  return img ? '/storage/' + img.path : null
}

// ── Totals ────────────────────────────────────────────────────────────────────
const hasItemDiscount  = computed(() => props.sale.items?.some(i => Number(i.discount_amount) > 0))
const hasCoupon        = computed(() => !!props.sale.coupon_code)
const hasLoyalty       = computed(() => props.sale.loyalty_applied && Number(props.sale.loyalty_discount_total) > 0)
const totalDiscounts   = computed(() =>
  Number(props.sale.discount_total ?? 0) +
  Number(props.sale.coupon_discount_total ?? 0) +
  Number(props.sale.loyalty_discount_total ?? 0)
)
</script>

<template>
  <Head :title="'Venta #' + sale.id" />

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- ── Header ───────────────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <Link :href="route('sales.index')" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </Link>
        <div>
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900 font-mono">Venta #{{ sale.id }}</h1>
            <SaleStatusBadge :status="sale.status" size="md" />
          </div>
          <p class="text-sm text-gray-500 mt-0.5">{{ fmtDate(sale.created_at) }}</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Print (placeholder) -->
        <button
          type="button"
          @click="handlePrintSale"
          :disabled="isPrinting"
          class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition"
          :class="isPrinting ? 'opacity-60 cursor-not-allowed' : ''"
        >
          <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.056 48.056 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
          </svg>
          {{ isPrinting ? 'Imprimiendo...' : 'Imprimir ticket' }}
        </button>

        <!-- Cancel -->
        <button v-if="can.cancel && isPaid"
          @click="showCancelModal = true"
          class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
          Cancelar venta
        </button>
      </div>
    </div>

    <!-- ── Flash ─────────────────────────────────────────────────────────────── -->
    <div v-if="flash.success"
      class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
      <svg class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
      </svg>
      {{ flash.success }}
    </div>

    <div
      v-if="printFeedback"
      class="rounded-xl border px-4 py-3 text-sm flex items-center gap-2"
      :class="printFeedback.type === 'success'
        ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-red-50 border-red-200 text-red-800'"
    >
      <svg v-if="printFeedback.type === 'success'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
      </svg>
      <svg v-else class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.051 3.378c.866-1.5 3.032-1.5 3.898 0l7.354 12.748ZM12 15.75h.007v.008H12v-.008Z" />
      </svg>
      {{ printFeedback.text }}
    </div>

    <!-- ── Cancellation reason (if cancelled) ───────────────────────────────── -->
    <div v-if="sale.status === 'cancelled'"
      class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
      <div><span class="font-semibold">Motivo:</span> {{ sale.cancel_reason ?? sale.cancellation_reason ?? '—' }}</div>
      <div class="mt-1 text-red-700/90">
        Tipo: <span class="font-medium capitalize">{{ sale.cancel_type ?? '—' }}</span>
        · Acción inventario: <span class="font-medium">{{ sale.inventory_action ?? '—' }}</span>
        <template v-if="sale.return_condition"> · Condición: <span class="font-medium">{{ sale.return_condition }}</span></template>
      </div>
    </div>

    <!-- ── Meta info row ─────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Cajero -->
      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Cajero</p>
        <div class="flex items-center gap-2 mt-1">
          <div class="h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <span class="text-xs font-bold text-gray-600">{{ sale.creator?.name?.charAt(0).toUpperCase() ?? '?' }}</span>
          </div>
          <p class="text-sm font-semibold text-gray-800">{{ sale.creator?.name ?? '—' }}</p>
        </div>
      </div>

      <!-- Cliente -->
      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Cliente</p>
        <div v-if="sale.customer" class="flex items-center gap-2 mt-1">
          <div class="h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <span class="text-xs font-bold text-gray-600">{{ sale.customer.name.charAt(0).toUpperCase() }}</span>
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-800">{{ sale.customer.name }}</p>
            <p v-if="sale.customer.phone" class="text-xs text-gray-400 font-mono">{{ sale.customer.phone }}</p>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 italic mt-1">Sin cliente</p>
      </div>

      <!-- Total destacado -->
      <div class="rounded-xl bg-gray-900 p-4 shadow-sm text-white">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Total de la venta</p>
        <p class="text-3xl font-bold mt-1">${{ money(sale.total) }}</p>
        <p v-if="totalDiscounts > 0" class="text-xs text-gray-400 mt-1">
          Ahorro total: ${{ money(totalDiscounts) }}
        </p>
      </div>
    </div>

    <!-- ── Main content grid ─────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left: Products + Payments -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Products table -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">
              Productos
              <span class="ml-1 text-gray-400 font-normal">({{ sale.items?.length ?? 0 }})</span>
            </h2>
          </div>

          <table class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="py-2.5 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Precio</th>
                <th v-if="hasItemDiscount" class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Descuento</th>
                <th class="py-2.5 pl-3 pr-5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total línea</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="item in sale.items" :key="item.id" class="group">
                <!-- Producto -->
                <td class="py-3 pl-5 pr-3">
                  <div class="flex items-center gap-3">
                    <div class="h-10 w-10 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                      <img v-if="thumbUrl(item.product)"
                        :src="thumbUrl(item.product)" :alt="item.name"
                        class="h-full w-full object-cover" />
                      <div v-else class="h-full w-full flex items-center justify-center text-gray-300">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                      </div>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-gray-800 truncate">{{ item.name }}</p>
                      <div class="flex flex-wrap gap-1 mt-0.5">
                        <span v-if="item.sku" class="text-xs text-gray-400 font-mono">{{ item.sku }}</span>
                        <span v-if="item.product?.size" class="text-xs text-gray-400">· {{ item.product.size.name }}</span>
                        <span v-if="item.product?.color" class="text-xs text-gray-400">/ {{ item.product.color.name }}</span>
                      </div>
                    </div>
                  </div>
                </td>
                <!-- Precio unitario -->
                <td class="px-3 py-3 text-right text-sm text-gray-700">
                  ${{ money(item.unit_price) }}
                  <span v-if="item.quantity > 1" class="block text-xs text-gray-400">× {{ item.quantity }}</span>
                </td>
                <!-- Descuento línea -->
                <td v-if="hasItemDiscount" class="px-3 py-3 text-right text-sm">
                  <span v-if="Number(item.discount_amount) > 0" class="text-red-500">
                    − ${{ money(item.discount_amount) }}
                  </span>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <!-- Total línea -->
                <td class="py-3 pl-3 pr-5 text-right text-sm font-bold text-gray-900">
                  ${{ money(item.line_total) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Payments -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700">Pagos registrados</h2>
          <PaymentsList :payments="sale.payments ?? []" />
        </div>
      </div>

      <!-- Right: Totals -->
      <div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3 sticky top-6">
          <h2 class="text-sm font-semibold text-gray-700 pb-2 border-b border-gray-100">Resumen de totales</h2>

          <!-- Subtotal -->
          <div class="flex justify-between items-center text-sm">
            <span class="text-gray-500">Subtotal</span>
            <span class="font-medium text-gray-900">${{ money(sale.subtotal) }}</span>
          </div>

          <!-- Descuento manual -->
          <div v-if="Number(sale.discount_total) > 0"
            class="flex justify-between items-center text-sm">
            <span class="text-gray-500">
              Descuento manual
              <span v-if="sale.global_discount_type === 'percent'" class="text-xs text-gray-400">
                ({{ sale.global_discount_value }}%)
              </span>
            </span>
            <span class="text-red-500 font-medium">− ${{ money(sale.discount_total) }}</span>
          </div>

          <!-- Cupón -->
          <div v-if="hasCoupon && Number(sale.coupon_discount_total) > 0"
            class="flex justify-between items-center text-sm">
            <span class="text-gray-500 flex items-center gap-1">
              Cupón
              <span class="font-mono text-xs bg-amber-50 text-amber-700 rounded px-1.5 py-0.5 ring-1 ring-amber-200">
                {{ sale.coupon_code }}
              </span>
            </span>
            <span class="text-red-500 font-medium">− ${{ money(sale.coupon_discount_total) }}</span>
          </div>

          <!-- Fidelidad -->
          <div v-if="hasLoyalty"
            class="flex justify-between items-center text-sm">
            <span class="text-gray-500 flex items-center gap-1">
              Descuento fidelidad
              <span class="text-xs bg-purple-50 text-purple-700 rounded px-1.5 py-0.5 ring-1 ring-purple-200 font-medium">VIP</span>
            </span>
            <span class="text-red-500 font-medium">− ${{ money(sale.loyalty_discount_total) }}</span>
          </div>

          <!-- Divider + Total -->
          <div class="border-t border-gray-200 pt-3 mt-1">
            <div class="flex justify-between items-center">
              <span class="text-base font-bold text-gray-900">TOTAL</span>
              <span class="text-2xl font-bold text-gray-900">${{ money(sale.total) }}</span>
            </div>
            <p v-if="totalDiscounts > 0" class="text-right text-xs text-emerald-600 mt-1">
              Ahorro total: ${{ money(totalDiscounts) }}
            </p>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Cancel modal ──────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showCancelModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-5">
        <!-- Modal header -->
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
            <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-900">Cancelar venta #{{ sale.id }}</h3>
            <p class="text-sm text-gray-500 mt-0.5">Esta acción es irreversible. La venta quedará marcada como cancelada.</p>
          </div>
        </div>

        <p v-if="cancelForm.errors.sale" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {{ cancelForm.errors.sale }}
        </p>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Motivo de cancelación <span class="text-red-500">*</span>
          </label>
          <textarea v-model="cancelForm.cancel_reason" rows="3"
            placeholder="Ej. Devolución por talla incorrecta..."
            class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm text-gray-800 placeholder-gray-400 resize-none focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          <p v-if="cancelForm.errors.cancel_reason" class="mt-1 text-xs text-red-600">{{ cancelForm.errors.cancel_reason }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Tipo de cancelación <span class="text-red-500">*</span>
          </label>
          <select
            v-model="cancelForm.cancel_type"
            class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm text-gray-800 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          >
            <option value="ajuste">Ajuste</option>
            <option value="devolucion">Devolución</option>
          </select>
          <p v-if="cancelForm.errors.cancel_type" class="mt-1 text-xs text-red-600">{{ cancelForm.errors.cancel_type }}</p>
        </div>

        <div v-if="isReturnCancel" class="space-y-2">
          <p class="text-sm font-medium text-gray-700">Condición del producto <span class="text-red-500">*</span></p>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="cancelForm.return_condition" type="radio" value="buena" class="text-gray-900 focus:ring-gray-400" />
            Buena
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="cancelForm.return_condition" type="radio" value="danada" class="text-gray-900 focus:ring-gray-400" />
            Dañada
          </label>
          <p v-if="cancelForm.errors.return_condition" class="text-xs text-red-600">{{ cancelForm.errors.return_condition }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Acción de inventario <span class="text-red-500">*</span>
          </label>
          <select
            v-model="cancelForm.inventory_action"
            class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm text-gray-800 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          >
            <option value="regresar_disponible">Regresar al inventario (disponible)</option>
            <option value="marcar_danado">Marcar como dañada / no vendible</option>
            <option value="no_regresar">No regresar (solo cancelar)</option>
          </select>
          <p v-if="cancelForm.errors.inventory_action" class="mt-1 text-xs text-red-600">{{ cancelForm.errors.inventory_action }}</p>
        </div>

        <div class="flex gap-3 justify-end pt-1">
          <button @click="closeCancelModal"
            class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            Volver
          </button>
          <button @click="submitCancel" :disabled="cancelForm.processing"
            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition disabled:opacity-50">
            {{ cancelForm.processing ? 'Cancelando...' : 'Confirmar cancelación' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
