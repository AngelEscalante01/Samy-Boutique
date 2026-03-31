<script setup>
import { computed, ref } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
  items:       { type: Object,  required: true },      // paginación Inertia
  updateRoute: { type: String,  required: true },
  toggleRoute: { type: String,  required: true },
  deleteRoute: { type: String,  required: true },
  showHex:     { type: Boolean, default: false },
  recordLabel: { type: String, default: 'registros' },
})

const pagination = computed(() => {
  const links = props.items?.links ?? []
  if (links.length < 3) {
    return { prev: null, pages: [], next: null }
  }

  return {
    prev: links[0],
    pages: links.slice(1, -1),
    next: links[links.length - 1],
  }
})

function pageLabel(label) {
  return String(label ?? '').replace(/<[^>]*>/g, '').trim()
}

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
  <div class="hidden overflow-x-auto md:block">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50/80">
        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <th class="w-12 px-3 py-2.5">ID</th>
          <th class="px-3 py-2.5">Nombre</th>
          <th class="w-28 px-3 py-2.5 text-center">Estado</th>
          <th class="w-40 px-3 py-2.5 text-right">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        <tr v-if="!items.data?.length">
          <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">
            Sin registros. Agrega el primero arriba.
          </td>
        </tr>

        <tr
          v-for="item in items.data"
          :key="item.id"
          :class="['transition-colors duration-150', editingId === item.id ? 'bg-sky-50/40' : 'hover:bg-slate-50/80']"
        >
          <td class="px-3 py-2 text-xs font-semibold text-slate-400 tabular-nums">{{ item.id }}</td>

          <td class="px-3 py-2">
            <template v-if="editingId === item.id">
              <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                  <input
                    v-model="editForm.name"
                    type="text"
                    placeholder="Nombre"
                    class="h-8 w-full max-w-xs rounded-lg border border-slate-300 px-2.5 text-sm shadow-sm
                           focus:border-slate-400 focus:ring-1 focus:ring-slate-400 focus:outline-none"
                    :class="{ 'border-red-400': editForm.errors.name }"
                    @keydown.enter="saveEdit(item)"
                    @keydown.esc="cancelEdit"
                  />
                  <template v-if="showHex">
                    <input
                      v-model="editForm.hex"
                      type="color"
                      class="h-8 w-9 cursor-pointer rounded border border-slate-300 p-0.5"
                      title="Color hex"
                    />
                    <input
                      v-model="editForm.hex"
                      type="text"
                      placeholder="#FFFFFF"
                      maxlength="7"
                      class="h-8 w-24 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm
                             focus:border-slate-400 focus:ring-1 focus:ring-slate-400 focus:outline-none"
                    />
                  </template>
                </div>
                <p v-if="editForm.errors.name" class="text-xs text-red-500">{{ editForm.errors.name }}</p>
              </div>
            </template>
            <template v-else>
              <div class="flex items-center gap-2">
                <span
                  v-if="showHex"
                  class="h-5 w-5 shrink-0 rounded-md border border-slate-200"
                  :style="{ background: item.hex || '#ffffff' }"
                  :title="item.hex || 'Sin HEX'"
                />
                <div class="min-w-0">
                  <p class="truncate font-medium text-slate-800">{{ item.name }}</p>
                  <p v-if="showHex" class="truncate text-[11px] text-slate-400">{{ item.hex || 'Sin HEX' }}</p>
                </div>
              </div>
            </template>
          </td>

          <td class="px-3 py-2 text-center">
            <span
              :class="item.active
                ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
                : 'bg-slate-100 text-slate-500 ring-slate-200'"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
            >
              <span
                :class="item.active ? 'bg-emerald-400' : 'bg-slate-400'"
                class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full"
              />
              {{ item.active ? 'Activo' : 'Inactivo' }}
            </span>
          </td>

          <td class="px-3 py-2">
            <div v-if="editingId === item.id" class="flex justify-end gap-1.5">
              <button
                type="button"
                :disabled="editForm.processing || !editForm.name.trim()"
                class="rounded-md bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="saveEdit(item)"
              >
                Guardar
              </button>
              <button
                type="button"
                class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                @click="cancelEdit"
              >
                Cancelar
              </button>
            </div>

            <div v-else class="flex justify-end gap-1.5">
              <button
                type="button"
                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 transition hover:bg-slate-50"
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
                class="inline-flex h-7 w-7 items-center justify-center rounded-md border transition"
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
                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-rose-200 text-rose-500 transition hover:border-rose-300 hover:bg-rose-50"
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

  <ul class="divide-y divide-slate-100 md:hidden">
    <li v-if="!items.data?.length" class="px-4 py-6 text-center text-sm text-slate-400">
      Sin registros. Agrega el primero arriba.
    </li>

    <li
      v-for="item in items.data"
      :key="item.id"
      :class="['px-4 py-3', editingId === item.id ? 'bg-sky-50/40' : '']"
    >
      <template v-if="editingId === item.id">
        <div class="flex flex-col gap-2">
          <input
            v-model="editForm.name"
            type="text"
            placeholder="Nombre"
            class="h-9 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-slate-400 focus:ring-1 focus:ring-slate-400 focus:outline-none"
          />
          <div v-if="showHex" class="flex items-center gap-2">
            <input v-model="editForm.hex" type="color" class="h-9 w-10 cursor-pointer rounded border border-slate-300 p-0.5" />
            <input v-model="editForm.hex" type="text" placeholder="#FFFFFF" maxlength="7"
              class="h-9 w-28 rounded-lg border border-slate-300 px-3 text-sm focus:border-slate-400 focus:ring-1 focus:ring-slate-400 focus:outline-none" />
          </div>
          <p v-if="editForm.errors.name" class="text-xs text-red-500">{{ editForm.errors.name }}</p>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="editForm.processing || !editForm.name.trim()"
              class="flex-1 rounded-lg bg-slate-900 py-2 text-sm font-semibold text-white disabled:opacity-50"
              @click="saveEdit(item)"
            >Guardar</button>
            <button
              type="button"
              class="flex-1 rounded-lg border border-slate-300 py-2 text-sm font-semibold text-slate-600"
              @click="cancelEdit"
            >Cancelar</button>
          </div>
        </div>
      </template>

      <template v-else>
        <div class="flex items-center gap-3">
          <span
            v-if="showHex"
            class="h-8 w-8 flex-shrink-0 rounded-lg border border-slate-200"
            :style="{ background: item.hex || '#ffffff' }"
          />

          <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-slate-800">{{ item.name }}</p>
            <p class="text-[11px] text-slate-400">ID: {{ item.id }}</p>
            <span
              :class="item.active
                ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
                : 'bg-slate-100 text-slate-500 ring-slate-200'"
              class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
            >{{ item.active ? 'Activo' : 'Inactivo' }}</span>
          </div>

          <div class="flex shrink-0 items-center gap-1.5">
            <button type="button" @click="startEdit(item)"
              class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
              </svg>
            </button>
            <button type="button" @click="toggle(item)"
              :class="item.active ? 'border-amber-200 text-amber-600' : 'border-emerald-200 text-emerald-600'"
              class="inline-flex h-7 w-7 items-center justify-center rounded-md border hover:bg-slate-50">
              <svg v-if="item.active" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </button>
            <button type="button" @click="destroy(item)"
              class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-rose-200 text-rose-500 hover:bg-rose-50">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
              </svg>
            </button>
          </div>
        </div>
      </template>
    </li>
  </ul>

  <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5">
    <p class="text-xs text-slate-500">
      Mostrando {{ items.from ?? 0 }} a {{ items.to ?? 0 }} de {{ items.total ?? 0 }} {{ recordLabel }}
    </p>

    <nav
      v-if="(items.last_page ?? 1) > 1"
      class="flex items-center gap-1"
      aria-label="Paginacion de catalogos"
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
</template>
