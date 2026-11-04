<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
  size:   { type: String, default: 'sm' }, // sm | md | lg
})

const statusConfig = {
  completed: { label: 'Pagada',    cls: 'bg-emerald-100 text-emerald-700 ring-emerald-200' },
  cancelled: { label: 'Cancelada', cls: 'bg-red-100    text-red-700     ring-red-200'     },
  pending:   { label: 'Pendiente', cls: 'bg-amber-100  text-amber-700   ring-amber-200'   },
  applied:   { label: 'Aplicado',  cls: 'bg-indigo-100 text-indigo-700  ring-indigo-200'  },
}

const sizeClass = {
  sm: 'px-2   py-0.5 text-xs font-semibold',
  md: 'px-2.5 py-1   text-sm font-semibold',
  lg: 'px-3   py-1   text-sm font-bold',
}

const config = computed(() =>
  statusConfig[props.status] ?? { label: props.status, cls: 'bg-gray-100 text-gray-500 ring-gray-200' }
)
</script>

<template>
  <span
    class="inline-flex items-center rounded-full ring-1"
    :class="[config.cls, sizeClass[size]]"
  >
    {{ config.label }}
  </span>
</template>
