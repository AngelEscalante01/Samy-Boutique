<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import CatalogTable from '@/Components/Catalogs/CatalogTable.vue'

defineProps({
  colors: { type: Object, required: true },
  filters:     { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash ?? {})

// ── Form crear ────────────────────────────────────────────────────────────────
const form = useForm({
  name:   '',
  hex:    '',
  active: true,
})

function submit() {
  form
    .transform(d => ({
      name:   d.name,
      hex:    d.hex || null,
      active: d.active,
    }))
    .post(route('catalogs.colors.store'), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
}
</script>

<template>
  <Head title="Colores" />

  <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

    <!-- ── Breadcrumb ──────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
      <Link :href="route('dashboard')" class="hover:text-gray-700 transition-colors">Inicio</Link>
      <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <span class="text-gray-400">Catálogos</span>
      <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <span class="font-medium text-gray-700">Colores</span>
    </nav>

    <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Colores</h1>
      <p class="mt-1 text-sm text-gray-500">Administra los valores que aparecen al crear productos.</p>
    </div>

    <!-- ── Tabs de navegación ─────────────────────────────────────────────── -->
    <div class="flex items-center gap-2 rounded-xl bg-gray-100/70 p-1.5 w-fit">
      <Link
            :href="route('catalogs.categories.index')"
            :class="[
              route().current('catalogs.categories.index')
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100',
              'rounded-lg px-4 py-2 text-sm font-medium transition-colors'
            ]"
          >Categorías</Link>
          <Link
            :href="route('catalogs.sizes.index')"
            :class="[
              route().current('catalogs.sizes.index')
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100',
              'rounded-lg px-4 py-2 text-sm font-medium transition-colors'
            ]"
          >Tallas</Link>
          <Link
            :href="route('catalogs.colors.index')"
            :class="[
              route().current('catalogs.colors.index')
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100',
              'rounded-lg px-4 py-2 text-sm font-medium transition-colors'
            ]"
          >Colores</Link>
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
      <div
        v-if="flash.success"
        class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
      >
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
      <div
        v-if="flash.error"
        class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
      >
        <svg class="h-4 w-4 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        {{ flash.error }}
      </div>
    </Transition>

    <!-- ── Card principal ─────────────────────────────────────────────────── -->
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

      <!-- Form crear nuevo -->
      <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
          Agregar Color
        </p>
        <form @submit.prevent="submit" class="flex flex-wrap items-start gap-3">
          <!-- Nombre -->
          <div class="flex flex-col gap-1 min-w-[200px] flex-1">
            <input
              v-model="form.name"
              type="text"
              placeholder="Nombre del color"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': form.errors.name }"
            />
            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
          </div>
          
          <!-- Hex (solo colores) -->
          <div class="flex items-center gap-2">
            <input
              v-model="form.hex"
              type="color"
              class="h-9 w-10 cursor-pointer rounded-lg border border-gray-300 p-0.5 shadow-sm"
              title="Seleccionar color"
            />
            <input
              v-model="form.hex"
              type="text"
              placeholder="#FFFFFF"
              maxlength="7"
              class="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
            />
          </div>
          <!-- Botón -->
          <button
            type="submit"
            :disabled="form.processing || !form.name.trim()"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold
                   text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed
                   transition-colors"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Agregar
          </button>
        </form>
      </div>

      <!-- Tabla / lista -->
      <CatalogTable
        :items="colors"
        update-route="catalogs.colors.update"
        toggle-route="catalogs.colors.toggle"
        delete-route="catalogs.colors.destroy"
        :show-hex="true"
      />

      <!-- Paginación -->
      <div
        v-if="colors.last_page > 1"
        class="flex items-center justify-between border-t border-gray-100 px-5 py-3 bg-gray-50/40"
      >
        <p class="text-xs text-gray-500">
          Página {{ colors.current_page }} de {{ colors.last_page }}
          &middot; {{ colors.total }} registros
        </p>
        <div class="flex gap-1.5">
          <Link
            v-if="colors.prev_page_url"
            :href="colors.prev_page_url"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
          >← Anterior</Link>
          <Link
            v-if="colors.next_page_url"
            :href="colors.next_page_url"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
          >Siguiente →</Link>
        </div>
      </div>
    </div>

  </div>
</template>
