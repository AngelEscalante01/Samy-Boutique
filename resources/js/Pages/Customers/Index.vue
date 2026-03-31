<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  customers: { type: Object, required: true },
  filters:   { type: Object, required: true },
  can:       { type: Object, default: () => ({}) },
})

const LOYALTY_CYCLE = 5

const search = ref(props.filters.q ?? '')
const segment = ref(props.filters.segment ?? '')

const segmentOptions = [
  { value: '', label: 'Todos' },
  { value: 'frequent', label: 'Frecuentes' },
  { value: 'new', label: 'Nuevos' },
]

const paginationMeta = computed(() => ({
  from: props.customers?.from ?? 0,
  to: props.customers?.to ?? 0,
  total: props.customers?.total ?? 0,
  lastPage: props.customers?.last_page ?? 1,
}))

const pagination = computed(() => {
  const links = props.customers?.links ?? []
  if (links.length < 3) {
    return { prev: null, pages: [], next: null }
  }

  return {
    prev: links[0],
    pages: links.slice(1, -1),
    next: links[links.length - 1],
  }
})

function applyFilters() {
  router.get(route('customers.index'), {
    q: search.value || undefined,
    segment: segment.value || undefined,
  }, { preserveState: true, replace: true })
}

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 320)
}

function clearSearch() {
  search.value = ''
  applyFilters()
}

function setSegment(value) {
  segment.value = value
  applyFilters()
}

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
})

function pageLabel(label) {
  return String(label ?? '').replace(/<[^>]*>/g, '').trim()
}

function avatarInitial(name) {
  return String(name ?? '?').trim().charAt(0).toUpperCase() || '?'
}

function loyaltyRemaining(count) {
  const safeCount = Math.max(0, Number(count ?? 0))
  const mod = safeCount % LOYALTY_CYCLE
  return mod === 0 ? LOYALTY_CYCLE : LOYALTY_CYCLE - mod
}

function loyaltyText(count) {
  const safeCount = Math.max(0, Number(count ?? 0))
  if (safeCount === 0) return 'Primera compra pendiente'
  if (safeCount % LOYALTY_CYCLE === LOYALTY_CYCLE - 1) return 'A 1 compra del descuento'
  return `Faltan ${loyaltyRemaining(safeCount)} para descuento`
}

function loyaltyBadge(count) {
  const safeCount = Math.max(0, Number(count ?? 0))

  if (safeCount === 0) {
    return {
      label: 'Nuevo',
      className: 'bg-slate-100 text-slate-600 ring-slate-200',
    }
  }

  if (safeCount >= 10) {
    return {
      label: 'Frecuente',
      className: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    }
  }

  if (safeCount % LOYALTY_CYCLE === LOYALTY_CYCLE - 1) {
    return {
      label: 'Casi listo',
      className: 'bg-amber-100 text-amber-700 ring-amber-200',
    }
  }

  return {
    label: 'En progreso',
    className: 'bg-sky-100 text-sky-700 ring-sky-200',
  }
}
</script>

