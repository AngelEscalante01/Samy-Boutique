<script setup>
import { computed } from 'vue'

const props = defineProps({
  startsAt: { type: String, default: null },
  endsAt:   { type: String, default: null },
  size:     { type: String, default: 'sm' }, // sm | md
})

const now = new Date()

const validity = computed(() => {
  const start = props.startsAt ? new Date(props.startsAt) : null
  const end   = props.endsAt   ? new Date(props.endsAt)   : null

  if (end && end < now)    return { key: 'expired',  label: 'Expirado',  cls: 'bg-red-100    text-red-700     ring-red-200'     }
  if (start && start > now) return { key: 'upcoming', label: 'Próximo',   cls: 'bg-blue-100   text-blue-700    ring-blue-200'    }
  return                          { key: 'active',   label: 'Vigente',   cls: 'bg-emerald-100 text-emerald-700 ring-emerald-200' }
})

const sizeClass = {
  sm: 'px-2 py-0.5 text-xs font-semibold',
  md: 'px-2.5 py-1 text-sm font-semibold',
}
</script>

<template>
  <span class="inline-flex items-center rounded-full ring-1"
    :class="[validity.cls, sizeClass[size]]">
    {{ validity.label }}
  </span>
</template>
