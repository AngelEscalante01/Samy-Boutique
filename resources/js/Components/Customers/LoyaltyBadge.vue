<script setup>
import { computed } from 'vue'

const props = defineProps({
  count: { type: Number, default: 0 },
  // size: 'sm' | 'md' (md = used in Show page)
  size:  { type: String, default: 'sm' },
})

const CYCLE = 5
const progress  = computed(() => props.count % CYCLE)
const remaining = computed(() => CYCLE - (props.count % CYCLE))
const pct       = computed(() => (progress.value / CYCLE) * 100)
const isNext    = computed(() => progress.value === CYCLE - 1)  // 4/5
const earned    = computed(() => Math.floor(props.count / CYCLE))
</script>

<template>
  <!-- sm variant: compact badge for table rows -->
  <template v-if="size === 'sm'">
    <span v-if="isNext"
      class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
      <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
      </svg>
      Siguiente con descuento
    </span>
    <span v-else class="text-xs text-gray-500">
      Faltan {{ remaining }} para descuento
    </span>
  </template>

  <!-- md variant: full card with progress bar (Show page) -->
  <template v-else>
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg class="h-5 w-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
          </svg>
          <p class="text-sm font-semibold text-gray-800">Programa de fidelidad</p>
        </div>
        <span class="text-sm font-bold text-gray-900">{{ progress }}/{{ CYCLE }}</span>
      </div>

      <!-- Progress bar -->
      <div class="w-full h-2.5 rounded-full bg-gray-100 overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-500"
          :class="isNext ? 'bg-amber-400' : 'bg-emerald-500'"
          :style="{ width: pct + '%' }"
        />
      </div>

      <!-- Dots -->
      <div class="flex justify-between">
        <div v-for="i in CYCLE" :key="i"
          class="h-1.5 w-1.5 rounded-full"
          :class="i <= progress ? 'bg-emerald-500' : 'bg-gray-200'"
        />
      </div>

      <!-- Message -->
      <p v-if="isNext" class="text-xs font-semibold text-amber-700 flex items-center gap-1">
        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
        !La siguiente compra aplica descuento de fidelidad!
      </p>
      <p v-else class="text-xs text-gray-500">
        Le faltan <strong class="text-gray-700">{{ remaining }}</strong> compra{{ remaining !== 1 ? 's' : '' }} para obtener descuento.
      </p>
      <p v-if="earned > 0" class="text-xs text-gray-400">
        Descuentos obtenidos: {{ earned }}
      </p>
    </div>
  </template>
</template>
