/**
 * Samy Boutique – Premium UI System Generator
 * node _gen/premium_ui.mjs
 *
 * Genera:
 *   Components/UI/Button.vue
 *   Components/UI/Input.vue
 *   Components/UI/Select.vue
 *   Components/UI/Card.vue
 *   Components/UI/Badge.vue
 *   Components/UI/Modal.vue
 *   Components/UI/Table.vue
 */
import { writeFileSync, mkdirSync } from 'fs'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const base = resolve(__dirname, '..', 'resources', 'js')

function write(rel, content) {
  const abs = resolve(base, rel)
  mkdirSync(dirname(abs), { recursive: true })
  writeFileSync(abs, content, 'utf-8')
  console.log('  OK', rel)
}

/* ═══════════════════════════════════════════════════════════════════════════
   Button.vue
   Props: variant (primary|secondary|ghost|danger) · size (sm|md|lg) · loading · disabled
   ═══════════════════════════════════════════════════════════════════════════ */
const BUTTON = `<script setup>
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
<\/script>

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
`

/* ═══════════════════════════════════════════════════════════════════════════
   Input.vue
   Props: modelValue · label · placeholder · error · type · icon (slot)
   ═══════════════════════════════════════════════════════════════════════════ */
const INPUT = `<script setup>
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
<\/script>

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
`

/* ═══════════════════════════════════════════════════════════════════════════
   Select.vue
   ═══════════════════════════════════════════════════════════════════════════ */
const SELECT = `<script setup>
defineProps({
  modelValue: { default: '' },
  label:      { type: String,  default: '' },
  error:      { type: String,  default: '' },
  required:   { type: Boolean, default: false },
  disabled:   { type: Boolean, default: false },
  hint:       { type: String,  default: '' },
})
defineEmits(['update:modelValue'])
<\/script>

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
`

/* ═══════════════════════════════════════════════════════════════════════════
   Card.vue
   Slots: header (opcional) · default (contenido) · footer (opcional)
   ═══════════════════════════════════════════════════════════════════════════ */
const CARD = `<script setup>
defineProps({
  padding: { type: Boolean, default: true },
  noBorder: { type: Boolean, default: false },
})
<\/script>

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
`

/* ═══════════════════════════════════════════════════════════════════════════
   Badge.vue
   variants: success | warning | danger | neutral | info | premium
   sizes: sm | md
   ═══════════════════════════════════════════════════════════════════════════ */
const BADGE = `<script setup>
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
<\/script>

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
`

/* ═══════════════════════════════════════════════════════════════════════════
   Modal.vue
   Props: open (Boolean, required) · title · size (sm|md|lg|xl)
   Emits: close
   Slots: default (body) · footer
   ═══════════════════════════════════════════════════════════════════════════ */
const MODAL = `<script setup>
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
<\/script>

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
`

/* ═══════════════════════════════════════════════════════════════════════════
   Table.vue
   Slot: head (th's) · body (tr's) · empty (mensaje vacío) · foot
   ═══════════════════════════════════════════════════════════════════════════ */
const TABLE = `<script setup>
defineProps({
  striped:   { type: Boolean, default: false },
  bordered:  { type: Boolean, default: false },
  empty:     { type: Boolean, default: false },
  emptyText: { type: String,  default: 'Sin registros.' },
})
<\/script>

<template>
  <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-stone-100 text-sm">
        <!-- Head -->
        <thead class="bg-stone-50">
          <tr class="text-xs font-semibold uppercase tracking-wide text-stone-500">
            <slot name="head" />
          </tr>
        </thead>

        <!-- Body -->
        <tbody
          :class="[
            'bg-white',
            striped  ? '[&>tr:nth-child(even)]:bg-stone-50/50' : 'divide-y divide-stone-50',
          ]"
        >
          <slot name="body" />

          <!-- Empty state -->
          <tr v-if="empty">
            <td
              colspan="100%"
              class="px-5 py-10 text-center text-sm text-stone-400"
            >
              <slot name="empty">{{ emptyText }}</slot>
            </td>
          </tr>
        </tbody>

        <!-- Foot -->
        <tfoot v-if="$slots.foot" class="border-t border-stone-100 bg-stone-50/40">
          <slot name="foot" />
        </tfoot>
      </table>
    </div>

    <!-- Pagination slot (outside table) -->
    <div v-if="$slots.pagination" class="border-t border-stone-100 bg-stone-50/40 px-5 py-3">
      <slot name="pagination" />
    </div>
  </div>
</template>
`

/* ── Write ───────────────────────────────────────────────────────────────── */
console.log('\n▸ Componentes UI')
write('Components/UI/Button.vue', BUTTON)
write('Components/UI/Input.vue',  INPUT)
write('Components/UI/Select.vue', SELECT)
write('Components/UI/Card.vue',   CARD)
write('Components/UI/Badge.vue',  BADGE)
write('Components/UI/Modal.vue',  MODAL)
write('Components/UI/Table.vue',  TABLE)

console.log('\nDone ✓')
