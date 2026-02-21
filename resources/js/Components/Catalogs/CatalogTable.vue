<script setup>
import { ref, reactive } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
  items:       { type: Object,  required: true },      // paginación Inertia
  updateRoute: { type: String,  required: true },
  toggleRoute: { type: String,  required: true },
  deleteRoute: { type: String,  required: true },
  showHex:     { type: Boolean, default: false },
})

// ── Estado inline-edit ────────────────────────────────────────────────────────
const editingId = ref(null)
const editForm  = useForm({ name: '', hex: '' })

function startEdit(item) {
  editingId.value = item.id
  editForm.name   = item.name
  editForm.hex    = item.hex ?? ''
  editForm.clearErrors()
}

function cancelEdit() {
  editingId.value = null
  editForm.reset()
}

function saveEdit(item) {
  editForm
    .transform(d => ({
      name:   d.name,
      hex:    props.showHex ? (d.hex || null) : undefined,
      active: item.active,
    }))
    .put(route(props.updateRoute, item.id), {
      preserveScroll: true,
      onSuccess: () => { editingId.value = null },
    })
}

// ── Toggle ────────────────────────────────────────────────────────────────────
function toggle(item) {
  router.patch(route(props.toggleRoute, item.id), {}, {
    preserveScroll: true,
  })
}

