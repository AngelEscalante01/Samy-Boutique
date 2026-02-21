<script setup>
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
    : '$' + money(c.discount_value)
}
</script>

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
                Mínimo <span class="font-semibold text-gray-700">${{ money(c.min_total) }}</span>
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
