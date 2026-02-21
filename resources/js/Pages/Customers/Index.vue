<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import LoyaltyBadge from '@/Components/Customers/LoyaltyBadge.vue'

const props = defineProps({
  customers: { type: Object, required: true },
  filters:   { type: Object, required: true },
  can:       { type: Object, default: () => ({}) },
})

const search = ref(props.filters.q ?? '')

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(route('customers.index'), { q: search.value || undefined }, { preserveState: true, replace: true })
  }, 300)
}
function clearSearch() {
  search.value = ''
  router.get(route('customers.index'), {}, { preserveState: true, replace: true })
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <Head title="Clientes" />

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Clientes</h1>
        <p class="text-sm text-gray-500 mt-0.5">Directorio y fidelidad de clientes</p>
      </div>
      <Link v-if="can.create" :href="route('customers.create')"
        class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo cliente
      </Link>
    </div>

    <!-- Search -->
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
      <div class="relative max-w-md">
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
          fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
        </svg>
        <input v-model="search" @input="onSearchInput" type="text"
          placeholder="Buscar por nombre o telefono..."
          class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-8 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
        <button v-if="search" @click="clearSearch"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
      <!-- Desktop -->
      <table class="min-w-full divide-y divide-gray-100 hidden sm:table">
        <thead class="bg-gray-50">
          <tr>
            <th class="py-3 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nombre</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Telefono</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Compras</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fidelidad</th>
            <th class="py-3 pl-3 pr-5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="customers.data.length === 0">
            <td colspan="5" class="py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <svg class="h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <p class="text-sm text-gray-500">No hay clientes registrados</p>
                <Link v-if="can.create" :href="route('customers.create')"
                  class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 transition">
                  Crear primer cliente
                </Link>
              </div>
            </td>
          </tr>
          <tr v-for="c in customers.data" :key="c.id" class="hover:bg-gray-50 transition">
            <td class="py-3 pl-5 pr-3">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                  <span class="text-xs font-bold text-gray-600">{{ c.name?.charAt(0)?.toUpperCase() ?? '?' }}</span>
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">{{ c.name }}</p>
                  <p v-if="c.email" class="text-xs text-gray-400">{{ c.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-3 py-3 text-sm text-gray-600 font-mono">{{ c.phone ?? '—' }}</td>
            <td class="px-3 py-3 text-center"><span class="text-sm font-bold text-gray-900">{{ c.purchases_count }}</span></td>
            <td class="px-3 py-3"><LoyaltyBadge :count="c.purchases_count" size="sm" /></td>
            <td class="py-3 pl-3 pr-5 text-right">
              <div class="flex items-center justify-end gap-3">
                <Link :href="route('customers.show', c.id)" class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 transition">Ver</Link>
                <Link v-if="can.update" :href="route('customers.edit', c.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 underline underline-offset-2 transition">Editar</Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Mobile cards -->
      <ul class="sm:hidden divide-y divide-gray-100">
        <li v-if="customers.data.length === 0" class="py-12 text-center">
          <p class="text-sm text-gray-400 mb-3">No hay clientes registrados</p>
          <Link v-if="can.create" :href="route('customers.create')"
            class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 transition">Crear cliente</Link>
        </li>
        <li v-for="c in customers.data" :key="c.id" class="p-4 space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="h-9 w-9 rounded-full bg-gray-200 flex items-center justify-center">
                <span class="text-sm font-bold text-gray-600">{{ c.name?.charAt(0)?.toUpperCase() ?? '?' }}</span>
              </div>
              <div>
                <p class="text-sm font-semibold text-gray-900">{{ c.name }}</p>
                <p class="text-xs text-gray-400 font-mono">{{ c.phone ?? '—' }}</p>
              </div>
            </div>
            <span class="text-sm font-bold text-gray-700">{{ c.purchases_count }} compras</span>
          </div>
          <LoyaltyBadge :count="c.purchases_count" size="sm" />
          <div class="flex gap-3 pt-1">
            <Link :href="route('customers.show', c.id)" class="text-xs font-medium text-gray-600 hover:text-gray-900 underline">Ver</Link>
            <Link v-if="can.update" :href="route('customers.edit', c.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 underline">Editar</Link>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="customers.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">Mostrando {{ customers.from }}–{{ customers.to }} de {{ customers.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in customers.links" :key="link.label" :href="link.url ?? '#'"
            :class="['px-2.5 py-1 rounded text-xs font-medium transition',
              link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100',
              !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </div>
</template>
