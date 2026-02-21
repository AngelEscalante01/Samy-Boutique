<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import RoleBadge from '@/Components/Users/RoleBadge.vue'

const props = defineProps({
  users:   { type: Object, required: true },
  roles:   { type: Array,  default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash ?? {})

// ── Filtros locales ──────────────────────────────────────────────────────────
const localQ    = ref(props.filters?.q    ?? '')
const localRole = ref(props.filters?.role ?? '')

let debounceTimer = null
function onSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => applyFilters(), 350)
}

function applyFilters() {
  router.get(route('users.index'), {
    q:    localQ.value    || undefined,
    role: localRole.value || undefined,
  }, { preserveScroll: true, preserveState: true })
}

function clearFilters() {
  localQ.value = ''
  localRole.value = ''
  router.get(route('users.index'), {}, { preserveScroll: true })
}

// ── Toggle activo ────────────────────────────────────────────────────────────
function toggleActive(user) {
  const action = user.active ? 'desactivar' : 'activar'
  if (!confirm(`¿Deseas ${action} a "${user.name}"?`)) return
  router.patch(route('users.toggleActive', user.id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head title="Usuarios" />

  <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

    <!-- ── Breadcrumb ──────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
      <Link :href="route('dashboard')" class="hover:text-gray-700 transition-colors">Inicio</Link>
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <span class="font-medium text-gray-700">Usuarios</span>
    </nav>

    <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Usuarios</h1>
        <p class="mt-1 text-sm text-gray-500">Gestiona los accesos al sistema</p>
      </div>
      <Link
        :href="route('users.create')"
        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold
               text-white shadow-sm hover:bg-blue-700 transition-colors"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo usuario
      </Link>
    </div>

    <!-- ── Flash ──────────────────────────────────────────────────────────── -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="flash.success"
           class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <svg class="h-4 w-4 flex-shrink-0 mt-0.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        {{ flash.success }}
      </div>
    </Transition>

    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="flash.error"
           class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <svg class="h-4 w-4 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        {{ flash.error }}
      </div>
    </Transition>

    <!-- ── Filtros ────────────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center gap-3">
      <!-- Búsqueda -->
      <div class="relative flex-1 min-w-[200px] max-w-xs">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"
             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input
          v-model="localQ"
          type="text"
          placeholder="Buscar por nombre o email…"
          class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm
                 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
          @input="onSearch"
        />
      </div>

      <!-- Filtro rol -->
      <select
        v-model="localRole"
        class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
               focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
        @change="applyFilters"
      >
        <option value="">Todos los roles</option>
        <option v-for="r in roles" :key="r" :value="r">{{ r.charAt(0).toUpperCase() + r.slice(1) }}</option>
      </select>

      <button
        v-if="localQ || localRole"
        type="button"
        class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-500 hover:bg-gray-100"
        @click="clearFilters"
      >
        Limpiar
      </button>

      <span class="ml-auto text-xs text-gray-400 hidden sm:block">
        {{ users.total }} usuario{{ users.total === 1 ? '' : 's' }}
      </span>
    </div>

    <!-- ── Card principal ─────────────────────────────────────────────────── -->
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

      <!-- Tabla Desktop -->
      <div class="hidden sm:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3 text-left">Nombre</th>
              <th class="px-5 py-3 text-left">Email</th>
              <th class="px-5 py-3 text-left w-28">Rol</th>
              <th class="px-5 py-3 text-center w-28">Estado</th>
              <th class="px-5 py-3 text-left w-28">Alta</th>
              <th class="px-5 py-3 text-right w-32">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50 bg-white">
            <tr v-if="!users.data?.length">
              <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">Sin usuarios.</td>
            </tr>
            <tr
              v-for="user in users.data"
              :key="user.id"
              :class="['transition-colors', user.active ? 'hover:bg-gray-50/60' : 'opacity-50 hover:bg-gray-50/40']"
            >
              <td class="px-5 py-3 font-medium text-gray-900">
                <div class="flex items-center gap-2.5">
                  <!-- Avatar inicial -->
                  <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600">
                    {{ user.name.charAt(0).toUpperCase() }}
                  </span>
                  {{ user.name }}
                </div>
              </td>
              <td class="px-5 py-3 text-gray-500">{{ user.email }}</td>
              <td class="px-5 py-3"><RoleBadge :role="user.role" /></td>
              <td class="px-5 py-3 text-center">
                <span
                  :class="user.active
                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                    : 'bg-gray-100 text-gray-500 ring-gray-200'"
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                >
                  <span :class="user.active ? 'bg-emerald-400' : 'bg-gray-400'"
                        class="mr-1.5 h-1.5 w-1.5 rounded-full inline-block"/>
                  {{ user.active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="px-5 py-3 text-gray-400 text-xs tabular-nums">{{ user.created_at }}</td>
              <td class="px-5 py-3">
                <div class="flex justify-end gap-1.5">
                  <Link
                    :href="route('users.edit', user.id)"
                    class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-gray-300 hover:bg-gray-50 transition-colors"
                    title="Editar"
                  >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                  </Link>
                  <button
                    type="button"
                    :class="user.active
                      ? 'border-amber-200 text-amber-600 hover:bg-amber-50'
                      : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'"
                    class="rounded-lg border p-1.5 transition-colors"
                    :title="user.active ? 'Desactivar' : 'Activar'"
                    @click="toggleActive(user)"
                  >
                    <svg v-if="user.active" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Lista Móvil -->
      <ul class="sm:hidden divide-y divide-gray-100">
        <li v-if="!users.data?.length" class="px-4 py-8 text-center text-sm text-gray-400">Sin usuarios.</li>
        <li
          v-for="user in users.data"
          :key="user.id"
          :class="['px-4 py-3', !user.active && 'opacity-50']"
        >
          <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-600">
              {{ user.name.charAt(0).toUpperCase() }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="font-medium text-gray-800 truncate">{{ user.name }}</p>
              <p class="text-xs text-gray-400 truncate">{{ user.email }}</p>
              <div class="mt-1 flex items-center gap-1.5">
                <RoleBadge :role="user.role" />
                <span
                  :class="user.active ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-500 ring-gray-200'"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1"
                >{{ user.active ? 'Activo' : 'Inactivo' }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
              <Link :href="route('users.edit', user.id)"
                    class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                </svg>
              </Link>
              <button type="button" @click="toggleActive(user)"
                :class="user.active ? 'border-amber-200 text-amber-600' : 'border-emerald-200 text-emerald-600'"
                class="rounded-lg border p-1.5 hover:bg-gray-50">
                <svg v-if="user.active" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
              </button>
            </div>
          </div>
        </li>
      </ul>

      <!-- Paginación -->
      <div
        v-if="users.last_page > 1"
        class="flex items-center justify-between border-t border-gray-100 px-5 py-3 bg-gray-50/40"
      >
        <p class="text-xs text-gray-500">
          Página {{ users.current_page }} de {{ users.last_page }} · {{ users.total }} registros
        </p>
        <div class="flex gap-1.5">
          <Link v-if="users.prev_page_url" :href="users.prev_page_url"
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100">
            ← Anterior
          </Link>
          <Link v-if="users.next_page_url" :href="users.next_page_url"
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100">
            Siguiente →
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>
