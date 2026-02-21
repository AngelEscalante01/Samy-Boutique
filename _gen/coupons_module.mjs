import { writeFileSync, mkdirSync } from 'fs'
import { join, dirname } from 'path'

const base = 'c:/xampp/htdocs/Samy-Boutique/resources/js'

// ─── CouponBadge.vue ──────────────────────────────────────────────────────────
const couponBadge = `<script setup>
import { computed } from 'vue'

const props = defineProps({
  startsAt: { type: String, default: null },
  endsAt:   { type: String, default: null },
  size:     { type: String, default: 'sm' }, // sm | md
})

const now = new Date()

const validity = computed(() => {
  const start = props.startsAt ? new Date(props.startsAt) : null
  const end   = props.endsAt   ? new Date(props.endsAt)   : null

  if (end && end < now)    return { key: 'expired',  label: 'Expirado',  cls: 'bg-red-100    text-red-700     ring-red-200'     }
  if (start && start > now) return { key: 'upcoming', label: 'Próximo',   cls: 'bg-blue-100   text-blue-700    ring-blue-200'    }
  return                          { key: 'active',   label: 'Vigente',   cls: 'bg-emerald-100 text-emerald-700 ring-emerald-200' }
})

const sizeClass = {
  sm: 'px-2 py-0.5 text-xs font-semibold',
  md: 'px-2.5 py-1 text-sm font-semibold',
}
<\/script>

<template>
  <span class="inline-flex items-center rounded-full ring-1"
    :class="[validity.cls, sizeClass[size]]">
    {{ validity.label }}
  </span>
</template>
`

// ─── Toggle.vue ───────────────────────────────────────────────────────────────
const toggle = `<script setup>
const props   = defineProps({ modelValue: { type: Boolean, default: false } })
const emit    = defineEmits(['update:modelValue'])
const toggle  = () => emit('update:modelValue', !props.modelValue)
<\/script>

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
`

