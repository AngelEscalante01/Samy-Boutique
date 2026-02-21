<script setup>
import { watch, onUnmounted } from 'vue'

const props = defineProps({
  open:  { type: Boolean, required: true },
  title: { type: String,  default: '' },
  size:  { type: String,  default: 'md' },  // sm | md | lg | xl
})
const emit = defineEmits(['close'])

const SIZES = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
}

// Bloquear scroll del body cuando está abierto
watch(() => props.open, (val) => {
  document.body.style.overflow = val ? 'hidden' : ''
}, { immediate: true })

onUnmounted(() => {
  document.body.style.overflow = ''
})

function handleOverlayClick() {
  emit('close')
}
function handleKeydown(e) {
  if (e.key === 'Escape') emit('close')
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @keydown="handleKeydown"
        tabindex="-1"
      >
        <!-- Overlay -->
        <div
          class="absolute inset-0 bg-zinc-900/60 backdrop-blur-sm"
          aria-hidden="true"
          @click="handleOverlayClick"
        />

        <!-- Panel -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="open"
            :class="['relative z-10 w-full bg-white rounded-2xl shadow-2xl shadow-zinc-900/20 flex flex-col max-h-[90vh]', SIZES[size] ?? SIZES.md]"
            role="dialog"
            aria-modal="true"
          >
            <!-- Header -->
            <div v-if="title || $slots.header" class="flex items-center justify-between gap-4 border-b border-stone-200 px-6 py-4">
              <div>
                <slot name="header">
                  <h2 class="text-base font-semibold text-stone-900 tracking-wide">{{ title }}</h2>
                </slot>
              </div>
              <button
                type="button"
                class="rounded-xl p-2 text-stone-400 hover:bg-stone-100 hover:text-stone-600 transition-colors"
                @click="$emit('close')"
                aria-label="Cerrar"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6">
              <slot />
            </div>

            <!-- Footer slot -->
            <div
              v-if="$slots.footer"
              class="border-t border-stone-200 bg-stone-50/60 px-6 py-4"
            >
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
