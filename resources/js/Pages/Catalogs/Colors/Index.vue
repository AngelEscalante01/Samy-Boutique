<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import CatalogTable from '@/Components/Catalogs/CatalogTable.vue'

defineProps({
  colors: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash ?? {})

const form = useForm({
  name: '',
  hex: '',
  active: true,
})

function submit() {
  form
    .transform((data) => ({
      name: data.name,
      hex: data.hex || null,
      active: true,
    }))
    .post(route('catalogs.colors.store'), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
}
</script>

<template>
  <Head title="Catálogos" />

  <div class="mx-auto max-w-6xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">
    <section class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <h1 class="text-2xl font-bold tracking-tight text-slate-900">Catálogos</h1>
      <p class="mt-0.5 text-sm text-slate-500">Administra valores del sistema</p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-3.5 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)] sm:px-4">
      <div class="inline-flex w-full rounded-xl bg-slate-100 p-1 sm:w-auto">
        <Link
          :href="route('catalogs.categories.index')"
          class="flex-1 rounded-lg px-3 py-1.5 text-center text-xs font-semibold tracking-wide transition sm:text-sm"
          :class="route().current('catalogs.categories.index')
            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
            : 'text-slate-500 hover:text-slate-700'"
        >
          Categorías
        </Link>
        <Link
          :href="route('catalogs.sizes.index')"
          class="flex-1 rounded-lg px-3 py-1.5 text-center text-xs font-semibold tracking-wide transition sm:text-sm"
          :class="route().current('catalogs.sizes.index')
            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
            : 'text-slate-500 hover:text-slate-700'"
        >
          Tallas
        </Link>
        <Link
          :href="route('catalogs.colors.index')"
          class="flex-1 rounded-lg px-3 py-1.5 text-center text-xs font-semibold tracking-wide transition sm:text-sm"
          :class="route().current('catalogs.colors.index')
            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
            : 'text-slate-500 hover:text-slate-700'"
        >
          Colores
        </Link>
      </div>
    </section>

    <section v-if="flash.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
      {{ flash.success }}
    </section>
    <section v-if="flash.error" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">
      {{ flash.error }}
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div class="border-b border-slate-200 bg-slate-50/70 px-3.5 py-2.5 sm:px-4">
        <form class="flex flex-wrap items-start gap-2" @submit.prevent="submit">
          <div class="min-w-[220px] flex-1">
            <input
              v-model="form.name"
              type="text"
              placeholder="Nombre de color"
              class="h-9 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
              :class="{ 'border-rose-400': form.errors.name }"
            >
            <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
          </div>

          <div class="flex items-center gap-2">
            <input
              v-model="form.hex"
              type="color"
              class="h-9 w-9 cursor-pointer rounded border border-slate-300 p-0.5"
              title="Seleccionar color"
            >
            <input
              v-model="form.hex"
              type="text"
              maxlength="7"
              placeholder="#FFFFFF"
              class="h-9 w-28 rounded-lg border border-slate-300 px-3 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
            >
          </div>

          <button
            type="submit"
            :disabled="form.processing || !form.name.trim()"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Agregar
          </button>
        </form>
      </div>

      <CatalogTable
        :items="colors"
        update-route="catalogs.colors.update"
        toggle-route="catalogs.colors.toggle"
        delete-route="catalogs.colors.destroy"
        :show-hex="true"
        record-label="colores"
      />
    </section>
  </div>
</template>
