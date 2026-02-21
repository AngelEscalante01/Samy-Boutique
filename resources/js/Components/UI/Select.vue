<script setup>
defineProps({
  modelValue: { default: '' },
  label:      { type: String,  default: '' },
  error:      { type: String,  default: '' },
  required:   { type: Boolean, default: false },
  disabled:   { type: Boolean, default: false },
  hint:       { type: String,  default: '' },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex flex-col gap-1">
    <label v-if="label" class="text-xs font-semibold text-stone-700">
      {{ label }}
      <span v-if="required" class="text-red-400 ml-0.5">*</span>
    </label>

    <div class="relative">
      <select
        :value="modelValue"
        :disabled="disabled"
        :class="[
          'block w-full appearance-none rounded-xl border py-2 pl-3 pr-8 text-sm text-stone-800 bg-white',
          'transition duration-200',
          'focus:outline-none focus:ring-2 focus:ring-amber-300/60 focus:border-amber-400',
          'disabled:bg-stone-50 disabled:text-stone-400 disabled:cursor-not-allowed',
          error ? 'border-red-400 focus:ring-red-200/50 focus:border-red-400' : 'border-stone-300',
        ]"
        @change="$emit('update:modelValue', $event.target.value)"
      >
        <slot />
      </select>
      <!-- Chevron -->
      <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-stone-400">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
      </div>
    </div>

    <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
    <p v-else-if="hint" class="text-xs text-stone-400">{{ hint }}</p>
  </div>
</template>
