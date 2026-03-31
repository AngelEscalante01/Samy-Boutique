<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { printLayawayCreated } from '@/services/printSale'

const props = defineProps({
  products:  { type: Array, required: true },
  customers: { type: Array, required: true },
})

const page = usePage()
const statusMessage = ref(null) // { type: 'success' | 'error' | 'warning', text: string }

const productQuery = ref('')
const filteredProducts = computed(() => {
  const q = productQuery.value.toLowerCase().trim()
  if (!q) return props.products
  return props.products.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.sku && p.sku.toLowerCase().includes(q)) ||
    (p.variants || []).some(v => (v.sku || '').toLowerCase().includes(q))
  )
})

const cart = ref([])
const inCartIds = computed(() => new Set(cart.value.map(i => i.variant.id)))
function firstVariantId(product) {
  return (product.variants || []).find(v => Number(v.stock || 0) > 0)?.id ?? null
}
function addToCart(product) {
  const variant = (product.variants || []).find(v => Number(v.stock || 0) > 0)
  if (!variant) return
  if (!inCartIds.value.has(variant.id)) cart.value.push({ product, variant, qty: 1 })
}
function removeFromCart(variantId) { cart.value = cart.value.filter(i => i.variant.id !== variantId) }
function incQty(variantId) {
  const item = cart.value.find(i => i.variant.id === variantId)
  if (!item) return
  if (item.qty < Number(item.variant.stock || 0)) item.qty += 1
}
function decQty(variantId) {
  const item = cart.value.find(i => i.variant.id === variantId)
  if (!item || item.qty <= 1) return
  item.qty -= 1
}
const cartTotal = computed(() => cart.value.reduce((s, item) => s + (Number(item.variant.sale_price_effective || item.product.sale_price) * Number(item.qty || 1)), 0).toFixed(2))

const customerQuery = ref('')
const selectedCustomer = ref(null)
const customerSuggestions = computed(() => {
  const q = customerQuery.value.toLowerCase().trim()
  if (!q || selectedCustomer.value) return []
  return props.customers.filter(c =>
    c.name.toLowerCase().includes(q) || (c.phone && c.phone.includes(q))
  ).slice(0, 6)
})
function selectCustomer(c) { selectedCustomer.value = c; customerQuery.value = c.name }
function clearCustomer() { selectedCustomer.value = null; customerQuery.value = '' }

const initialPaymentAmount = ref('')
const initialPaymentMethod = ref('cash')
const vigenciaOpciones = [7, 15, 30, 45, 60]
const vigenciaSeleccion = ref('30')
const vigenciaManual = ref('')

const vigenciaDias = computed(() => {
  if (vigenciaSeleccion.value === 'manual') {
    return Number(vigenciaManual.value || 0)
  }

  return Number(vigenciaSeleccion.value || 0)
})

const fechaApartadoPreview = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return today
})

const fechaVencimientoPreview = computed(() => {
  const dias = vigenciaDias.value
  if (!Number.isInteger(dias) || dias <= 0) return null

  const due = new Date()
  due.setHours(0, 0, 0, 0)
  due.setDate(due.getDate() + dias)
  return due
})

const form = useForm({ customer_id: null, vigencia_dias: null, items: [], payments: [] })

function submit() {
  statusMessage.value = null

  if (!Number.isInteger(vigenciaDias.value) || vigenciaDias.value <= 0) {
    statusMessage.value = {
      type: 'error',
      text: 'La vigencia debe ser un numero entero mayor a 0 dias.',
    }
    return
  }

  form.customer_id = selectedCustomer.value?.id ?? null
  form.vigencia_dias = vigenciaDias.value
  form.items = cart.value.map(item => ({ variant_id: item.variant.id, qty: Number(item.qty || 1) }))
  const amt = parseFloat(initialPaymentAmount.value)
  form.payments = (!isNaN(amt) && amt > 0) ? [{ method: initialPaymentMethod.value, amount: amt }] : []

  form.post(route('layaways.store'), {
    onError: () => {
      statusMessage.value = {
        type: 'error',
        text: 'No se pudo guardar el apartado. Verifica los datos e intenta nuevamente.',
      }
    },
    onSuccess: async (pageResponse) => {
      const layawayId = pageResponse?.props?.flash?.print_layaway_id
        ?? page.props?.flash?.print_layaway_id
        ?? null

      if (!layawayId) {
        statusMessage.value = {
          type: 'warning',
          text: 'Apartado guardado, pero no se encontro el folio para imprimir automaticamente.',
        }
        return
      }

      const printResult = await printLayawayCreated(layawayId)
      if (printResult.ok) {
        statusMessage.value = {
          type: 'success',
          text: 'Apartado guardado e impresion enviada correctamente.',
        }
        return
      }

      statusMessage.value = {
        type: 'error',
        text: `Apartado guardado, pero fallo la impresion: ${printResult.message}`,
      }
    },
  })
}