// ── Eliminar ──────────────────────────────────────────────────────────────────
function destroy(item) {
  if (!confirm(`¿Eliminar "${item.name}"? Esta acción no se puede deshacer.`)) return
  router.delete(route(props.deleteRoute, item.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <!-- ── Tabla Desktop ──────────────────────────────────────────────────────── -->
  <div class="hidden sm:block overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
      <thead>
        <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
          <th class="px-4 py-3 w-10">#</th>
          <th v-if="showHex" class="px-4 py-3 w-14">Color</th>
          <th class="px-4 py-3">Nombre</th>
          <th class="px-4 py-3 w-28 text-center">Estado</th>
          <th class="px-4 py-3 w-48 text-right">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50 bg-white">
        <tr v-if="!items.data?.length">
          <td :colspan="showHex ? 5 : 4" class="px-4 py-8 text-center text-gray-400 text-sm">
            Sin registros. Agrega el primero arriba.
          </td>
        </tr>

        <tr
          v-for="item in items.data"
          :key="item.id"
          :class="['transition-colors', editingId === item.id ? 'bg-blue-50/40' : 'hover:bg-gray-50/60']"
        >
          <!-- # -->
          <td class="px-4 py-3 text-gray-400 tabular-nums">{{ item.id }}</td>

          <!-- Swatch -->
          <td v-if="showHex" class="px-4 py-3">
            <span
              v-if="item.hex"
              class="block h-6 w-6 rounded-md ring-1 ring-gray-200 shadow-sm"
              :style="{ background: item.hex }"
              :title="item.hex"
            />
            <span v-else class="block h-6 w-6 rounded-md ring-1 ring-dashed ring-gray-300 bg-gray-50" />
          </td>

          <!-- Nombre (normal / edit) -->
          <td class="px-4 py-3">
            <!-- modo edición -->
            <template v-if="editingId === item.id">
              <div class="flex flex-col gap-1.5">
                <div class="flex items-center gap-2">
                  <input
                    v-model="editForm.name"
                    type="text"
                    placeholder="Nombre"
                    class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-1.5 text-sm shadow-sm
                           focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                    :class="{ 'border-red-400': editForm.errors.name }"
                    @keydown.enter="saveEdit(item)"
                    @keydown.esc="cancelEdit"
                  />
                  <template v-if="showHex">
                    <input
                      v-model="editForm.hex"
                      type="color"
                      class="h-8 w-10 cursor-pointer rounded border border-gray-300 p-0.5"
                      title="Color hex"
                    />
                    <input
                      v-model="editForm.hex"
                      type="text"
                      placeholder="#FFFFFF"
                      maxlength="7"
                      class="w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm
                             focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                    />
                  </template>
                </div>
                <p v-if="editForm.errors.name" class="text-xs text-red-500">{{ editForm.errors.name }}</p>
              </div>
            </template>
            <!-- modo normal -->
            <template v-else>
              <span class="font-medium text-gray-800">{{ item.name }}</span>
            </template>
          </td>

          <!-- Estado -->
          <td class="px-4 py-3 text-center">
            <span
              :class="item.active
                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                : 'bg-gray-100  text-gray-500  ring-1 ring-gray-200'"
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            >
              <span
                :class="item.active ? 'bg-emerald-400' : 'bg-gray-400'"
                class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full"
              />
              {{ item.active ? 'Activo' : 'Inactivo' }}
            </span>
          </td>

          <!-- Acciones -->
          <td class="px-4 py-3">
            <!-- Modo edición: Guardar / Cancelar -->
            <div v-if="editingId === item.id" class="flex justify-end gap-2">
              <button
                type="button"
                :disabled="editForm.processing || !editForm.name.trim()"
                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm
                       hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="saveEdit(item)"
              >
                Guardar
              </button>
              <button
                type="button"
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600
                       hover:bg-gray-100"
                @click="cancelEdit"
              >
                Cancelar
              </button>
            </div>

            <!-- Modo normal: Editar / Toggle / Eliminar -->
            <div v-else class="flex justify-end gap-1.5">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600
                       hover:border-gray-300 hover:bg-gray-50 transition-colors"
                @click="startEdit(item)"
                title="Editar"
              >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                </svg>
              </button>

              <button
                type="button"
                :class="item.active
                  ? 'border-amber-200 text-amber-600 hover:bg-amber-50'
                  : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'"
                class="rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                :title="item.active ? 'Desactivar' : 'Activar'"
                @click="toggle(item)"
              >
                <svg v-if="item.active" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
              </button>

              <button
                type="button"
                class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-500
                       hover:bg-red-50 hover:border-red-300 transition-colors"
                title="Eliminar"
                @click="destroy(item)"
              >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ── Lista móvil ─────────────────────────────────────────────────────────── -->
  <ul class="sm:hidden divide-y divide-gray-100">
    <li v-if="!items.data?.length" class="px-4 py-6 text-center text-sm text-gray-400">
      Sin registros. Agrega el primero arriba.
    </li>

    <li
      v-for="item in items.data"
      :key="item.id"
      :class="['px-4 py-3', editingId === item.id ? 'bg-blue-50/40' : '']"
    >
      <!-- modo edición mobile -->
      <template v-if="editingId === item.id">
        <div class="flex flex-col gap-2">
          <input
            v-model="editForm.name"
            type="text"
            placeholder="Nombre"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500
                   focus:ring-1 focus:ring-blue-500 focus:outline-none"
          />
          <div v-if="showHex" class="flex items-center gap-2">
            <input v-model="editForm.hex" type="color" class="h-9 w-12 cursor-pointer rounded border border-gray-300 p-0.5" />
            <input v-model="editForm.hex" type="text" placeholder="#FFFFFF" maxlength="7"
              class="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500
                     focus:ring-1 focus:ring-blue-500 focus:outline-none" />
          </div>
          <p v-if="editForm.errors.name" class="text-xs text-red-500">{{ editForm.errors.name }}</p>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="editForm.processing || !editForm.name.trim()"
              class="flex-1 rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white disabled:opacity-50"
              @click="saveEdit(item)"
            >Guardar</button>
            <button
              type="button"
              class="flex-1 rounded-lg border border-gray-300 py-2 text-sm font-semibold text-gray-600"
              @click="cancelEdit"
            >Cancelar</button>
          </div>
        </div>
      </template>

      <!-- modo normal mobile -->
      <template v-else>
        <div class="flex items-center gap-3">
          <span
            v-if="showHex && item.hex"
            class="h-8 w-8 flex-shrink-0 rounded-lg ring-1 ring-gray-200 shadow-sm"
            :style="{ background: item.hex }"
          />
          <span v-else-if="showHex" class="h-8 w-8 flex-shrink-0 rounded-lg ring-1 ring-dashed ring-gray-300 bg-gray-50" />

          <div class="min-w-0 flex-1">
            <p class="font-medium text-gray-800 truncate">{{ item.name }}</p>
            <span
              :class="item.active
                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                : 'bg-gray-100  text-gray-500  ring-gray-200'"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 mt-0.5"
            >{{ item.active ? 'Activo' : 'Inactivo' }}</span>
          </div>

          <div class="flex items-center gap-1.5 flex-shrink-0">
            <button type="button" @click="startEdit(item)"
              class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
              </svg>
            </button>
            <button type="button" @click="toggle(item)"
              :class="item.active ? 'border-amber-200 text-amber-600' : 'border-emerald-200 text-emerald-600'"
              class="rounded-lg border p-1.5 hover:bg-gray-50">
              <svg v-if="item.active" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </button>
            <button type="button" @click="destroy(item)"
              class="rounded-lg border border-red-200 p-1.5 text-red-500 hover:bg-red-50">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
              </svg>
            </button>
          </div>
        </div>
      </template>
    </li>
  </ul>
</template>
