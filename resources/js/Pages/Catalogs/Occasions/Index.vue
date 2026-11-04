<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'

defineProps({ occasions: { type: Array, default: () => [] } })

const form = useForm({ name: '', sort_order: 0, active: true })

function create() {
  form.post(route('catalogs.occasions.store'), { preserveScroll: true, onSuccess: () => form.reset() })
}

function update(occasion) {
  const name = window.prompt('Nombre de la ocasión', occasion.name)
  if (name === null) return
  const order = window.prompt('Orden de aparición', String(occasion.sort_order))
  if (order === null) return
  router.put(route('catalogs.occasions.update', occasion.id), {
    name, sort_order: Number(order), active: occasion.active,
  }, { preserveScroll: true })
}

function toggle(occasion) {
  router.put(route('catalogs.occasions.update', occasion.id), {
    name: occasion.name, sort_order: occasion.sort_order, active: !occasion.active,
  }, { preserveScroll: true })
}
</script>

<template>
  <Head title="Ocasiones y estilos" />
  <div class="mx-auto max-w-5xl space-y-5 px-4 py-5 app-card-stack app-page">
    <div class="flex items-center justify-between gap-3 app-heading">
      <div>
        <h1 class="text-2xl font-semibold text-stone-900">Ocasiones y estilos</h1>
        <p class="text-sm text-stone-500">Se pueden asociar varias ocasiones a cada producto.</p>
      </div>
      <Link :href="route('catalogs.categories.index')" class="text-sm text-stone-600 underline">Volver a catálogos</Link>
    </div>
    <form class="flex flex-wrap gap-3 rounded-2xl border border-stone-200 bg-white p-4" @submit.prevent="create">
      <div class="min-w-56 flex-1">
        <label for="occasion-name" class="block text-xs font-semibold text-stone-600">Nombre</label>
        <input id="occasion-name" v-model="form.name" required maxlength="120" class="mt-1 w-full rounded-lg border-stone-300 text-sm" placeholder="Ej. XV años">
        <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
      </div>
      <div>
        <label for="occasion-order" class="block text-xs font-semibold text-stone-600">Orden</label>
        <input id="occasion-order" v-model.number="form.sort_order" type="number" min="0" max="9999" required class="mt-1 w-24 rounded-lg border-stone-300 text-sm">
      </div>
      <button type="submit" :disabled="form.processing" class="self-end rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Agregar</button>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white app-surface">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500"><tr><th class="p-3">Nombre</th><th class="p-3">Orden</th><th class="p-3">Productos</th><th class="p-3">Estado</th><th class="p-3">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="occasion in occasions" :key="occasion.id" class="border-b border-stone-100">
            <td class="p-3 font-medium">{{ occasion.name }}</td><td class="p-3">{{ occasion.sort_order }}</td><td class="p-3">{{ occasion.products_count }}</td><td class="p-3">{{ occasion.active ? 'Activa' : 'Inactiva' }}</td>
            <td class="space-x-3 p-3"><button type="button" class="underline" @click="update(occasion)">Editar</button><button type="button" class="underline" @click="toggle(occasion)">{{ occasion.active ? 'Desactivar' : 'Activar' }}</button></td>
          </tr>
          <tr v-if="!occasions.length"><td colspan="5" class="p-6 text-center text-stone-500">Aún no hay ocasiones.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
