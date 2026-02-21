<script setup>
defineProps({
  variant: { type: String, default: 'neutral' },  // success | warning | danger | neutral | info | premium
  size:    { type: String, default: 'sm' },        // sm | md
  dot:     { type: Boolean, default: false },
})

const VARIANTS = {
  success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  warning: 'bg-amber-50   text-amber-700   ring-amber-200',
  danger:  'bg-red-50     text-red-700     ring-red-200',
  neutral: 'bg-zinc-100   text-zinc-600    ring-zinc-200',
  info:    'bg-sky-50     text-sky-700     ring-sky-200',
  premium: 'bg-amber-100  text-amber-900   ring-amber-200',  // champagne
}
const DOT_COLORS = {
  success: 'bg-emerald-400',
  warning: 'bg-amber-400',
  danger:  'bg-red-400',
  neutral: 'bg-zinc-400',
  info:    'bg-sky-400',
  premium: 'bg-amber-500',
}
</script>

<template>
  <span
    :class="[
      'inline-flex items-center font-medium ring-1 rounded-full',
      size === 'md' ? 'px-3 py-1 text-sm gap-1.5' : 'px-2.5 py-0.5 text-xs gap-1',
      VARIANTS[variant] ?? VARIANTS.neutral,
    ]"
  >
    <span
      v-if="dot"
      :class="['rounded-full shrink-0', DOT_COLORS[variant] ?? 'bg-zinc-400', size === 'md' ? 'h-2 w-2' : 'h-1.5 w-1.5']"
    />
    <slot />
  </span>
</template>
