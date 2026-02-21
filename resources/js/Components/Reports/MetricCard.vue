<script setup>
defineProps({
  label:    { type: String,  required: true },
  value:    { type: [String, Number], required: true },
  sub:      { type: String,  default: '' },
  color:    { type: String,  default: 'gray' },   // emerald | blue | purple | amber | red | gray
  prefix:   { type: String,  default: '' },
  isMoney:  { type: Boolean, default: false },
})
</script>

<template>
  <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 flex flex-col gap-1 min-w-0">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 truncate">{{ label }}</p>
    <p
      :class="{
        'text-gray-900':   color === 'gray',
        'text-emerald-600': color === 'emerald',
        'text-blue-600':   color === 'blue',
        'text-purple-600': color === 'purple',
        'text-amber-600':  color === 'amber',
        'text-red-500':    color === 'red',
      }"
      class="text-2xl font-bold tabular-nums leading-none"
    >
      <span v-if="isMoney" class="text-lg font-semibold text-gray-400 mr-0.5">$</span>{{ value }}
    </p>
    <p v-if="sub" class="text-xs text-gray-400 truncate">{{ sub }}</p>
  </div>
</template>
