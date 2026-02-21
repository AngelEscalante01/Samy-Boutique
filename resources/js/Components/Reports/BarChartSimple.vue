<script setup>
import { computed } from 'vue'

const props = defineProps({
  data:      { type: Array,  required: true },   // [{ label, value }]
  colorClass:{ type: String, default: 'bg-blue-500' },
  height:    { type: String, default: 'h-24' },
})

const max = computed(() => Math.max(...props.data.map(d => d.value ?? 0), 1))

function pct(v) {
  return Math.max(4, Math.round(((v ?? 0) / max.value) * 100))
}

function money(v) {
  return Number(v ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
</script>

<template>
  <div class="w-full overflow-x-auto">
    <div class="flex items-end gap-1 min-w-0" :class="height" style="min-width: max-content">
      <div
        v-for="(d, i) in data"
        :key="i"
        class="group relative flex flex-col items-center gap-0.5 flex-1"
        style="min-width: 28px; max-width: 56px"
      >
        <!-- Barra -->
        <div
          :class="[colorClass, 'rounded-t-sm w-full transition-all duration-300 hover:opacity-80']"
          :style="{ height: pct(d.value) + '%' }"
          :title="d.label + ': $' + money(d.value)"
        />
        <!-- Tooltip emergente -->
        <div class="absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2
                    hidden group-hover:flex flex-col items-center pointer-events-none z-10">
          <div class="rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs text-white whitespace-nowrap shadow-lg">
            <span class="font-semibold">{{ d.label }}</span><br />
            ${{ money(d.value) }}
          </div>
          <div class="h-1.5 w-1.5 rotate-45 bg-gray-900 -mt-1" />
        </div>
      </div>
    </div>
    <!-- Labels -->
    <div class="mt-1 flex gap-1" style="min-width: max-content">
      <div
        v-for="(d, i) in data"
        :key="'l' + i"
        class="text-center text-[10px] text-gray-400 truncate flex-1"
        style="min-width: 28px; max-width: 56px"
      >{{ d.label }}</div>
    </div>
  </div>
</template>