<template>
  <Head title="Clientes" />

  <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Clientes</h1>
        <p class="mt-0.5 text-sm text-slate-500">Directorio y fidelidad de clientes</p>
      </div>

      <Link
        v-if="can.create"
        :href="route('customers.create')"
        class="inline-flex h-9 items-center gap-2 rounded-lg bg-slate-900 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo cliente
      </Link>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-3.5 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:px-4">
      <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div class="relative w-full lg:max-w-md">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
          </svg>
          <input
            v-model="search"
            @input="onSearchInput"
            type="text"
            placeholder="Buscar por nombre o telefono"
            class="h-9 w-full rounded-lg border border-slate-200 py-1.5 pl-8 pr-8 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
          >
          <button
            v-if="search"
            type="button"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600"
            @click="clearSearch"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="inline-flex w-full rounded-xl bg-slate-100 p-1 lg:w-auto">
          <button
            v-for="option in segmentOptions"
            :key="option.value"
            type="button"
            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold tracking-wide transition sm:text-sm"
            :class="segment === option.value
              ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
              : 'text-slate-500 hover:text-slate-700'"
            @click="setSegment(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div class="overflow-x-auto">
        <table class="hidden min-w-full divide-y divide-slate-200 md:table">
          <thead class="bg-slate-50/80">
            <tr>
              <th class="py-2.5 pl-4 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</th>
              <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Telefono</th>
              <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Compras</th>
              <th class="px-2 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fidelidad</th>
              <th class="py-2.5 pl-2 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            <tr v-if="customers.data.length === 0">
              <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No hay clientes para mostrar</td>
            </tr>

            <tr v-for="c in customers.data" :key="c.id" class="transition-colors duration-150 hover:bg-slate-50/80">
              <td class="py-2.5 pl-4 pr-2">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200">
                    <span class="text-xs font-bold text-slate-700">{{ avatarInitial(c.name) }}</span>
                  </div>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ c.name }}</p>
                    <p v-if="c.email" class="truncate text-xs text-slate-400">{{ c.email }}</p>
                  </div>
                </div>
              </td>

              <td class="px-2 py-2.5 text-sm font-medium text-slate-600">{{ c.phone ?? '—' }}</td>

              <td class="px-2 py-2.5 text-center">
                <span class="text-lg font-extrabold tracking-tight text-slate-900">{{ c.purchases_count }}</span>
              </td>

              <td class="px-2 py-2.5">
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                    :class="loyaltyBadge(c.purchases_count).className"
                  >
                    {{ loyaltyBadge(c.purchases_count).label }}
                  </span>
                  <span class="text-xs text-slate-500">{{ loyaltyText(c.purchases_count) }}</span>
                </div>
              </td>

              <td class="py-2.5 pl-2 pr-4">
                <div class="flex items-center justify-end gap-1.5">
                  <Link
                    :href="route('customers.show', c.id)"
                    class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                  >
                    Ver
                  </Link>
                  <Link
                    v-if="can.update"
                    :href="route('customers.edit', c.id)"
                    class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-indigo-700 transition hover:bg-indigo-50"
                  >
                    Editar
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <ul class="divide-y divide-slate-100 md:hidden">
        <li v-if="customers.data.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
          No hay clientes para mostrar
        </li>

        <li v-for="c in customers.data" :key="c.id" class="space-y-2.5 px-4 py-3">
          <div class="flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200">
                <span class="text-sm font-bold text-slate-700">{{ avatarInitial(c.name) }}</span>
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ c.name }}</p>
                <p class="text-xs text-slate-500">{{ c.phone ?? '—' }}</p>
              </div>
            </div>
            <span class="text-base font-extrabold tracking-tight text-slate-800">{{ c.purchases_count }}</span>
          </div>

          <div class="flex items-center gap-2">
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
              :class="loyaltyBadge(c.purchases_count).className"
            >
              {{ loyaltyBadge(c.purchases_count).label }}
            </span>
            <span class="text-xs text-slate-500">{{ loyaltyText(c.purchases_count) }}</span>
          </div>

          <div class="flex items-center justify-end gap-1.5">
            <Link
              :href="route('customers.show', c.id)"
              class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              Ver
            </Link>
            <Link
              v-if="can.update"
              :href="route('customers.edit', c.id)"
              class="inline-flex h-7 items-center rounded-md border border-slate-200 px-2 text-[11px] font-semibold text-indigo-700 transition hover:bg-indigo-50"
            >
              Editar
            </Link>
          </div>
        </li>
      </ul>

      <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5">
        <p class="text-xs text-slate-500">
          Total de clientes: {{ paginationMeta.total }}. Mostrando {{ paginationMeta.from }} a {{ paginationMeta.to }}.
        </p>

        <nav
          v-if="paginationMeta.lastPage > 1"
          class="flex items-center gap-1"
          aria-label="Paginacion de clientes"
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