function money(v) { return Number(v).toFixed(2) }
function fmtDateShort(d) {
  if (!d) return '—'
  return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

function storageBaseUrl() {
  if (typeof window === 'undefined') return '/storage/'
  const marker = '/layaways/'
  const path = window.location.pathname
  const cut = path.indexOf(marker)
  const appBase = cut >= 0 ? path.slice(0, cut) : ''
  return `${window.location.origin}${appBase}/storage/`
}

function thumbUrl(product) {
  const img = product.images?.[0]
  return img?.url ?? (img?.path ? storageBaseUrl() + img.path : null)
}

function variantLabel(variant) {
  return `${variant?.size?.name ?? 'Sin talla'} · ${variant?.color?.name ?? 'Sin color'} (${variant?.stock ?? 0})`
}
</script>

<template>
  <Head title="Nuevo apartado" />

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('layaways.index')" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <h1 class="text-2xl font-bold text-gray-900">Nuevo apartado</h1>
    </div>

    <div
      v-if="statusMessage"
      class="mb-4 rounded-xl border px-4 py-3 text-sm"
      :class="statusMessage.type === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : statusMessage.type === 'warning'
          ? 'border-amber-200 bg-amber-50 text-amber-800'
          : 'border-red-200 bg-red-50 text-red-800'"
    >
      {{ statusMessage.text }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Product grid -->
      <div class="lg:col-span-2 space-y-4">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Productos disponibles</h2>
          <div class="relative mb-4">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
              fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <input v-model="productQuery" type="text" placeholder="Buscar por nombre o SKU..."
              class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>
          <div v-if="filteredProducts.length === 0" class="py-10 text-center text-sm text-gray-400">Sin productos disponibles</div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[520px] overflow-y-auto pr-1">
            <button v-for="p in filteredProducts" :key="p.id" @click="addToCart(p)"
              :disabled="!firstVariantId(p) || inCartIds.has(firstVariantId(p))"
              class="relative flex flex-col rounded-xl border text-left transition focus:outline-none"
              :class="inCartIds.has(firstVariantId(p)) ? 'border-emerald-300 bg-emerald-50 opacity-80 cursor-default' : 'border-gray-200 bg-white hover:border-gray-900 hover:shadow-sm'">
              <div class="aspect-square w-full overflow-hidden rounded-t-xl bg-gray-100">
                <img v-if="thumbUrl(p)" :src="thumbUrl(p)" :alt="p.name" class="h-full w-full object-cover" />
                <div v-else class="flex h-full items-center justify-center text-gray-300">
                  <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                  </svg>
                </div>
              </div>
              <div v-if="inCartIds.has(firstVariantId(p))" class="absolute inset-0 flex items-center justify-center rounded-xl bg-emerald-600/20">
                <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </div>
              <div class="p-2 space-y-0.5">
                <p class="text-xs font-semibold text-gray-800 line-clamp-2 leading-tight">{{ p.name }}</p>
                <p class="mt-1 text-xs text-gray-500 line-clamp-2">{{ variantLabel((p.variants || [])[0]) }}</p>
                <p class="text-sm font-bold text-gray-900 mt-1">${{ money((p.variants || [])[0]?.sale_price_effective ?? p.sale_price) }}</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Summary -->
      <div class="space-y-4">
        <!-- Customer -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Cliente</h2>
          <div class="relative">
            <input v-model="customerQuery" type="text" placeholder="Buscar cliente..."
              :disabled="!!selectedCustomer"
              class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400 disabled:bg-gray-50" />
            <ul v-if="customerSuggestions.length"
              class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-md max-h-48 overflow-y-auto">
              <li v-for="c in customerSuggestions" :key="c.id" @click="selectCustomer(c)"
                class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                <span class="font-medium text-gray-800">{{ c.name }}</span>
                <span v-if="c.phone" class="ml-2 text-gray-400 text-xs">{{ c.phone }}</span>
              </li>
            </ul>
          </div>
          <div v-if="selectedCustomer" class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
            <div>
              <p class="text-sm font-semibold text-gray-800">{{ selectedCustomer.name }}</p>
              <p v-if="selectedCustomer.phone" class="text-xs text-gray-500">{{ selectedCustomer.phone }}</p>
            </div>
            <button @click="clearCustomer" class="text-gray-400 hover:text-red-500 transition">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <p class="text-xs text-gray-400">Opcional</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Vigencia (dias)</h2>
          <select v-model="vigenciaSeleccion"
            class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400">
            <option v-for="dias in vigenciaOpciones" :key="dias" :value="String(dias)">{{ dias }} dias</option>
            <option value="manual">Otro (captura manual)</option>
          </select>

          <div v-if="vigenciaSeleccion === 'manual'">
            <label class="mb-1 block text-xs font-medium text-gray-600">Dias de vigencia</label>
            <input v-model="vigenciaManual" type="number" min="1" step="1" placeholder="Ej. 21"
              class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <p v-if="form.errors.vigencia_dias" class="text-xs text-red-600">{{ form.errors.vigencia_dias }}</p>

          <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <p>Fecha del apartado: <strong>{{ fmtDateShort(fechaApartadoPreview) }}</strong></p>
            <p>Vigencia: <strong>{{ Number.isInteger(vigenciaDias) && vigenciaDias > 0 ? vigenciaDias + ' dias' : 'Invalida' }}</strong></p>
            <p>Vence el: <strong>{{ fechaVencimientoPreview ? fmtDateShort(fechaVencimientoPreview) : 'Fecha invalida' }}</strong></p>
          </div>
        </div>

        <!-- Cart -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Carrito <span class="text-gray-400 font-normal">({{ cart.length }})</span></h2>
          <p v-if="form.errors.items" class="text-xs text-red-600">{{ form.errors.items }}</p>
          <div v-if="cart.length === 0" class="py-6 text-center text-sm text-gray-400">Agrega productos desde la lista</div>
          <ul v-else class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
            <li v-for="item in cart" :key="item.variant.id" class="flex items-center gap-3 py-2">
              <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-lg bg-gray-100">
                <img v-if="thumbUrl(item.product)" :src="thumbUrl(item.product)" :alt="item.product.name" class="h-full w-full object-cover" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-gray-800 truncate">{{ item.product.name }}</p>
                <p class="text-xs text-gray-400">{{ item.variant.size?.name }} · {{ item.variant.color?.name }}</p>
                <div class="mt-1 inline-flex items-center gap-2 text-xs">
                  <button type="button" class="rounded border border-gray-200 px-1.5" @click="decQty(item.variant.id)">-</button>
                  <span>{{ item.qty }}</span>
                  <button type="button" class="rounded border border-gray-200 px-1.5" @click="incQty(item.variant.id)">+</button>
                </div>
              </div>
              <span class="text-sm font-semibold text-gray-900 flex-shrink-0">${{ money((item.variant.sale_price_effective || item.product.sale_price) * item.qty) }}</span>
              <button @click="removeFromCart(item.variant.id)" class="text-gray-300 hover:text-red-500 transition flex-shrink-0">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </li>
          </ul>
          <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
            <span class="text-sm font-semibold text-gray-700">Total</span>
            <span class="text-lg font-bold text-gray-900">${{ cartTotal }}</span>
          </div>
        </div>

        <!-- Initial payment -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-3">
          <h2 class="text-sm font-semibold text-gray-700">Abono inicial <span class="text-gray-400 font-normal">(opcional)</span></h2>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
            <input v-model="initialPaymentAmount" type="number" min="0" step="0.01" placeholder="0.00"
              class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>
          <select v-model="initialPaymentMethod"
            class="w-full rounded-lg border border-gray-200 py-2 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400">
            <option value="cash">Efectivo</option>
            <option value="card">Tarjeta</option>
            <option value="transfer">Transferencia</option>
            <option value="other">Otro</option>
          </select>
        </div>

        <button @click="submit" :disabled="cart.length === 0 || form.processing"
          class="w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
          {{ form.processing ? 'Creando...' : 'Crear apartado' }}
        </button>
      </div>
    </div>
  </div>
</template>
