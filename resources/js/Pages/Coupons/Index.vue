<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import CouponBadge from '@/Components/Coupons/CouponBadge.vue'

const props = defineProps({
  coupons: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const q = ref(props.filters.q ?? '')
const active = ref(props.filters.active ?? '')
const validity = ref(props.filters.validity ?? '')

const statusTabs = [
  { key: '', label: 'Todos' },
  { key: '1', label: 'Activos' },
  { key: '0', label: 'Inactivos' },
]

const validityTabs = [
  { key: 'active', label: 'Vigentes' },
  { key: 'upcoming', label: 'Próximos' },
  { key: 'expired', label: 'Expirados' },
]

const pagination = computed(() => {
  const links = props.coupons?.links ?? []
  if (links.length < 3) {
    return { prev: null, pages: [], next: null }
  }

  return {
    prev: links[0],
    pages: links.slice(1, -1),
    next: links[links.length - 1],
  }
})

const hasFilters = computed(() => !!q.value || active.value !== '' || !!validity.value)

function pageLabel(label) {
  return String(label ?? '').replace(/<[^>]*>/g, '').trim()
}

function apply() {
  router.get(route('coupons.index'), {
    q: q.value || undefined,
    active: active.value !== '' ? active.value : undefined,
    validity: validity.value || undefined,
  }, { preserveState: true, replace: true })
}

function setActiveTab(key) {
  active.value = key
  apply()
}

function setValidityTab(key) {
  validity.value = validity.value === key ? '' : key
  apply()
}

function clearFilters() {
  q.value = ''
  active.value = ''
  validity.value = ''
  router.get(route('coupons.index'), {}, { preserveState: false, replace: true })
}

let searchTimer = null
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(apply, 320)
}

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
})