// ─── Coupons/Index.vue ────────────────────────────────────────────────────────
const couponsIndex = `<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import CouponBadge from '@/Components/Coupons/CouponBadge.vue'

const props = defineProps({
  coupons: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const q        = ref(props.filters.q        ?? '')
const active   = ref(props.filters.active   ?? '')
const validity = ref(props.filters.validity ?? '')

const activeTab = [
  { key: '',  label: 'Todos'     },
  { key: '1', label: 'Activos'   },
  { key: '0', label: 'Inactivos' },
]
const validityTabs = [
  { key: '',         label: 'Todos'     },
  { key: 'active',   label: 'Vigentes'  },
  { key: 'upcoming', label: 'Próximos'  },
  { key: 'expired',  label: 'Expirados' },
]

function apply() {
  router.get(route('coupons.index'), {
    q:        q.value        || undefined,
    active:   active.value   !== '' ? active.value   : undefined,
    validity: validity.value || undefined,
  }, { preserveState: true, replace: true })
}
const hasFilters = computed(() => !!q.value || active.value !== '' || !!validity.value)
function clearFilters() {
  q.value = ''; active.value = ''; validity.value = ''
  router.get(route('coupons.index'), {}, { preserveState: false, replace: true })
}

let searchTimer = null
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(apply, 300)
}

// ── Vigencia calculada en frontend ────────────────────────────────────────────
const now = new Date()
function validityKey(c) {
  const start = c.starts_at ? new Date(c.starts_at) : null
  const end   = c.ends_at   ? new Date(c.ends_at)   : null
  if (end && end < now)    return 'expired'
  if (start && start > now) return 'upcoming'
  return 'active'
}

// Filtro frontend de vigencia (ya que el backend no tiene ese filtro aún)
const rows = computed(() => {
  if (!validity.value) return props.coupons.data
  return props.coupons.data.filter(c => validityKey(c) === validity.value)
})

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
function money(v)   { return Number(v ?? 0).toFixed(2) }
function valueLabel(c) {
  return c.discount_type === 'percent'
    ? c.discount_value + '%'
    : '\$' + money(c.discount_value)
}
<\/script>

<template>
  <Head title="Cupones" />

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Cupones</h1>
        <p class="text-sm text-gray-500 mt-0.5">Gestión de descuentos y promociones</p>
      </div>
      <Link :href="route('coupons.create')"
        class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo cupón
      </Link>
    </div>

    <!-- Filters card -->
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 space-y-4">

      <!-- Active tabs -->
      <div class="flex gap-0.5 border-b border-gray-200">
        <button v-for="tab in activeTab" :key="tab.key"
          @click="active = tab.key; apply()"
          class="px-3 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
          :class="active === tab.key
            ? 'border-gray-900 text-gray-900'
            : 'border-transparent text-gray-500 hover:text-gray-700'">
          {{ tab.label }}
        </button>
        <span class="mx-2 self-center text-gray-200">|</span>
        <button v-for="tab in validityTabs" :key="tab.key"
          @click="validity = tab.key"
          class="px-3 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
          :class="validity === tab.key
            ? 'border-indigo-500 text-indigo-600'
            : 'border-transparent text-gray-400 hover:text-gray-600'">
          {{ tab.label }}
        </button>
      </div>

      <!-- Search + clear -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[200px] max-w-sm">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
          </svg>
          <input v-model="q" @input="onSearch" type="text" placeholder="Buscar por código..."
            class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
        </div>
        <button v-if="hasFilters" @click="clearFilters"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
          Limpiar filtros
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 hidden sm:table">
        <thead class="bg-gray-50">
          <tr>
            <th class="py-3 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Código</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo / Valor</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Vigencia</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Condiciones</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Usos</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
            <th class="py-3 pl-3 pr-5 text-right" />
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="rows.length === 0">
            <td colspan="7" class="py-16 text-center">
              <svg class="mx-auto h-10 w-10 text-gray-200 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
              </svg>
              <p class="text-sm text-gray-400 mb-3">No hay cupones con estos filtros</p>
              <Link :href="route('coupons.create')"
                class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 transition">
                Crear primer cupón
              </Link>
            </td>
          </tr>
          <tr v-for="c in rows" :key="c.id"
            class="transition"
            :class="validityKey(c) === 'active' && c.active ? 'hover:bg-emerald-50/40' : 'hover:bg-gray-50'">
            <!-- Código -->
            <td class="py-3 pl-5 pr-3">
              <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-gray-900 uppercase tracking-wider">{{ c.code }}</span>
                <span v-if="c.name" class="text-xs text-gray-400 truncate max-w-[120px]">{{ c.name }}</span>
              </div>
            </td>
            <!-- Tipo / Valor -->
            <td class="px-3 py-3">
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                  :class="c.discount_type === 'percent'
                    ? 'bg-violet-50 text-violet-700 ring-violet-200'
                    : 'bg-teal-50   text-teal-700   ring-teal-200'">
                  {{ c.discount_type === 'percent' ? '%' : '$' }}
                </span>
                <span class="text-sm font-bold text-gray-900">{{ valueLabel(c) }}</span>
              </div>
            </td>
            <!-- Vigencia -->
            <td class="px-3 py-3">
              <CouponBadge :starts-at="c.starts_at" :ends-at="c.ends_at" />
              <p class="text-xs text-gray-400 mt-1">
                <span v-if="c.starts_at">{{ fmtDate(c.starts_at) }}</span>
                <span v-if="c.starts_at && c.ends_at"> → </span>
                <span v-if="c.ends_at">{{ fmtDate(c.ends_at) }}</span>
                <span v-if="!c.starts_at && !c.ends_at" class="italic">Sin límite</span>
              </p>
            </td>
            <!-- Condiciones -->
            <td class="px-3 py-3 space-y-1">
              <p v-if="c.min_total" class="text-xs text-gray-500">
                Mínimo <span class="font-semibold text-gray-700">\${{ money(c.min_total) }}</span>
              </p>
              <p v-if="c.max_redemptions" class="text-xs text-gray-500">
                Máx <span class="font-semibold text-gray-700">{{ c.max_redemptions }}</span> usos
              </p>
              <p v-if="c.max_redemptions_per_customer" class="text-xs text-gray-500">
                <span class="font-semibold text-gray-700">{{ c.max_redemptions_per_customer }}</span> por cliente
              </p>
              <span v-if="!c.min_total && !c.max_redemptions && !c.max_redemptions_per_customer"
                class="text-xs text-gray-300 italic">Sin condiciones</span>
            </td>
            <!-- Usos -->
            <td class="px-3 py-3 text-center">
              <span class="text-sm font-bold text-gray-700">{{ c.redemptions_count ?? '—' }}</span>
              <span v-if="c.max_redemptions" class="text-xs text-gray-400"> / {{ c.max_redemptions }}</span>
            </td>
            <!-- Estado -->
            <td class="px-3 py-3 text-center">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                :class="c.active
                  ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
                  : 'bg-gray-100    text-gray-500    ring-gray-200'">
                {{ c.active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <!-- Acción -->
            <td class="py-3 pl-3 pr-5 text-right">
              <Link :href="route('coupons.edit', c.id)"
                class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 transition">
                Editar
              </Link>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Mobile cards -->
      <ul class="sm:hidden divide-y divide-gray-100">
        <li v-if="rows.length === 0" class="py-12 text-center space-y-3">
          <p class="text-sm text-gray-400">No hay cupones con estos filtros</p>
          <Link :href="route('coupons.create')"
            class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 transition">
            Crear cupón
          </Link>
        </li>
        <li v-for="c in rows" :key="c.id" class="p-4 space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="font-mono font-bold text-gray-900 uppercase tracking-wider">{{ c.code }}</span>
              <p v-if="c.name" class="text-xs text-gray-400 mt-0.5">{{ c.name }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                :class="c.active ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-500 ring-gray-200'">
                {{ c.active ? 'Activo' : 'Inactivo' }}
              </span>
              <CouponBadge :starts-at="c.starts_at" :ends-at="c.ends_at" />
            </div>
          </div>
          <div class="flex items-center gap-3 flex-wrap text-sm">
            <span class="font-bold text-gray-900">{{ valueLabel(c) }}</span>
            <span class="text-gray-400 text-xs">
              {{ c.starts_at || c.ends_at ? fmtDate(c.starts_at) + ' – ' + fmtDate(c.ends_at) : 'Sin vigencia' }}
            </span>
          </div>
          <div class="flex justify-between items-center pt-1">
            <span v-if="c.redemptions_count != null" class="text-xs text-gray-500">
              {{ c.redemptions_count }} usos<span v-if="c.max_redemptions"> / {{ c.max_redemptions }}</span>
            </span>
            <Link :href="route('coupons.edit', c.id)"
              class="text-xs font-medium text-gray-600 hover:text-gray-900 underline">Editar</Link>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="coupons.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">Mostrando {{ coupons.from }}–{{ coupons.to }} de {{ coupons.total }}</p>
        <div class="flex flex-wrap gap-1">
          <Link v-for="link in coupons.links" :key="link.label" :href="link.url ?? '#'"
            :class="['px-2.5 py-1 rounded text-xs font-medium transition',
              link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100',
              !link.url   ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </div>
</template>
`

