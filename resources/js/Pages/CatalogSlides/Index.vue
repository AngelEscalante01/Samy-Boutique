<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineProps({ slides: { type: Array, default: () => [] } })

const flash = computed(() => usePage().props.flash ?? {})
const createForm = useForm({ title: '', subtitle: '', button_text: '', destination: '', sort_order: 0, active: true, desktop_image: null, mobile_image: null })
const editForm = useForm({ title: '', subtitle: '', button_text: '', destination: '', sort_order: 0, active: true, desktop_image: null, mobile_image: null })
const editingId = ref(null)
const createFileKey = ref(0)
const editFileKey = ref(0)

function createSlide() {
  createForm.post(route('catalog-slides.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset()
      createFileKey.value++
    },
  })
}

function startEdit(slide) {
  editForm.clearErrors()
  editForm.title = slide.title ?? ''
  editForm.subtitle = slide.subtitle ?? ''
  editForm.button_text = slide.button_text ?? ''
  editForm.destination = slide.destination ?? ''
  editForm.sort_order = slide.sort_order
  editForm.active = slide.active
  editForm.desktop_image = null
  editForm.mobile_image = null
  editFileKey.value++
  editingId.value = slide.id
}

function cancelEdit() {
  editingId.value = null
  editForm.clearErrors()
  editForm.reset()
  editFileKey.value++
}

function saveEdit(slide) {
  editForm.transform((data) => ({ ...data, _method: 'put' })).post(route('catalog-slides.update', slide.id), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: cancelEdit,
  })
}

function toggle(slide) {
  router.put(route('catalog-slides.update', slide.id), {
    title: slide.title, subtitle: slide.subtitle, button_text: slide.button_text,
    destination: slide.destination, sort_order: slide.sort_order, active: !slide.active,
  }, { preserveScroll: true })
}

function remove(slide) {
  if (!window.confirm('¿Eliminar esta diapositiva y sus imágenes?')) return
  router.delete(route('catalog-slides.destroy', slide.id), {
    preserveScroll: true,
    onSuccess: () => { if (editingId.value === slide.id) cancelEdit() },
  })
}
</script>

