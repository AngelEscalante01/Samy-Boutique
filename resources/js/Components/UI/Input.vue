<script setup>
defineProps({
  modelValue:  { default: '' },
  label:       { type: String,  default: '' },
  placeholder: { type: String,  default: '' },
  error:       { type: String,  default: '' },
  type:        { type: String,  default: 'text' },
  required:    { type: Boolean, default: false },
  disabled:    { type: Boolean, default: false },
  hint:        { type: String,  default: '' },
})
defineEmits(['update:modelValue'])
defineSlots()            // allows icon slot usage info
</script>

<template>
  <div class="flex flex-col gap-1">
    <label v-if="label" class="text-xs font-semibold text-stone-700">
      {{ label }}
      <span v-if="required" class="text-red-400 ml-0.5">*</span>
    </label>

    <div class="relative">
      <!-- Leading icon slot -->
      <div v-if="$slots.icon"
           class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
        <slot name="icon" />
      </div>

      <input
        :value="modelValue"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        :class="[
          'block w-full rounded-xl border py-2 text-sm text-stone-800 bg-white',
          'transition duration-200 placeholder:text-stone-400',
          'focus:outline-none focus:ring-2 focus:ring-amber-300/60 focus:border-amber-400',
          'disabled:bg-stone-50 disabled:text-stone-400 disabled:cursor-not-allowed',
          $slots.icon ? 'pl-9 pr-3' : 'px-3',
          error ? 'border-red-400 focus:ring-red-200/50 focus:border-red-400' : 'border-stone-300',
        ]"
        @input="$emit('update:modelValue', $event.target.value)"
      />
    </div>

    <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
    <p v-else-if="hint" class="text-xs text-stone-400">{{ hint }}</p>
  </div>
</template>
