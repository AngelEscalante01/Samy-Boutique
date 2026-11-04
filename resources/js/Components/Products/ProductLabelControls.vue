<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import UICard from '@/Components/UI/Card.vue'
const props = defineProps({ product: { type: Object, required: true } })
const labelItems = computed(() => {
  const variants = props.product.variants ?? []
  return variants.length
    ? variants.map((variant) => ({ value: `variant:${variant.id}`, sku: variant.sku, description: `${variant.size?.name ?? 'Sin talla'} / ${variant.color?.name ?? 'Sin color'}` }))
    : [{ value: `product:${props.product.id}`, sku: props.product.sku, description: 'Modelo sin variantes' }]
})
const labelSelection = ref([])
const labelCopies = ref(1)
const labelWidth = ref(60)
const labelHeight = ref(30)
const labelShowPrice = ref(true)
const labelBusy = ref(false)
const labelError = ref('')
const labelStatus = ref('')
const labelPrintFrame = ref(null)

async function printProductLabels() {
  labelError.value = ''
  labelStatus.value = ''
  if (!labelSelection.value.length) {
    labelError.value = 'Selecciona al menos una variante para imprimir.'
    return
  }
  if (labelBusy.value) return
  labelBusy.value = true
  try {
    const response = await axios.post(route('product-labels.store'), {
      selection: labelSelection.value,
      copies: Number(labelCopies.value),
      width_mm: Number(labelWidth.value),
      height_mm: Number(labelHeight.value),
      show_price: labelShowPrice.value,
    }, { headers: { Accept: 'application/json' } })
    const frame = labelPrintFrame.value
    if (!frame) throw new Error('No se encontró el marco de impresión.')
    frame.onload = () => {
      if (!frame.contentDocument?.querySelector('.label')) {
        labelError.value = 'No se pudo cargar la etiqueta. Intenta de nuevo.'
      } else {
        try {
          frame.contentWindow.focus()
          frame.contentWindow.print()
          labelStatus.value = 'Se solicitó la impresión. Revisa el diálogo de la impresora.'
        } catch {
          labelError.value = 'El navegador no pudo abrir el diálogo de impresión.'
        }
      }
      labelBusy.value = false
    }
    frame.onerror = () => {
      labelError.value = 'No se pudo cargar la etiqueta. Intenta de nuevo.'
      labelBusy.value = false
    }
    frame.src = `${response.data.url}?embedded=1`
  } catch (error) {
    labelError.value = Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || error.response?.data?.message || 'No se pudieron preparar las etiquetas.'
    labelBusy.value = false
  }
}

</script>
<template>
  <details class="border border-stone-200 bg-white">
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-stone-700">Imprimir etiquetas de este producto</summary>
  <UICard class="mb-6">
    <template #header><h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Etiquetas de este producto</h2></template>
    <p class="mb-3 text-sm text-stone-600">Elige las variantes guardadas y cuántas etiquetas necesitas. Se imprimirán sus códigos de barras actuales.</p>
    <div class="mb-3 flex flex-wrap gap-3">
      <label v-for="item in labelItems" :key="item.value" class="flex items-start gap-2 rounded-lg border border-stone-200 px-3 py-2 text-sm">
        <input v-model="labelSelection" type="checkbox" :value="item.value" class="mt-1 rounded border-stone-300">
        <span><strong class="block">{{ item.description }}</strong><span class="font-mono text-xs text-stone-500">{{ item.sku }}</span></span>
      </label>
    </div>
    <div class="flex flex-wrap items-end gap-3">
      <label class="text-xs font-medium text-stone-700">Copias por variante<input v-model.number="labelCopies" type="number" min="1" max="100" class="mt-1 block w-28 rounded-lg border-stone-300 text-sm"></label>
      <label class="text-xs font-medium text-stone-700">Ancho (mm)<input v-model.number="labelWidth" type="number" min="40" max="76" class="mt-1 block w-28 rounded-lg border-stone-300 text-sm"></label>
      <label class="text-xs font-medium text-stone-700">Alto (mm)<input v-model.number="labelHeight" type="number" min="30" max="80" class="mt-1 block w-28 rounded-lg border-stone-300 text-sm"></label>
      <label class="flex items-center gap-2 pb-2 text-xs font-medium text-stone-700"><input v-model="labelShowPrice" type="checkbox" class="rounded border-stone-300">Mostrar precio</label>
      <button type="button" :disabled="labelBusy" class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="printProductLabels">{{ labelBusy ? 'Preparando...' : 'Imprimir etiquetas' }}</button>
    </div>
    <p v-if="labelError" role="alert" class="mt-3 text-sm text-red-700">{{ labelError }}</p>
    <p v-if="labelStatus" role="status" class="mt-3 text-sm text-emerald-700">{{ labelStatus }}</p>
    <iframe ref="labelPrintFrame" title="Etiqueta para imprimir" class="pointer-events-none absolute -left-[9999px] top-0 h-px w-px border-0" aria-hidden="true"></iframe>
    <p class="mt-2 text-xs text-stone-500">Tamaño inicial: 60 × 30 mm. Usa la misma medida en el controlador de la impresora.</p>
  </UICard>

  </details>
</template>
