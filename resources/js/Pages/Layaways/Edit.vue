<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  layaway: { type: Object, required: true },
  products: { type: Array, default: () => [] },
  customers: { type: Array, default: () => [] },
  discountPolicy: { type: Object, default: () => ({ canApply: false, canHigh: false, basicMaxPercent: 10, basicMaxAmount: 100 }) },
})

const form = useForm({
  version: props.layaway.version,
  customer_id: props.layaway.customer_id ?? null,
  items: props.layaway.items.map((item) => ({ variant_id: item.product_variant_id, qty: Number(item.qty ?? item.quantity) })),
  global_discount_type: props.layaway.global_discount_type ?? '',
  global_discount_value: props.layaway.global_discount_value ?? '',
  reason: '',
})
const inheritedHighDiscount = computed(() => !props.discountPolicy.canHigh && Number(props.layaway.global_discount_value ?? 0) >
  (props.layaway.global_discount_type === 'percent' ? props.discountPolicy.basicMaxPercent : props.discountPolicy.basicMaxAmount))

function imageFor(images, variantId) {
  const image = images?.find((entry) => Number(entry.product_variant_id) === Number(variantId))
    ?? images?.find((entry) => !entry.product_variant_id)
    ?? images?.[0]
  return image?.thumbnail_url || image?.image_url || image?.url || '/images/product-placeholder.svg'
}

const options = computed(() => {
  const map = new Map()
  for (const item of props.layaway.items) {
    if (item.product_variant_id) map.set(Number(item.product_variant_id), {
      id: Number(item.product_variant_id), name: item.name, sku: item.sku,
      detail: `${item.variant?.size?.name ?? 'Sin talla'} · ${item.variant?.color?.name ?? 'Sin color'}`,
      image: imageFor(item.product?.images, item.product_variant_id),
      stock: 0, price: item.unit_price, available: false,
    })
  }
  for (const product of props.products) {
    for (const variant of product.variants ?? []) {
      map.set(Number(variant.id), {
        id: Number(variant.id), name: product.name, sku: variant.sku,
        detail: `${variant.size?.name ?? 'Sin talla'} · ${variant.color?.name ?? 'Sin color'}`,
        image: imageFor(product.images, variant.id), stock: Number(variant.stock),
        price: variant.sale_price_effective, available: true,
      })
    }
  }
  return map
})

const customerQuery = ref('')
const customerPickerOpen = ref(false)
const selectedCustomer = computed(() => props.customers.find((customer) => Number(customer.id) === Number(form.customer_id)) ?? null)
const matchingCustomers = computed(() => {
  const needle = customerQuery.value.trim().toLowerCase()
  return props.customers.filter((customer) => !needle || customer.name.toLowerCase().includes(needle) || String(customer.phone ?? '').includes(needle)).slice(0, 12)
})
const productQuery = ref('')
const pickerMode = ref(null)
const matchingOptions = computed(() => {
  const needle = productQuery.value.trim().toLowerCase()
  const selectedIds = new Set(form.items.map((item) => Number(item.variant_id)))
  return Array.from(options.value.values())
    .filter((option) => option.available && !selectedIds.has(option.id))
    .filter((option) => !needle || `${option.name} ${option.sku} ${option.detail}`.toLowerCase().includes(needle))
    .slice(0, 24)
})
const previewResult = ref(null)
const previewError = ref('')
const loadingPreview = ref(false)
watch(() => [form.customer_id, form.items, form.global_discount_type, form.global_discount_value], () => { previewResult.value = null }, { deep: true })

function chooseCustomer(customer) {
  form.customer_id = customer?.id ?? null
  customerPickerOpen.value = false
  customerQuery.value = ''
}

function openPicker(mode) {
  pickerMode.value = pickerMode.value === mode ? null : mode
  productQuery.value = ''
}

function chooseVariant(option) {
  if (pickerMode.value === 'add') {
    form.items.push({ variant_id: option.id, qty: 1 })
  } else if (Number.isInteger(pickerMode.value)) {
    const index = pickerMode.value
    form.items.splice(index, 1, { variant_id: option.id, qty: Number(form.items[index].qty) || 1 })
  }
  pickerMode.value = null
  productQuery.value = ''
}

function optionFor(id) {
  return options.value.get(Number(id))
}

function imageFailed(event) {
  event.target.onerror = null
  event.target.src = '/images/product-placeholder.svg'
}

async function preview() {
  previewError.value = ''
  loadingPreview.value = true
  try {
    const response = await axios.post(route('layaways.adjustment.preview', props.layaway.id), form.data())
    previewResult.value = response.data
  } catch (error) {
    previewError.value = Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || 'No se pudo calcular la vista previa.'
  } finally {
    loadingPreview.value = false
  }
}

