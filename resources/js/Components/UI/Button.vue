<script setup>
defineProps({
  variant:  { type: String,  default: 'primary' },  // primary | secondary | ghost | danger
  size:     { type: String,  default: 'md' },        // sm | md | lg
  loading:  { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  type:     { type: String,  default: 'button' },
})

const VARIANTS = {
  primary:   'bg-zinc-900 text-white hover:bg-zinc-800 border border-transparent shadow-sm',
  secondary: 'bg-white text-stone-800 hover:bg-stone-50 border border-stone-300 shadow-sm',
  ghost:     'bg-transparent text-stone-700 hover:bg-stone-100 border border-transparent',
  danger:    'bg-red-600 text-white hover:bg-red-500 border border-transparent shadow-sm',
}
const SIZES = {
  sm:  'px-3 py-1.5 text-xs gap-1.5',
  md:  'px-4 py-2 text-sm gap-2',
  lg:  'px-5 py-2.5 text-sm gap-2',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center font-semibold rounded-xl transition duration-200',
      'focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-1',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      VARIANTS[variant] ?? VARIANTS.primary,
      SIZES[size]      ?? SIZES.md,
    ]"
  >
    <!-- Spinner -->
    <svg
      v-if="loading"
      class="animate-spin shrink-0"
      :class="size === 'sm' ? 'h-3 w-3' : 'h-4 w-4'"
      fill="none" viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path  class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
    </svg>
    <slot />
  </button>
</template>