<template>
  <Head title="Carrusel del catálogo" />
  <div class="mx-auto max-w-6xl space-y-5 px-4 py-6 app-card-stack app-page">
    <div class="flex flex-wrap items-center justify-between gap-3 app-heading">
      <div>
        <h1 class="text-2xl font-semibold">Carrusel del catálogo</h1>
        <p class="text-sm text-stone-500">Agrega, edita y ordena las imágenes de portada del catálogo público.</p>
        <p class="mt-1 text-xs text-stone-500">Prepara dos versiones del mismo banner: escritorio horizontal 3:1 (1800 × 600 px) y teléfono vertical 4:5 (800 × 1000 px). La versión móvil es opcional; si falta, se recorta la imagen de escritorio.</p>
      </div>
      <div class="flex gap-4 text-sm">
        <Link :href="route('catalogs.categories.index')" class="underline">Volver a catálogos</Link>
        <Link :href="route('public.catalog.index')" class="underline">Ver catálogo</Link>
      </div>
    </div>

    <p v-if="flash.success" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ flash.success }}</p>
    <p v-if="flash.error" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ flash.error }}</p>

    <form class="grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 md:grid-cols-2" @submit.prevent="createSlide">
      <h2 class="text-lg font-semibold md:col-span-2">Nueva diapositiva</h2>
      <label class="text-sm">Título<input v-model="createForm.title" maxlength="180" class="mt-1 block w-full rounded-lg border-stone-300"><span v-if="createForm.errors.title" class="text-xs text-red-600">{{ createForm.errors.title }}</span></label>
      <label class="text-sm">Texto secundario<input v-model="createForm.subtitle" maxlength="255" class="mt-1 block w-full rounded-lg border-stone-300"><span v-if="createForm.errors.subtitle" class="text-xs text-red-600">{{ createForm.errors.subtitle }}</span></label>
      <label class="text-sm">Texto del botón<input v-model="createForm.button_text" maxlength="80" class="mt-1 block w-full rounded-lg border-stone-300"><span v-if="createForm.errors.button_text" class="text-xs text-red-600">{{ createForm.errors.button_text }}</span></label>
      <label class="text-sm">Destino del botón<input v-model="createForm.destination" placeholder="/catalogo o https://..." class="mt-1 block w-full rounded-lg border-stone-300"><span v-if="createForm.errors.destination" class="text-xs text-red-600">{{ createForm.errors.destination }}</span></label>
      <label class="text-sm">Imagen de escritorio · horizontal 3:1 (obligatoria)<input :key="createFileKey + '-desktop'" type="file" required accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-xs" @change="createForm.desktop_image = $event.target.files?.[0] ?? null"><span v-if="createForm.errors.desktop_image" class="text-xs text-red-600">{{ createForm.errors.desktop_image }}</span></label>
      <label class="text-sm">Imagen de móvil · vertical 4:5 (opcional)<input :key="createFileKey + '-mobile'" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-xs" @change="createForm.mobile_image = $event.target.files?.[0] ?? null"><span v-if="createForm.errors.mobile_image" class="text-xs text-red-600">{{ createForm.errors.mobile_image }}</span></label>
      <label class="text-sm">Orden<input v-model.number="createForm.sort_order" type="number" min="0" max="9999" class="mt-1 block w-full rounded-lg border-stone-300"><span v-if="createForm.errors.sort_order" class="text-xs text-red-600">{{ createForm.errors.sort_order }}</span></label>
      <label class="flex items-center gap-2 text-sm"><input v-model="createForm.active" type="checkbox">Activa</label>
      <button :disabled="createForm.processing" type="submit" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 md:col-span-2">Agregar diapositiva</button>
    </form>

    <h2 class="text-lg font-semibold">Diapositivas guardadas</h2>
    <p v-if="!slides.length" class="rounded-xl border border-stone-200 bg-white p-4 text-sm text-stone-500">Todavía no hay diapositivas. Agrega la primera arriba.</p>
    <div class="grid gap-4 md:grid-cols-2 app-card-grid">
      <article v-for="slide in slides" :key="slide.id" class="overflow-hidden rounded-2xl border border-stone-200 bg-white app-surface">
        <div class="grid grid-cols-[minmax(0,1fr)_110px] gap-2 bg-stone-100 p-3">
          <div><p class="mb-1 text-xs font-semibold text-stone-600">Escritorio</p><img :src="slide.desktop_url" :alt="'Vista de escritorio: ' + (slide.title || 'Diapositiva')" class="aspect-[3/1] w-full rounded-md object-cover"></div>
          <div><p class="mb-1 text-xs font-semibold text-stone-600">Móvil</p><img :src="slide.mobile_url" :alt="'Vista móvil: ' + (slide.title || 'Diapositiva')" class="aspect-[4/5] w-full rounded-md object-cover"><p v-if="!slide.has_mobile_image" class="mt-1 text-[10px] leading-tight text-amber-800">Usa y recorta la imagen de escritorio</p></div>
        </div>
        <div class="space-y-3 p-4">
          <div>
            <h3 class="font-semibold">{{ slide.title || 'Sin título' }}</h3>
            <p class="text-sm text-stone-500">{{ slide.subtitle }}</p>
            <p class="text-xs text-stone-500">Orden {{ slide.sort_order }} · {{ slide.active ? 'Activa' : 'Inactiva' }}</p>
          </div>
          <div v-if="editingId !== slide.id" class="flex flex-wrap gap-4 text-sm">
            <button type="button" class="underline" @click="startEdit(slide)">Editar diapositiva</button>
            <button type="button" class="underline" @click="toggle(slide)">{{ slide.active ? 'Desactivar' : 'Activar' }}</button>
            <button type="button" class="text-red-700 underline" @click="remove(slide)">Eliminar</button>
          </div>
          <form v-else class="grid gap-3 border-t border-stone-100 pt-3 sm:grid-cols-2" @submit.prevent="saveEdit(slide)">
            <h4 class="font-semibold sm:col-span-2">Editar diapositiva</h4>
            <label class="text-xs">Título<input v-model="editForm.title" maxlength="180" class="mt-1 block w-full rounded-lg border-stone-300 text-sm"><span v-if="editForm.errors.title" class="text-red-600">{{ editForm.errors.title }}</span></label>
            <label class="text-xs">Texto secundario<input v-model="editForm.subtitle" maxlength="255" class="mt-1 block w-full rounded-lg border-stone-300 text-sm"><span v-if="editForm.errors.subtitle" class="text-red-600">{{ editForm.errors.subtitle }}</span></label>
            <label class="text-xs">Texto del botón<input v-model="editForm.button_text" maxlength="80" class="mt-1 block w-full rounded-lg border-stone-300 text-sm"><span v-if="editForm.errors.button_text" class="text-red-600">{{ editForm.errors.button_text }}</span></label>
            <label class="text-xs">Destino del botón<input v-model="editForm.destination" placeholder="/catalogo o https://..." class="mt-1 block w-full rounded-lg border-stone-300 text-sm"><span v-if="editForm.errors.destination" class="text-red-600">{{ editForm.errors.destination }}</span></label>
            <label class="text-xs">Reemplazar imagen de escritorio · 3:1<input :key="editFileKey + '-desktop'" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full" @change="editForm.desktop_image = $event.target.files?.[0] ?? null"><span v-if="editForm.errors.desktop_image" class="text-red-600">{{ editForm.errors.desktop_image }}</span></label>
            <label class="text-xs">Reemplazar imagen de móvil · 4:5<input :key="editFileKey + '-mobile'" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full" @change="editForm.mobile_image = $event.target.files?.[0] ?? null"><span v-if="editForm.errors.mobile_image" class="text-red-600">{{ editForm.errors.mobile_image }}</span></label>
            <p class="text-xs text-stone-500 sm:col-span-2">Deja los archivos vacíos para conservar las imágenes actuales.</p>
            <label class="text-xs">Orden<input v-model.number="editForm.sort_order" type="number" min="0" max="9999" class="mt-1 block w-full rounded-lg border-stone-300 text-sm"><span v-if="editForm.errors.sort_order" class="text-red-600">{{ editForm.errors.sort_order }}</span></label>
            <label class="flex items-center gap-2 text-xs"><input v-model="editForm.active" type="checkbox">Activa</label>
            <div class="flex gap-2 sm:col-span-2">
              <button :disabled="editForm.processing" type="submit" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar cambios</button>
              <button type="button" class="rounded-lg border border-stone-300 px-4 py-2 text-sm" @click="cancelEdit">Cancelar</button>
            </div>
          </form>
        </div>
      </article>
    </div>
  </div>
</template>
