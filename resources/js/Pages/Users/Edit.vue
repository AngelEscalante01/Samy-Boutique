<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Toggle    from '@/Components/Forms/Toggle.vue'
import RoleBadge from '@/Components/Users/RoleBadge.vue'

const props = defineProps({
  user:  { type: Object, required: true },
  roles: { type: Array,  default: () => [] },
})

const flash = computed(() => usePage().props.flash ?? {})

// ── Form principal ────────────────────────────────────────────────────────────
const form = useForm({
  name:   props.user.name,
  email:  props.user.email,
  role:   props.user.role,
  active: props.user.active,
})

function updateUser() {
  form.put(route('users.update', props.user.id), { preserveScroll: true })
}

// ── Form cambio de contraseña ─────────────────────────────────────────────────
const showPasswordSection = ref(false)
const passForm = useForm({
  new_password:              '',
  new_password_confirmation: '',
})

function updatePassword() {
  passForm.patch(route('users.updatePassword', props.user.id), {
    preserveScroll: true,
    onSuccess: () => {
      passForm.reset()
      showPasswordSection.value = false
    },
  })
}
</script>

<template>
  <Head :title="`Editar · ${user.name}`" />

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
      <span class="font-medium text-gray-700">Editar</span>
    </nav>

    <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
    <div class="flex items-center gap-4">
      <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-600">
        {{ user.name.charAt(0).toUpperCase() }}
      </span>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ user.name }}</h1>
        <div class="mt-0.5 flex items-center gap-2">
          <span class="text-sm text-gray-500">{{ user.email }}</span>
          <RoleBadge :role="user.role" />
        </div>
      </div>
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

    <!-- ── Form principal ─────────────────────────────────────────────────── -->
    <form @submit.prevent="updateUser" class="space-y-5">
      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
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
            <label class="text-sm font-medium text-gray-700">Estado</label>
            <div class="flex items-center gap-3 pt-1">
              <Toggle v-model="form.active" />
              <span class="text-sm" :class="form.active ? 'text-emerald-600' : 'text-gray-400'">
                {{ form.active ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3">
        <Link :href="route('users.index')"
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Cancelar
        </Link>
        <button
          type="submit"
          :disabled="form.processing || !form.isDirty"
          class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold
                 text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed
                 transition-colors"
        >
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
        </button>
      </div>
    </form>

    <!-- ── Sección cambio de contraseña ──────────────────────────────────── -->
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
      <button
        type="button"
        class="flex w-full items-center justify-between px-5 py-3.5 border-b border-gray-100
               hover:bg-gray-50/60 transition-colors text-left"
        @click="showPasswordSection = !showPasswordSection"
      >
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25
                 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
          </svg>
          <span class="text-sm font-semibold text-gray-700">Cambiar contraseña</span>
        </div>
        <svg
          :class="showPasswordSection ? 'rotate-180' : ''"
          class="h-4 w-4 text-gray-400 transition-transform"
          fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
      </button>

      <div v-if="showPasswordSection">
        <form @submit.prevent="updatePassword" class="grid gap-5 p-5 sm:grid-cols-2">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Nueva contraseña <span class="text-red-400">*</span></label>
            <input
              v-model="passForm.new_password"
              type="password"
              placeholder="Mínimo 8 caracteres"
              autocomplete="new-password"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': passForm.errors.new_password }"
            />
            <p v-if="passForm.errors.new_password" class="text-xs text-red-500">{{ passForm.errors.new_password }}</p>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700">Confirmar contraseña <span class="text-red-400">*</span></label>
            <input
              v-model="passForm.new_password_confirmation"
              type="password"
              placeholder="Repite la contraseña"
              autocomplete="new-password"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm
                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
              :class="{ 'border-red-400': passForm.errors.new_password_confirmation }"
            />
            <p v-if="passForm.errors.new_password_confirmation" class="text-xs text-red-500">
              {{ passForm.errors.new_password_confirmation }}
            </p>
          </div>
          <div class="sm:col-span-2 flex justify-end">
            <button
              type="submit"
              :disabled="passForm.processing || !passForm.new_password"
              class="inline-flex items-center gap-1.5 rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold
                     text-white shadow-sm hover:bg-gray-900 disabled:opacity-50 disabled:cursor-not-allowed
                     transition-colors"
            >
              <svg v-if="passForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ passForm.processing ? 'Guardando…' : 'Actualizar contraseña' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
