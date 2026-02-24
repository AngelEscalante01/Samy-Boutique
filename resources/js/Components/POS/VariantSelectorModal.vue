<script setup>
import UIButton from '@/Components/UI/Button.vue'
import UIModal from '@/Components/UI/Modal.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  product: { type: Object, default: null },
  inCartVariantIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'confirm'])

const qty = ref(1)
const selectedColorId = ref(null)
const selectedSizeId = ref(null)
const localError = ref('')

const availableVariants = computed(() =>
  (props.product?.variants ?? []).filter((variant) => (variant?.active ?? true) && Number(variant?.stock ?? 0) > 0),
)

const colorOptions = computed(() => {
  const map = new Map()

  for (const variant of availableVariants.value) {
    const colorId = Number(variant?.color?.id ?? 0)
    if (!colorId || map.has(colorId)) continue

    map.set(colorId, {
      id: colorId,
      name: variant?.color?.name ?? 'Sin color',
      stock: Number(variant?.stock ?? 0),
    })
  }

  return Array.from(map.values())
})

const sizeOptions = computed(() => {
  if (!selectedColorId.value) return []

  const map = new Map()
  for (const variant of availableVariants.value) {
    const colorId = Number(variant?.color?.id ?? 0)
    if (colorId !== Number(selectedColorId.value)) continue

    const sizeId = Number(variant?.size?.id ?? 0)
    if (!sizeId || map.has(sizeId)) continue

    map.set(sizeId, {
      id: sizeId,
      name: variant?.size?.name ?? 'Sin talla',
      stock: Number(variant?.stock ?? 0),
    })
  }

  return Array.from(map.values())
})

const selectedVariant = computed(() => {
  if (!selectedColorId.value || !selectedSizeId.value) return null

  return availableVariants.value.find((variant) =>
    Number(variant?.color?.id ?? 0) === Number(selectedColorId.value)
    && Number(variant?.size?.id ?? 0) === Number(selectedSizeId.value),
  ) ?? null
})

const maxQty = computed(() => Number(selectedVariant.value?.stock ?? 0))

const isInCart = computed(() => {
  if (!selectedVariant.value) return false
  return props.inCartVariantIds.includes(selectedVariant.value.id)
})

const canSubmit = computed(() =>
  !!selectedVariant.value
  && Number(maxQty.value) > 0
  && Number(qty.value) >= 1
  && Number(qty.value) <= Number(maxQty.value),
)

const priceLabel = computed(() => {
  if (!selectedVariant.value) return '—'
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' })
    .format(selectedVariant.value.sale_price_effective ?? 0)
})

function initializeSelection() {
  localError.value = ''
  qty.value = 1

  if (!colorOptions.value.length) {
    selectedColorId.value = null
    selectedSizeId.value = null
    return
  }

  if (!colorOptions.value.some((color) => Number(color.id) === Number(selectedColorId.value))) {
    selectedColorId.value = colorOptions.value[0].id
  }

  if (!sizeOptions.value.length) {
    selectedSizeId.value = null
    return
  }

  if (!sizeOptions.value.some((size) => Number(size.id) === Number(selectedSizeId.value))) {
    selectedSizeId.value = sizeOptions.value[0].id
  }
}

watch(() => props.open, (open) => {
  if (open) initializeSelection()
})

watch(() => props.product?.id, () => {
  initializeSelection()
})

watch(selectedColorId, () => {
  if (!sizeOptions.value.length) {
    selectedSizeId.value = null
    return
  }

  if (!sizeOptions.value.some((size) => Number(size.id) === Number(selectedSizeId.value))) {
    selectedSizeId.value = sizeOptions.value[0].id
  }
})

watch(selectedVariant, (variant) => {
  if (!variant) {
    qty.value = 1
    return
  }

  const stock = Number(variant.stock ?? 0)
  if (stock <= 0) {
    qty.value = 1
    return
  }

  qty.value = Math.max(1, Math.min(Number(qty.value || 1), stock))
})

function onQtyInput() {
  if (!selectedVariant.value) {
    qty.value = 1
    return
  }

  const stock = Number(selectedVariant.value.stock ?? 0)
  qty.value = Math.max(1, Math.min(Number(qty.value || 1), stock))
}

function confirmSelection() {
  localError.value = ''

  if (!selectedVariant.value) {
    localError.value = 'Selecciona una combinación válida.'
    return
  }

  if (Number(qty.value) <= 0 || Number(qty.value) > Number(selectedVariant.value.stock ?? 0)) {
    localError.value = 'Cantidad inválida para el stock disponible.'
    return
  }

  emit('confirm', {
    variant: selectedVariant.value,
    qty: Number(qty.value),
  })
}
</script>

<template>
  <UIModal :open="open" title="Seleccionar variante" size="md" @close="$emit('close')">
    <div class="space-y-4">
      <div>
        <p class="text-sm font-semibold text-stone-900">{{ product?.name ?? 'Producto' }}</p>
        <p class="text-xs text-stone-400">{{ product?.sku ?? '' }}</p>
      </div>

      <div v-if="!availableVariants.length" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        Este producto no tiene variantes con stock disponible.
      </div>

      <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-semibold uppercase tracking-widest text-stone-500">Color</label>
          <select
            v-model="selectedColorId"
            class="w-full rounded-xl border border-stone-300 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400"
          >
            <option v-for="color in colorOptions" :key="color.id" :value="color.id">{{ color.name }}</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold uppercase tracking-widest text-stone-500">Talla</label>
          <select
            v-model="selectedSizeId"
            class="w-full rounded-xl border border-stone-300 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400"
          >
            <option v-for="size in sizeOptions" :key="size.id" :value="size.id">{{ size.name }}</option>
          </select>
        </div>

        <div>
          <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-stone-500">Stock disponible</p>
          <p class="rounded-xl bg-stone-50 px-3 py-2 text-sm font-semibold text-stone-700">
            {{ selectedVariant ? selectedVariant.stock : 0 }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold uppercase tracking-widest text-stone-500">Cantidad</label>
          <input
            v-model.number="qty"
            type="number"
            min="1"
            :max="maxQty || 1"
            class="w-full rounded-xl border border-stone-300 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400"
            @input="onQtyInput"
          >
        </div>
      </div>

      <div v-if="selectedVariant" class="rounded-xl bg-stone-50 px-3 py-2 text-sm text-stone-700">
        Precio unitario: <span class="font-semibold">{{ priceLabel }}</span>
      </div>

      <p v-if="isInCart" class="text-xs text-amber-700">
        Esta variante ya está en el carrito; al agregar se actualizará su cantidad.
      </p>

      <p v-if="localError" class="text-xs text-red-600">{{ localError }}</p>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UIButton variant="secondary" @click="$emit('close')">Cancelar</UIButton>
        <UIButton :disabled="!canSubmit" @click="confirmSelection">Agregar al carrito</UIButton>
      </div>
    </template>
  </UIModal>
</template>
