<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Toggle from '@/Components/Forms/Toggle.vue'

defineProps({
  roles: { type: Array, default: () => [] },
})

const form = useForm({
  name:                  '',
  email:                 '',
  role:                  'cajero',
  password:              '',
  password_confirmation: '',
  active:                true,
})

function submit() {
  form.post(route('users.store'), { preserveScroll: true })
}
</script>

<template>
  <Head title="Nuevo usuario" />

  <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">

    <!-- ── Breadcrumb ──────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
      <Link :href="route('dashboard')" class="hover:text-gray-700 transition-colors">Inicio</Link>
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <Link :href="route('users.index')" class="hover:text-gray-700 transition-colors">Usuarios</Link>
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
      </svg>
      <span class="font-medium text-gray-700">Nuevo</span>
    </nav>

    <div>
      <h1 class="text-2xl font-bold text-gray-900">Nuevo usuario</h1>
      <p class="mt-1 text-sm text-gray-500">Crea un acceso al sistema</p>
    </div>

    <!-- ── Formulario ─────────────────────────────────────────────────────── -->
    <form @submit.prevent="submit" class="space-y-5">
      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

        <!-- Sección A: Datos del usuario -->
        <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-700">Datos del usuario</h2>
        </div>

        <div class="grid gap-5 p-5 sm:grid-cols-2">
          <!-- Nombre -->
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Nombre <span class="text-red-400">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Ej: María López"
              autocomplete="name"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': form.errors.name }"
            />
            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
          </div>

          <!-- Email -->
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Email <span class="text-red-400">*</span></label>
            <input
              v-model="form.email"
              type="email"
              placeholder="correo@ejemplo.com"
              autocomplete="email"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': form.errors.email }"
            />
            <p v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</p>
          </div>

          <!-- Rol -->
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Rol <span class="text-red-400">*</span></label>
            <select
              v-model="form.role"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm bg-white
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': form.errors.role }"
            >
              <option v-for="r in roles" :key="r" :value="r">
                {{ r.charAt(0).toUpperCase() + r.slice(1) }}
              </option>
            </select>
            <p v-if="form.errors.role" class="text-xs text-red-500">{{ form.errors.role }}</p>
          </div>

          <!-- Activo -->
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Estado inicial</label>
            <div class="flex items-center gap-3 pt-1">
              <Toggle v-model="form.active" />
              <span class="text-sm" :class="form.active ? 'text-emerald-600' : 'text-gray-400'">
                {{ form.active ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Sección B: Contraseña -->
        <div class="border-t border-b border-gray-100 bg-gray-50/50 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-700">Contraseña</h2>
        </div>

        <div class="grid gap-5 p-5 sm:grid-cols-2">
          <!-- Nueva contraseña -->
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Contraseña <span class="text-red-400">*</span></label>
            <input
              v-model="form.password"
              type="password"
              placeholder="Mínimo 8 caracteres"
              autocomplete="new-password"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': form.errors.password }"
            />
            <p v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</p>
          </div>

          <!-- Confirmar -->
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Confirmar contraseña <span class="text-red-400">*</span></label>
            <input
              v-model="form.password_confirmation"
              type="password"
              placeholder="Repite la contraseña"
              autocomplete="new-password"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': form.errors.password_confirmation }"
            />
            <p v-if="form.errors.password_confirmation" class="text-xs text-red-500">
              {{ form.errors.password_confirmation }}
            </p>
          </div>
        </div>
      </div>

      <!-- Botones -->
      <div class="flex items-center justify-end gap-3">
        <Link
          :href="route('users.index')"
          class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700
                 hover:bg-gray-50 transition-colors"
        >
          Cancelar
        </Link>
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold
                 text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed
                 transition-colors"
        >
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ form.processing ? 'Guardando…' : 'Crear usuario' }}
        </button>
      </div>
    </form>
  </div>
</template>
