<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const props = defineProps({
  initialQuery: { type: String, default: '' },
  initialRecentDays: { type: String, default: '' },
})
const emit = defineEmits(['close'])
const query = ref(props.initialQuery)
const recentDays = ref(props.initialRecentDays)
const rows = ref([])
const jobs = ref([])
const selected = ref([])
const page = ref(1)
const lastPage = ref(1)
const totalProducts = ref(0)
const loading = ref(false)
const busy = ref(false)
const errorMessage = ref('')
const statusMessage = ref('')
const copies = ref(1)
const width = ref(60)
const height = ref(30)
const showPrice = ref(true)
const printFrame = ref(null)
let requestNumber = 0

const allSelected = computed(() => rows.value.length > 0 && rows.value.every(row => selected.value.includes(row.selection)))

async function loadRows(targetPage = 1) {
  const currentRequest = ++requestNumber
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.get(route('product-labels.index'), {
      params: { q: query.value, recent_days: recentDays.value, page: targetPage },
      headers: { Accept: 'application/json' },
    })
    if (currentRequest !== requestNumber) return
    rows.value = data.rows ?? []
    jobs.value = data.jobs ?? []
    selected.value = []
    page.value = data.page ?? 1
    lastPage.value = data.last_page ?? 1
    totalProducts.value = data.total_products ?? 0
  } catch (error) {
    if (currentRequest === requestNumber) errorMessage.value = error.response?.data?.message || 'No se pudo cargar la tabla de etiquetas.'
  } finally {
    if (currentRequest === requestNumber) loading.value = false
  }
}

function toggleAll(event) {
  selected.value = event.target.checked ? rows.value.map(row => row.selection) : []
}

function loadPrintJob(url) {
  const frame = printFrame.value
  if (!frame) throw new Error('No se encontró el marco de impresión.')
  frame.onload = () => {
    if (!frame.contentDocument?.querySelector('.label')) {
      errorMessage.value = 'No se pudo cargar la etiqueta. Intenta de nuevo.'
    } else {
      try {
        frame.contentWindow.focus()
        frame.contentWindow.print()
        statusMessage.value = 'Se solicitó la impresión. Revisa el diálogo de la impresora.'
      } catch {
        errorMessage.value = 'El navegador no pudo abrir el diálogo de impresión.'
      }
    }
    busy.value = false
  }
  frame.onerror = () => {
    errorMessage.value = 'No se pudo cargar la etiqueta. Intenta de nuevo.'
    busy.value = false
  }
  frame.src = `${url}${url.includes('?') ? '&' : '?'}embedded=1`
}

async function printLabels(selection) {
  errorMessage.value = ''
  statusMessage.value = ''
  if (!selection.length) {
    errorMessage.value = 'Selecciona al menos una variante para imprimir.'
    return
  }
  if (busy.value) return
  busy.value = true
  try {
    const { data } = await axios.post(route('product-labels.store'), {
      selection,
      copies: Number(copies.value),
      width_mm: Number(width.value),
      height_mm: Number(height.value),
      show_price: showPrice.value,
    }, { headers: { Accept: 'application/json' } })
    loadPrintJob(data.url)
    await loadRows(page.value)
  } catch (error) {
    errorMessage.value = Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || error.response?.data?.message || 'No se pudieron preparar las etiquetas.'
    busy.value = false
  }
}

function reprint(url) {
  if (busy.value) return
  errorMessage.value = ''
  statusMessage.value = ''
  busy.value = true
  loadPrintJob(url)
}

function imageFailed(event) {
  event.target.onerror = null
  event.target.src = '/images/product-placeholder.svg'
}

onMounted(() => loadRows())
</script>

