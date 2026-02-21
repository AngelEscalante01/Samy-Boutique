<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ customer: { type: Object, required: true } })

const form = useForm({
  name:   props.customer.name ?? '',
  phone:  props.customer.phone ?? '',
  email:  props.customer.email ?? '',
  active: props.customer.active ?? true,
})

function submit() { form.put(route('customers.update', props.customer.id)) }
</script>

<template>
  <Head :title="'Editar: ' + (customer.name ?? 'Cliente')" />

  <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('customers.show', customer.id)" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Editar cliente</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ customer.name ?? 'Cliente' }}</p>
      </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre <span class="text-red-500">*</span></label>
        <input v-model="form.name" type="text" placeholder="Nombre completo"
          class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          :class="form.errors.name ? 'border-red-400' : ''" />
        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Telefono</label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 6.75Z" />
          </svg>
          <input v-model="form.phone" type="tel" placeholder="10 digitos"
            class="w-full rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
            :class="form.errors.phone ? 'border-red-400' : ''" />
        </div>
        <p v-if="form.errors.phone" class="mt-1.5 text-xs text-red-600">{{ form.errors.phone }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Correo electronico</label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
          </svg>
          <input v-model="form.email" type="email" placeholder="correo@ejemplo.com"
            class="w-full rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
            :class="form.errors.email ? 'border-red-400' : ''" />
        </div>
        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
      </div>

      <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
        <div>
          <p class="text-sm font-medium text-gray-700">Cliente activo</p>
          <p class="text-xs text-gray-400 mt-0.5">Los clientes inactivos no aparecen en busquedas del POS</p>
        </div>
        <button @click="form.active = !form.active" type="button"
          class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
          :class="form.active ? 'bg-emerald-500' : 'bg-gray-200'">
          <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
            :class="form.active ? 'translate-x-5' : 'translate-x-0'" />
        </button>
      </div>

      <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
        <button @click="submit" :disabled="form.processing"
          class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition disabled:opacity-50">
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
        </button>
        <Link :href="route('customers.show', customer.id)" class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancelar</Link>
      </div>
    </div>
  </div>
</template>