// ─── Coupons/Create.vue ───────────────────────────────────────────────────────
const couponsCreate = `<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Toggle from '@/Components/Forms/Toggle.vue'

const form = useForm({
  code:                         '',
  name:                         '',
  active:                       true,
  discount_type:                'percent',
  discount_value:               '',
  min_total:                    '',
  starts_at:                    '',
  ends_at:                      '',
  max_redemptions:              '',
  max_redemptions_per_customer: '',
})

function submit() {
  form.transform(data => ({
    ...data,
    code:                         data.code.toUpperCase().trim(),
    min_total:                    data.min_total               || null,
    starts_at:                    data.starts_at               || null,
    ends_at:                      data.ends_at                 || null,
    max_redemptions:              data.max_redemptions         || null,
    max_redemptions_per_customer: data.max_redemptions_per_customer || null,
  })).post(route('coupons.store'))
}

// ── Validaciones frontend ─────────────────────────────────────────────────────
const warnPercent = computed(() =>
  form.discount_type === 'percent' && Number(form.discount_value) > 100
)
const errorDateRange = computed(() =>
  form.starts_at && form.ends_at && form.ends_at < form.starts_at
)

// Forzar uppercase mientras escribe
watch(() => form.code, (val) => {
  form.code = val.toUpperCase().replace(/\s/g, '')
})
<\/script>

<template>
  <Head title="Nuevo cupón" />

  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Back + title -->
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('coupons.index')" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <h1 class="text-2xl font-bold text-gray-900">Nuevo cupón</h1>
    </div>

    <form @submit.prevent="submit" class="space-y-6">

      <!-- ── Sección A: Datos principales ─────────────────────────────────── -->
      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-5 flex items-center gap-2">
          <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
          </svg>
          Datos principales
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

          <!-- Código -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Código <span class="text-red-500">*</span>
            </label>
            <input v-model="form.code" type="text" maxlength="60"
              placeholder="Ej: FEB14, VERANO20, NUEVO10"
              class="w-full rounded-lg border py-2.5 px-3 text-sm font-mono uppercase tracking-wider placeholder-gray-300 focus:outline-none focus:ring-1"
              :class="form.errors.code
                ? 'border-red-400 focus:border-red-400 focus:ring-red-300'
                : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            <p v-if="form.errors.code" class="mt-1.5 text-xs text-red-600">{{ form.errors.code }}</p>
            <p v-else class="mt-1.5 text-xs text-gray-400">Solo letras, números y guiones. Se guarda en mayúsculas.</p>
          </div>

          <!-- Nombre (opcional) -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Nombre descriptivo <span class="text-xs font-normal text-gray-400">(opcional)</span>
            </label>
            <input v-model="form.name" type="text" maxlength="120"
              placeholder="Ej: Promoción Febrero 2026"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <!-- Tipo descuento -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Tipo de descuento <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
              <label v-for="opt in [{ value: 'percent', label: '% Porcentaje' }, { value: 'amount', label: '$ Monto fijo' }]" :key="opt.value"
                class="flex items-center gap-2.5 rounded-lg border cursor-pointer px-3 py-2.5 transition"
                :class="form.discount_type === opt.value
                  ? 'border-gray-900 bg-gray-900 text-white'
                  : 'border-gray-200 text-gray-700 hover:border-gray-400'">
                <input type="radio" v-model="form.discount_type" :value="opt.value" class="sr-only" />
                <span class="text-sm font-medium">{{ opt.label }}</span>
              </label>
            </div>
          </div>

          <!-- Valor -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Valor <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-gray-400 select-none">
                {{ form.discount_type === 'percent' ? '%' : '$' }}
              </span>
              <input v-model="form.discount_value" type="number" min="0.01" step="0.01"
                :placeholder="form.discount_type === 'percent' ? '10' : '50'"
                class="w-full rounded-lg border py-2.5 pl-7 pr-3 text-sm focus:outline-none focus:ring-1"
                :class="form.errors.discount_value || warnPercent
                  ? 'border-amber-400 focus:border-amber-400 focus:ring-amber-300'
                  : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            </div>
            <p v-if="form.errors.discount_value" class="mt-1.5 text-xs text-red-600">{{ form.errors.discount_value }}</p>
            <p v-else-if="warnPercent" class="mt-1.5 text-xs text-amber-600">
              ⚠ Un descuento mayor a 100% dará precio negativo.
            </p>
          </div>

          <!-- Estado activo -->
          <div class="sm:col-span-2 flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
            <div>
              <p class="text-sm font-medium text-gray-700">Cupón activo</p>
              <p class="text-xs text-gray-400 mt-0.5">Solo los cupones activos pueden aplicarse en el POS</p>
            </div>
            <Toggle v-model="form.active" />
          </div>

        </div>
      </div>

      <!-- ── Sección B: Reglas ──────────────────────────────────────────────── -->
      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-5 flex items-center gap-2">
          <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
          </svg>
          Reglas y vigencia
          <span class="text-xs font-normal text-gray-400">(todos opcionales)</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

          <!-- Vigencia desde -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Válido desde</label>
            <input v-model="form.starts_at" type="date"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <!-- Vigencia hasta -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Válido hasta</label>
            <input v-model="form.ends_at" type="date"
              class="w-full rounded-lg border py-2.5 px-3 text-sm focus:outline-none focus:ring-1"
              :class="errorDateRange
                ? 'border-red-400 focus:border-red-400 focus:ring-red-300'
                : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            <p v-if="form.errors.ends_at" class="mt-1.5 text-xs text-red-600">{{ form.errors.ends_at }}</p>
            <p v-else-if="errorDateRange" class="mt-1.5 text-xs text-red-600">La fecha final debe ser posterior a la inicial.</p>
          </div>

          <!-- Mínimo de compra -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Compra mínima</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input v-model="form.min_total" type="number" min="0" step="0.01" placeholder="0.00"
                class="w-full rounded-lg border border-gray-200 py-2.5 pl-7 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
            </div>
          </div>

          <!-- Máx usos total -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Límite de usos total</label>
            <input v-model="form.max_redemptions" type="number" min="1" step="1" placeholder="Sin límite"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <!-- Máx usos por cliente -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Límite por cliente</label>
            <input v-model="form.max_redemptions_per_customer" type="number" min="1" step="1" placeholder="Sin límite"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

        </div>
      </div>

      <!-- Botones -->
      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing || !!errorDateRange"
          class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Guardando...' : 'Crear cupón' }}
        </button>
        <Link :href="route('coupons.index')"
          class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
          Cancelar
        </Link>
      </div>

    </form>
  </div>
</template>
`