function fmtDate(dateValue) {
  if (!dateValue) return '—'

  return new Date(dateValue).toLocaleDateString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function money(value) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function valueLabel(coupon) {
  if (coupon.discount_type === 'percent') {
    return `${Number(coupon.discount_value ?? 0)}%`
  }

  return money(coupon.discount_value)
}

function typeLabel(coupon) {
  return coupon.discount_type === 'percent' ? 'Porcentaje' : 'Monto fijo'
}

function usageLabel(coupon) {
  const used = Number(coupon.redemptions_count ?? 0)

  if (coupon.max_redemptions) {
    return `${used} / ${coupon.max_redemptions}`
  }

  return `${used}`
}
</script>

<template>
  <Head title="Cupones" />

  <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Cupones</h1>
        <p class="mt-0.5 text-sm text-slate-500">Gestión de descuentos y promociones</p>
      </div>

      <Link
        :href="route('coupons.create')"
        class="inline-flex h-9 items-center gap-2 rounded-lg bg-slate-900 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo cupón
      </Link>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-3.5 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:px-4">
      <div class="space-y-2">
        <div class="flex flex-wrap gap-2">
          <div class="inline-flex rounded-xl bg-slate-100 p-1">
            <button
              v-for="tab in statusTabs"
              :key="tab.key"
              type="button"
              class="rounded-lg px-3 py-1.5 text-xs font-semibold tracking-wide transition sm:text-sm"
              :class="active === tab.key
                ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                : 'text-slate-500 hover:text-slate-700'"
              @click="setActiveTab(tab.key)"
            >
              {{ tab.label }}
            </button>
          </div>

          <div class="inline-flex rounded-xl bg-slate-100 p-1">
            <button
              v-for="tab in validityTabs"
              :key="tab.key"
              type="button"
              class="rounded-lg px-3 py-1.5 text-xs font-semibold tracking-wide transition sm:text-sm"
              :class="validity === tab.key
                ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                : 'text-slate-500 hover:text-slate-700'"
              @click="setValidityTab(tab.key)"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="relative min-w-[220px] flex-1 md:max-w-sm">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
            </svg>
            <input
              v-model="q"
              type="text"
              placeholder="Buscar por código"
              class="h-9 w-full rounded-lg border border-slate-200 py-1.5 pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
              @input="onSearch"
            >
          </div>

          <button
            v-if="hasFilters"
            type="button"
            class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
            @click="clearFilters"
          >
            Limpiar filtros
          </button>
        </div>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div class="overflow-x-auto">
        <table class="hidden min-w-full divide-y divide-slate-200 md:table">
          <thead class="bg-slate-50/80">
            <tr>
              <th class="py-2.5 pl-4 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Código</th>
              <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo / Valor</th>
              <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Vigencia</th>
              <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Condiciones</th>
              <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Usos</th>
              <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
              <th class="py-2.5 pl-2 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            <tr v-if="coupons.data.length === 0">
              <td colspan="7" class="px-5 py-12 text-center">
                <div class="mx-auto flex max-w-sm flex-col items-center gap-3">
                  <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z" />
                    </svg>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-slate-700">No se encontraron cupones</p>
                    <p class="mt-0.5 text-xs text-slate-400">Crea tu primer promoción para empezar a aplicar descuentos.</p>
                  </div>
                  <Link
                    :href="route('coupons.create')"
                    class="inline-flex h-8 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-700"
                  >
                    Crear cupón
                  </Link>
                </div>
              </td>
            </tr>

            <tr v-for="coupon in coupons.data" :key="coupon.id" class="transition-colors duration-150 hover:bg-slate-50/80">
              <td class="py-2.5 pl-4 pr-2">
                <div class="flex items-center gap-1.5">
                  <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold tracking-wider text-slate-800">
                    {{ coupon.code }}
                  </span>
                  <span v-if="coupon.name" class="truncate text-xs text-slate-400">{{ coupon.name }}</span>
                </div>
              </td>

              <td class="px-2 py-2.5">
                <div class="space-y-0.5">
                  <p class="text-sm font-semibold text-slate-800">{{ valueLabel(coupon) }}</p>
                  <p class="text-xs text-slate-500">{{ typeLabel(coupon) }}</p>
                </div>
              </td>

              <td class="px-2 py-2.5">
                <div class="space-y-1">
                  <CouponBadge :starts-at="coupon.starts_at" :ends-at="coupon.ends_at" size="sm" />
                  <p class="text-xs text-slate-500">
                    <span>{{ fmtDate(coupon.starts_at) }}</span>
                    <span class="text-slate-300"> - </span>
                    <span>{{ fmtDate(coupon.ends_at) }}</span>
                  </p>
                </div>
              </td>

              <td class="px-2 py-2.5">
                <div class="space-y-0.5 text-xs text-slate-500">
                  <p>
                    Mínimo:
                    <span class="font-semibold text-slate-700">
                      {{ coupon.min_total ? money(coupon.min_total) : 'Sin mínimo' }}
                    </span>
                  </p>
                  <p>
                    Límite total:
                    <span class="font-semibold text-slate-700">
                      {{ coupon.max_redemptions ?? 'Sin límite' }}
                    </span>
                  </p>
                  <p>
                    Por cliente:
                    <span class="font-semibold text-slate-700">
                      {{ coupon.max_redemptions_per_customer ?? 'Sin límite' }}
                    </span>
                  </p>
                </div>
              </td>

              <td class="px-2 py-2.5 text-center">
                <span class="text-sm font-bold text-slate-800">{{ usageLabel(coupon) }}</span>
              </td>

              <td class="px-2 py-2.5 text-center">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                  :class="coupon.active
                    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
                    : 'bg-slate-100 text-slate-500 ring-slate-200'"
                >
                  {{ coupon.active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>

              <td class="py-2.5 pl-2 pr-4">
                <div class="flex items-center justify-end">
                  <Link
                    :href="route('coupons.edit', coupon.id)"
                    class="inline-flex h-7 items-center gap-1 rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                  >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                    Editar
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <ul class="divide-y divide-slate-100 md:hidden">
        <li v-if="coupons.data.length === 0" class="px-4 py-8 text-center">
          <p class="text-sm font-semibold text-slate-700">No se encontraron cupones</p>
          <p class="mt-1 text-xs text-slate-400">Crea tu primera promoción para empezar.</p>
          <Link
            :href="route('coupons.create')"
            class="mt-3 inline-flex h-8 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-700"
          >
            Crear cupón
          </Link>
        </li>

        <li v-for="coupon in coupons.data" :key="coupon.id" class="space-y-2.5 px-4 py-3">
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold tracking-wider text-slate-800">{{ coupon.code }}</span>
              <p v-if="coupon.name" class="mt-0.5 text-xs text-slate-400">{{ coupon.name }}</p>
            </div>
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
              :class="coupon.active
                ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
                : 'bg-slate-100 text-slate-500 ring-slate-200'"
            >
              {{ coupon.active ? 'Activo' : 'Inactivo' }}
            </span>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-800">{{ valueLabel(coupon) }}</p>
              <p class="text-xs text-slate-500">{{ typeLabel(coupon) }}</p>
            </div>
            <CouponBadge :starts-at="coupon.starts_at" :ends-at="coupon.ends_at" size="sm" />
          </div>

          <p class="text-xs text-slate-500">
            Vigencia: {{ fmtDate(coupon.starts_at) }} - {{ fmtDate(coupon.ends_at) }}
          </p>

          <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">Usos: <span class="font-semibold text-slate-700">{{ usageLabel(coupon) }}</span></p>
            <Link
              :href="route('coupons.edit', coupon.id)"
              class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              Editar
            </Link>
          </div>
        </li>
      </ul>

      <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5">
        <p class="text-xs text-slate-500">
          Mostrando {{ coupons.from ?? 0 }} a {{ coupons.to ?? 0 }} de {{ coupons.total ?? 0 }} cupones
        </p>

        <nav
          v-if="(coupons.last_page ?? 1) > 1"
          class="flex items-center gap-1"
          aria-label="Paginación de cupones"
        >
          <Link
            :href="pagination.prev?.url ?? '#'"
            class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
            :class="!pagination.prev?.url ? 'pointer-events-none opacity-40' : ''"
          >
            Anterior
          </Link>

          <Link
            v-for="link in pagination.pages"
            :key="link.label"
            :href="link.url ?? '#'"
            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-2 text-xs font-semibold transition"
            :class="[
              link.active
                ? 'border-slate-900 bg-slate-900 text-white'
                : 'border-slate-200 text-slate-600 hover:bg-slate-50',
              !link.url ? 'pointer-events-none opacity-40' : '',
            ]"
          >
            {{ pageLabel(link.label) }}
          </Link>

          <Link
            :href="pagination.next?.url ?? '#'"
            class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
            :class="!pagination.next?.url ? 'pointer-events-none opacity-40' : ''"
          >
            Siguiente
          </Link>
        </nav>
      </div>
    </section>
  </div>
</template>