<template>
  <section class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm sm:p-5 app-surface" aria-label="Impresión de etiquetas en tabla">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-bold text-stone-900">Impresión de etiquetas en tabla</h2>
        <p class="text-sm text-stone-500">Una fila por variante. Imprime una o selecciona varias sin salir de Inventario.</p>
      </div>
      <button type="button" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold hover:bg-stone-50" @click="emit('close')">Cerrar tabla</button>
    </div>

    <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="loadRows()">
      <label class="flex min-w-48 flex-1 flex-col gap-1 text-xs font-medium text-stone-700">Buscar nombre o código
        <input v-model="query" type="search" maxlength="120" class="rounded-lg border-stone-300 text-sm" />
      </label>
      <label class="flex flex-col gap-1 text-xs font-medium text-stone-700">Agregados
        <select v-model="recentDays" class="rounded-lg border-stone-300 text-sm" @change="loadRows()">
          <option value="">Todas las fechas</option><option value="7">Últimos 7 días</option><option value="30">Últimos 30 días</option><option value="90">Últimos 90 días</option>
        </select>
      </label>
      <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white">Buscar</button>
      <span class="pb-2 text-xs text-stone-500">{{ totalProducts }} productos · más nuevos primero</span>
    </form>

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <label class="flex flex-col gap-1 text-xs font-medium text-stone-700">Copias por variante<input v-model.number="copies" type="number" min="1" max="100" class="w-24 rounded-lg border-stone-300 text-sm" /></label>
      <label class="flex flex-col gap-1 text-xs font-medium text-stone-700">Ancho (mm)<input v-model.number="width" type="number" min="40" max="76" class="w-24 rounded-lg border-stone-300 text-sm" /></label>
      <label class="flex flex-col gap-1 text-xs font-medium text-stone-700">Alto (mm)<input v-model.number="height" type="number" min="30" max="80" class="w-24 rounded-lg border-stone-300 text-sm" /></label>
      <label class="flex items-center gap-2 pb-2 text-xs font-medium text-stone-700"><input v-model="showPrice" type="checkbox" class="rounded border-stone-300" />Mostrar precio</label>
    </div>

    <div class="overflow-x-auto rounded-lg border border-stone-200 app-surface">
      <table class="w-full min-w-[780px] text-left text-sm">
        <thead class="bg-stone-50 text-xs uppercase text-stone-500"><tr>
          <th class="sticky left-0 z-10 bg-stone-50 px-3 py-3"><input type="checkbox" :checked="allSelected" aria-label="Seleccionar todas las variantes de esta página" @change="toggleAll" /></th>
          <th class="px-3 py-3">Foto</th><th class="px-3 py-3">Producto</th><th class="px-3 py-3">Variante</th><th class="px-3 py-3">Código</th><th class="px-3 py-3">Precio</th>
          <th class="sticky right-0 z-10 bg-stone-50 px-3 py-3">Acción</th>
        </tr></thead>
        <tbody>
          <tr v-for="row in rows" :key="row.selection" class="border-t border-stone-100">
            <td class="sticky left-0 bg-white px-3 py-2"><input v-model="selected" type="checkbox" :value="row.selection" :aria-label="`Elegir ${row.sku}`" /></td>
            <td class="px-3 py-2"><img :src="row.image_url || '/images/product-placeholder.svg'" :alt="`Foto de ${row.name}`" class="h-12 w-12 rounded-md border border-stone-200 object-cover" @error="imageFailed" /></td>
            <td class="max-w-48 px-3 py-2 font-medium text-stone-900">{{ row.name }}</td>
            <td class="px-3 py-2">{{ row.variant || 'Sin variante' }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ row.sku }}</td>
            <td class="px-3 py-2">${{ row.price }}</td>
            <td class="sticky right-0 bg-white px-3 py-2 shadow-[-4px_0_8px_-8px_#57534e]"><button type="button" :disabled="busy" :aria-label="`Imprimir etiqueta ${row.sku}`" class="whitespace-nowrap rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold hover:bg-stone-50 disabled:opacity-50" @click="printLabels([row.selection])">Imprimir etiqueta</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="loading" class="p-4 text-sm text-stone-500">Cargando productos...</p>
      <p v-else-if="!rows.length" class="p-4 text-sm text-stone-500">No hay productos con este filtro.</p>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
      <button type="button" :disabled="busy || !selected.length" class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="printLabels(selected)">Imprimir seleccionadas ({{ selected.length }})</button>
      <button v-if="page > 1" type="button" class="rounded-lg border border-stone-300 px-3 py-2 text-sm" @click="loadRows(page - 1)">← Anteriores</button>
      <span class="text-xs text-stone-500">Página {{ page }} de {{ lastPage }}</span>
      <button v-if="page < lastPage" type="button" class="rounded-lg border border-stone-300 px-3 py-2 text-sm" @click="loadRows(page + 1)">Siguientes →</button>
    </div>
    <p v-if="errorMessage" role="alert" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
    <p v-if="statusMessage" role="status" class="mt-3 text-sm text-emerald-700">{{ statusMessage }}</p>
    <p class="mt-3 text-xs text-stone-500">El tamaño debe coincidir con el rollo configurado en la impresora. El navegador solicitará el diálogo de impresión sin abrir otra pestaña.</p>

    <details v-if="jobs.length" class="mt-5 border-t border-stone-200 pt-4">
      <summary class="cursor-pointer text-sm font-semibold">Lotes preparados</summary>
      <div class="mt-2 flex flex-wrap gap-2"><button v-for="job in jobs" :key="job.id" type="button" :disabled="busy" class="rounded-lg border border-stone-300 px-3 py-2 text-xs hover:bg-stone-50" @click="reprint(job.url)">Reimprimir lote #{{ job.id }} · {{ job.date }} · {{ job.width_mm }} × {{ job.height_mm }} mm</button></div>
    </details>
    <iframe ref="printFrame" title="Etiquetas para imprimir" class="pointer-events-none absolute -left-[9999px] top-0 h-px w-px border-0" aria-hidden="true"></iframe>
  </section>
</template>