// ─── Coupons/Edit.vue ─────────────────────────────────────────────────────────
const couponsEdit = `<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Toggle      from '@/Components/Forms/Toggle.vue'
import CouponBadge from '@/Components/Coupons/CouponBadge.vue'

const props = defineProps({
  coupon: { type: Object, required: true },
})

const flash = computed(() => usePage().props.flash ?? {})

function isoToDate(iso) {
  if (!iso) return ''
  return iso.substring(0, 10) // YYYY-MM-DD
}

const form = useForm({
  code:                         props.coupon.code,
  name:                         props.coupon.name ?? '',
  active:                       props.coupon.active,
  discount_type:                props.coupon.discount_type,
  discount_value:               props.coupon.discount_value,
  min_total:                    props.coupon.min_total  ?? '',
  starts_at:                    isoToDate(props.coupon.starts_at),
  ends_at:                      isoToDate(props.coupon.ends_at),
  max_redemptions:              props.coupon.max_redemptions              ?? '',
  max_redemptions_per_customer: props.coupon.max_redemptions_per_customer ?? '',
})

function submit() {
  form.transform(data => ({
    ...data,
    code:                         data.code.toUpperCase().trim(),
    min_total:                    data.min_total               || null,
    starts_at:                    data.starts_at               || null,
    ends_at:                      data.ends_at                 || null,
    max_redemptions:              data.max_redemptions         || null,
    max_redemptions_per_customer: data.max_redemptions_per_customer || null,
  })).put(route('coupons.update', props.coupon.id))
}

// ── Validaciones frontend ─────────────────────────────────────────────────────
const warnPercent   = computed(() => form.discount_type === 'percent' && Number(form.discount_value) > 100)
const errorDateRange = computed(() => form.starts_at && form.ends_at && form.ends_at < form.starts_at)

watch(() => form.code, (val) => { form.code = val.toUpperCase().replace(/\s/g, '') })

// ── Resumen de vigencia ───────────────────────────────────────────────────────
function money(v)    { return Number(v ?? 0).toFixed(2) }
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
function valueLabel() {
  return props.coupon.discount_type === 'percent'
    ? props.coupon.discount_value + '%'
    : '\$' + money(props.coupon.discount_value)
}
<\/script>

<template>
  <Head :title="'Editar: ' + coupon.code" />

  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Back + title -->
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('coupons.index')" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <div>
        <h1 class="text-2xl font-bold text-gray-900 font-mono uppercase">{{ coupon.code }}</h1>
        <p v-if="coupon.name" class="text-sm text-gray-500 mt-0.5">{{ coupon.name }}</p>
      </div>
    </div>

    <!-- Flash -->
    <div v-if="flash.success"
      class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
      <svg class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
      </svg>
      {{ flash.success }}
    </div>

    <!-- ── Resumen ──────────────────────────────────────────────────────────── -->
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 mb-6">
      <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Resumen actual</h2>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">

        <div>
          <p class="text-xs text-gray-400 mb-1">Vigencia</p>
          <CouponBadge :starts-at="coupon.starts_at" :ends-at="coupon.ends_at" size="md" />
        </div>

        <div>
          <p class="text-xs text-gray-400 mb-1">Descuento</p>
          <p class="text-lg font-bold text-gray-900">{{ valueLabel() }}</p>
        </div>

        <div>
          <p class="text-xs text-gray-400 mb-1">Usos registrados</p>
          <p class="text-lg font-bold text-gray-900">
            {{ coupon.redemptions_count ?? '—' }}
            <span v-if="coupon.max_redemptions" class="text-sm font-normal text-gray-400"> / {{ coupon.max_redemptions }}</span>
          </p>
        </div>

        <div>
          <p class="text-xs text-gray-400 mb-1">Estado</p>
          <span class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-semibold ring-1"
            :class="coupon.active
              ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
              : 'bg-gray-100    text-gray-500    ring-gray-200'">
            {{ coupon.active ? 'Activo' : 'Inactivo' }}
          </span>
        </div>

        <div v-if="coupon.starts_at || coupon.ends_at" class="col-span-2 sm:col-span-4">
          <p class="text-xs text-gray-400 mb-1">Período</p>
          <p class="text-sm text-gray-600">
            {{ fmtDate(coupon.starts_at) }} → {{ fmtDate(coupon.ends_at) }}
          </p>
        </div>

      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-6">

      <!-- ── Sección A ──────────────────────────────────────────────────────── -->
      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-5 flex items-center gap-2">
          <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z" />
          </svg>
          Datos principales
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

          <!-- Código -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Código <span class="text-red-500">*</span>
            </label>
            <input v-model="form.code" type="text" maxlength="60"
              class="w-full rounded-lg border py-2.5 px-3 text-sm font-mono uppercase tracking-wider focus:outline-none focus:ring-1"
              :class="form.errors.code
                ? 'border-red-400 focus:border-red-400 focus:ring-red-300'
                : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            <p v-if="form.errors.code" class="mt-1.5 text-xs text-red-600">{{ form.errors.code }}</p>
          </div>

          <!-- Nombre -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Nombre descriptivo <span class="text-xs font-normal text-gray-400">(opcional)</span>
            </label>
            <input v-model="form.name" type="text" maxlength="120"
              placeholder="Ej: Promoción Febrero 2026"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <!-- Tipo descuento -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Tipo de descuento <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
              <label v-for="opt in [{ value: 'percent', label: '% Porcentaje' }, { value: 'amount', label: '$ Monto fijo' }]" :key="opt.value"
                class="flex items-center gap-2.5 rounded-lg border cursor-pointer px-3 py-2.5 transition"
                :class="form.discount_type === opt.value
                  ? 'border-gray-900 bg-gray-900 text-white'
                  : 'border-gray-200 text-gray-700 hover:border-gray-400'">
                <input type="radio" v-model="form.discount_type" :value="opt.value" class="sr-only" />
                <span class="text-sm font-medium">{{ opt.label }}</span>
              </label>
            </div>
          </div>

          <!-- Valor -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Valor <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-gray-400 select-none">
                {{ form.discount_type === 'percent' ? '%' : '$' }}
              </span>
              <input v-model="form.discount_value" type="number" min="0.01" step="0.01"
                class="w-full rounded-lg border py-2.5 pl-7 pr-3 text-sm focus:outline-none focus:ring-1"
                :class="form.errors.discount_value || warnPercent
                  ? 'border-amber-400 focus:border-amber-400 focus:ring-amber-300'
                  : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            </div>
            <p v-if="form.errors.discount_value" class="mt-1.5 text-xs text-red-600">{{ form.errors.discount_value }}</p>
            <p v-else-if="warnPercent" class="mt-1.5 text-xs text-amber-600">
              ⚠ Un descuento mayor a 100% dará precio negativo.
            </p>
          </div>

          <!-- Activo toggle -->
          <div class="sm:col-span-2 flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
            <div>
              <p class="text-sm font-medium text-gray-700">Cupón activo</p>
              <p class="text-xs text-gray-400 mt-0.5">Solo los cupones activos pueden aplicarse en el POS</p>
            </div>
            <Toggle v-model="form.active" />
          </div>

        </div>
      </div>

      <!-- ── Sección B: Reglas ──────────────────────────────────────────────── -->
      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-5 flex items-center gap-2">
          <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
          </svg>
          Reglas y vigencia
          <span class="text-xs font-normal text-gray-400">(todos opcionales)</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Válido desde</label>
            <input v-model="form.starts_at" type="date"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Válido hasta</label>
            <input v-model="form.ends_at" type="date"
              class="w-full rounded-lg border py-2.5 px-3 text-sm focus:outline-none focus:ring-1"
              :class="errorDateRange
                ? 'border-red-400 focus:border-red-400 focus:ring-red-300'
                : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            <p v-if="form.errors.ends_at" class="mt-1.5 text-xs text-red-600">{{ form.errors.ends_at }}</p>
            <p v-else-if="errorDateRange" class="mt-1.5 text-xs text-red-600">La fecha final debe ser posterior a la inicial.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Compra mínima</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input v-model="form.min_total" type="number" min="0" step="0.01" placeholder="0.00"
                class="w-full rounded-lg border border-gray-200 py-2.5 pl-7 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Límite de usos total</label>
            <input v-model="form.max_redemptions" type="number" min="1" step="1" placeholder="Sin límite"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Límite por cliente</label>
            <input v-model="form.max_redemptions_per_customer" type="number" min="1" step="1" placeholder="Sin límite"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

        </div>
      </div>

      <!-- Botones -->
      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing || !!errorDateRange"
          class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
        </button>
        <Link :href="route('coupons.index')"
          class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
          Cancelar
        </Link>
      </div>

    </form>
  </div>
</template>
`

const files = [
  ['Components/Coupons/CouponBadge.vue', couponBadge],
  ['Components/Forms/Toggle.vue',        toggle],
  ['Pages/Coupons/Index.vue',           couponsIndex],
  ['Pages/Coupons/Create.vue',          couponsCreate],
  ['Pages/Coupons/Edit.vue',            couponsEdit],
]

files.forEach(([rel, content]) => {
  const fullPath = join(base, rel)
  mkdirSync(dirname(fullPath), { recursive: true })
  writeFileSync(fullPath, content, 'utf-8')
  console.log('OK', rel)
})