function save() {
  if (!previewResult.value) return
  form.put(route('layaways.adjustment.update', props.layaway.id))
}

function money(value) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value ?? 0)) }
</script>

<template>
  <Head :title="`Editar apartado #${layaway.id}`" />
  <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 app-card-stack app-page">
    <div class="flex items-center justify-between app-heading"><div><h1 class="text-2xl font-semibold">Cambiar cliente o prendas · apartado #{{ layaway.id }}</h1><p class="text-sm text-stone-500">Los abonos se conservan. Las prendas actuales mantienen su precio; las nuevas usan el precio de hoy.</p></div><Link :href="route('layaways.show', layaway.id)" class="text-sm underline">Volver</Link></div>
    <form class="space-y-5 app-card-stack" @submit.prevent="save">
      <section class="rounded-2xl border border-stone-200 bg-white p-4 app-surface">
        <h2 class="text-sm font-semibold">Cliente del apartado</h2>
        <div class="mt-2 flex flex-wrap items-center gap-3"><p class="flex-1 text-sm text-stone-700">{{ selectedCustomer?.name ?? 'Sin cliente' }}<span v-if="selectedCustomer?.phone" class="text-stone-500"> · {{ selectedCustomer.phone }}</span></p><button type="button" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold" @click="customerPickerOpen = !customerPickerOpen">Cambiar cliente</button></div>
        <div v-if="customerPickerOpen" class="mt-3 rounded-lg border border-stone-200 p-3 app-surface">
          <label for="customer-search" class="block text-xs font-medium text-stone-700">Buscar cliente por nombre o teléfono</label>
          <input id="customer-search" v-model="customerQuery" type="search" class="mt-1 w-full rounded-lg border-stone-300 text-sm" placeholder="Escribe un nombre o teléfono" />
          <div class="mt-2 max-h-60 overflow-y-auto"><button type="button" class="block w-full border-b border-stone-100 px-2 py-2 text-left text-sm hover:bg-stone-50" @click="chooseCustomer(null)">Sin cliente</button><button v-for="customer in matchingCustomers" :key="customer.id" type="button" class="block w-full border-b border-stone-100 px-2 py-2 text-left text-sm hover:bg-stone-50" @click="chooseCustomer(customer)">{{ customer.name }} <span class="text-stone-500">{{ customer.phone || 'Sin teléfono' }}</span></button><p v-if="!matchingCustomers.length" class="p-2 text-sm text-stone-500">No se encontraron clientes.</p></div>
        </div>
      </section>
      <section class="rounded-2xl border border-stone-200 bg-white p-4 app-surface">
        <h2 class="text-sm font-semibold">Prendas reservadas</h2>
        <div v-for="(item, index) in form.items" :key="item.variant_id" class="mt-3 flex flex-wrap items-center gap-3 border-b border-stone-100 pb-3">
          <img :src="optionFor(item.variant_id)?.image || '/images/product-placeholder.svg'" :alt="`Foto de ${optionFor(item.variant_id)?.name ?? 'prenda'}`" class="h-14 w-14 rounded-lg border border-stone-200 object-cover" @error="imageFailed" />
          <div class="min-w-40 flex-1"><p class="text-sm font-semibold">{{ optionFor(item.variant_id)?.name ?? `Variante ${item.variant_id}` }}</p><p class="text-xs text-stone-500">{{ optionFor(item.variant_id)?.sku }} · {{ optionFor(item.variant_id)?.detail }}</p></div>
          <label class="text-xs">Cantidad<input v-model.number="item.qty" type="number" min="1" class="ml-2 w-20 rounded-lg border-stone-300 text-sm"></label>
          <button type="button" class="rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold" @click="openPicker(index)">Cambiar prenda</button>
          <button type="button" :disabled="form.items.length === 1" class="text-sm text-red-700 underline disabled:opacity-40" @click="form.items.splice(index, 1)">Quitar</button>
        </div>
        <button type="button" class="mt-4 rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold" @click="openPicker('add')">Agregar prenda</button>
        <div v-if="pickerMode !== null" class="mt-3 rounded-lg border border-stone-200 bg-stone-50 p-3 app-surface">
          <p class="text-sm font-semibold">{{ pickerMode === 'add' ? 'Elige una prenda para agregar' : 'Elige la prenda que sustituye a la actual' }}</p>
          <input v-model="productQuery" type="search" class="mt-2 w-full rounded-lg border-stone-300 text-sm" placeholder="Buscar prenda, talla, color o código" aria-label="Buscar prenda para el apartado" />
          <div class="mt-2 max-h-80 space-y-2 overflow-y-auto app-card-stack">
            <button v-for="option in matchingOptions" :key="option.id" type="button" class="flex w-full items-center gap-3 rounded-lg border border-stone-200 bg-white p-2 text-left hover:border-stone-400 app-surface" :aria-label="`${pickerMode === 'add' ? 'Agregar' : 'Usar en lugar de la actual'} ${option.name} ${option.detail} ${option.sku}`" @click="chooseVariant(option)"><img :src="option.image" :alt="`Foto de ${option.name}`" class="h-12 w-12 rounded border border-stone-200 object-cover" @error="imageFailed" /><span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ option.name }}</strong><span class="block text-xs text-stone-500">{{ option.detail }} · {{ option.sku }} · {{ option.stock }} disponibles</span></span><span class="text-xs font-semibold">{{ money(option.price) }}</span></button>
            <p v-if="!matchingOptions.length" class="p-2 text-sm text-stone-500">No hay prendas disponibles con esa búsqueda.</p>
          </div>
        </div>
      </section>
      <section class="rounded-2xl border border-stone-200 bg-white p-4 app-surface">
        <h2 class="text-sm font-semibold">Descuento global</h2>
        <p class="mt-1 text-xs text-stone-500">Solo se aplica con dos o más unidades. Si queda una, se elimina al guardar.</p>
        <p v-if="inheritedHighDiscount" class="mt-1 text-xs text-amber-700">Descuento alto aprobado previamente. Solo un gerente puede cambiarlo.</p>
        <p v-else-if="discountPolicy.canApply && !discountPolicy.canHigh" class="mt-1 text-xs text-amber-700">Límite de cajero: {{ discountPolicy.basicMaxPercent }}% o ${{ discountPolicy.basicMaxAmount }}.</p>
        <div class="mt-3 flex gap-3"><select v-model="form.global_discount_type" :disabled="!discountPolicy.canApply || inheritedHighDiscount" class="rounded-lg border-stone-300 text-sm"><option value="">Sin descuento</option><option value="percent">Porcentaje</option><option value="amount">Monto</option></select><input v-model="form.global_discount_value" type="number" min="0" step="0.01" :max="discountPolicy.canHigh || inheritedHighDiscount ? undefined : (form.global_discount_type === 'percent' ? discountPolicy.basicMaxPercent : discountPolicy.basicMaxAmount)" :disabled="!discountPolicy.canApply || inheritedHighDiscount" class="w-32 rounded-lg border-stone-300 text-sm"></div>
      </section>
      <section class="rounded-2xl border border-stone-200 bg-white p-4 app-surface"><label for="reason" class="block text-sm font-semibold">Motivo del cambio</label><textarea id="reason" v-model="form.reason" required maxlength="500" rows="2" class="mt-2 w-full rounded-lg border-stone-300 text-sm"></textarea></section>
      <div v-if="previewError" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ previewError }}</div>
      <div v-if="Object.keys(form.errors).length" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ Object.values(form.errors).join(' ') }}</div>
      <section v-if="previewResult" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 app-surface" aria-live="polite">
        <h2 class="font-semibold">Vista previa del ajuste</h2>
        <p v-if="Number(previewResult.customer_id) !== Number(layaway.customer_id)" class="mt-2 text-sm">Cliente: {{ layaway.customer?.name ?? 'Sin cliente' }} → {{ selectedCustomer?.name ?? 'Sin cliente' }}</p>
        <ul v-if="previewResult.changes?.length" class="mt-2 space-y-1 text-sm"><li v-for="change in previewResult.changes" :key="change.variant_id">{{ change.name }} · {{ change.sku }} · {{ change.size || 'Sin talla' }} / {{ change.color || 'Sin color' }}: {{ change.before_qty }} → {{ change.after_qty }} × {{ money(change.unit_price) }}</li></ul>
        <p class="mt-2 text-sm">Subtotal {{ money(previewResult.subtotal) }} · Descuento global {{ money(previewResult.manual_discount_total) }} · Fidelidad {{ money(previewResult.loyalty_discount_total) }} · Nuevo total {{ money(previewResult.total) }}</p>
        <p class="mt-1 text-sm">Abonado neto {{ money(previewResult.applied_total) }} · Saldo {{ money(previewResult.balance) }} · Excedente a devolver {{ money(previewResult.excess) }}</p>
      </section>
      <div class="flex gap-3"><button type="button" :disabled="loadingPreview" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold" @click="preview">{{ loadingPreview ? 'Calculando...' : 'Calcular vista previa' }}</button><button type="submit" :disabled="!previewResult || form.processing" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Confirmar cambios</button></div>
    </form>
  </div>
</template>
