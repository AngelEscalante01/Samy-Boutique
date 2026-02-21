<script setup>
defineProps({
  padding: { type: Boolean, default: true },
  noBorder: { type: Boolean, default: false },
})
</script>

<template>
  <div
    :class="[
      'bg-white rounded-2xl shadow-sm',
      noBorder ? '' : 'border border-stone-200',
      'overflow-hidden',
    ]"
  >
    <!-- Header slot -->
    <div
      v-if="$slots.header"
      class="border-b border-stone-100 bg-stone-50/60 px-5 py-3"
    >
      <slot name="header" />
    </div>

    <!-- Content -->
    <div :class="padding ? 'p-5' : ''">
      <slot />
    </div>

    <!-- Footer slot -->
    <div
      v-if="$slots.footer"
      class="border-t border-stone-100 bg-stone-50/40 px-5 py-3"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
