<script setup>
const props   = defineProps({ modelValue: { type: Boolean, default: false } })
const emit    = defineEmits(['update:modelValue'])
const toggle  = () => emit('update:modelValue', !props.modelValue)
</script>

<template>
  <button
    type="button"
    @click="toggle"
    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1"
    :class="modelValue ? 'bg-emerald-500' : 'bg-gray-200'"
    :aria-checked="modelValue"
    role="switch"
  >
    <span
      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
      :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
    />
  </button>
</template>
