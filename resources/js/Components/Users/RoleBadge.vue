<script setup>
defineProps({
  role: { type: String, default: '—' },
  size: { type: String, default: 'sm' }, // sm | md
})

const CONFIG = {
  gerente: { label: 'Gerente', cls: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
  cajero:  { label: 'Cajero',  cls: 'bg-sky-50    text-sky-700    ring-sky-200'    },
}
</script>

<template>
  <span
    :class="[
      CONFIG[role]?.cls ?? 'bg-gray-100 text-gray-500 ring-gray-200',
      size === 'md' ? 'px-3 py-1 text-sm' : 'px-2.5 py-0.5 text-xs'
    ]"
    class="inline-flex items-center rounded-full font-medium ring-1"
  >
    {{ CONFIG[role]?.label ?? role }}
  </span>
</template>
