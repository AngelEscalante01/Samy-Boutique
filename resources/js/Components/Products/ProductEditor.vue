<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import BarcodePreview from './BarcodePreview.vue'
import { savedPhotoScopes, togglePhotoScope, photoAvailable } from './photo-scopes'

const props = defineProps({
  product: { type: Object, default: null },
  categories: { type: Array, default: () => [] }, sizes: { type: Array, default: () => [] }, colors: { type: Array, default: () => [] },
  occasions: { type: Array, default: () => [] }, photoVariants: { type: Array, default: () => [] }, barcodes: { type: Array, default: () => [] }, can: { type: Object, default: () => ({}) },
})
const editing = !!props.product
let keyCounter = 0
const newKey = () => `n_${globalThis.crypto?.randomUUID?.() ?? `${Date.now().toString(36)}_${keyCounter++}`}`
function variantRow(variant = {}) {
  return { client_key: variant.id ? `v_${variant.id}` : newKey(), id: variant.id ?? null, sku: variant.sku ?? '', size_id: variant.size?.id ?? '', color_id: variant.color?.id ?? '', stock: variant.stock ?? 1, ...(variant.id ? { expected_stock: variant.stock } : {}),
    sale_price: variant.sale_price ?? '', ...(props.can.viewPurchasePrice ? { purchase_price: variant.purchase_price ?? '' } : {}) }
}
const form = useForm({
  name: props.product?.name ?? '', description: props.product?.description ?? '', category_id: props.product?.category?.id ?? '', gender: props.product?.gender ?? 'unisex', sale_price_base: props.product?.sale_price_base ?? '',
  status: props.product?.status ?? 'disponible', occasion_ids: (props.product?.occasions ?? []).map(item => item.id),
  variants: editing ? (props.product.variants ?? []).filter(variant => variant.active !== false).map(variantRow) : [variantRow()],
  ...(editing ? { catalog_published: !!props.product.catalog_published, expected_updated_at: props.product.updated_at, sold_at: props.product.sold_at } : {}),
})
const existingPhotos = ref((props.product?.images ?? []).map(image => ({ ...image, uid: `saved_${image.id}`, scopes: savedPhotoScopes(image), preview: image.thumbnail_url || image.url || image.image_url })))
const newPhotos = ref([])
const localError = ref('')
const gallery = computed(() => [...existingPhotos.value, ...newPhotos.value])
const totalStock = computed(() => form.variants.reduce((total, variant) => total + Number(variant.stock || 0), 0))
const variantName = variant => `${props.colors.find(color => Number(color.id) === Number(variant.color_id))?.name ?? 'Elige color'} / ${props.sizes.find(size => Number(size.id) === Number(variant.size_id))?.name ?? 'Elige talla'}`
const photoOptions = computed(() => [
  ...form.variants.map(variant => ({ key: variant.client_key, name: variantName(variant) })),
  ...props.photoVariants.filter(variant => !form.variants.some(row => Number(row.id) === Number(variant.id))).map(variant => ({ key: `v_${variant.id}`, name: `${variant.color?.name ?? 'Color'} / ${variant.size?.name ?? 'Talla'} · archivada` })),
])
const assignedPhotos = variant => gallery.value.filter(photo => photo.scopes.includes(variant.client_key))
const codeFor = variant => props.barcodes.filter(code => code.sku === variant.sku)
const errors = computed(() => [...new Set(Object.values(form.errors))])

function addFiles(event, scope = 'all') {
  const files = Array.from(event.target.files ?? [])
  event.target.value = ''
  localError.value = ''
  if (newPhotos.value.length + files.length > 10) { localError.value = 'Puedes agregar hasta 10 fotos nuevas por guardado.'; return }
  if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 4 * 1024 * 1024)) { localError.value = 'Usa fotos JPG, PNG o WEBP de hasta 4 MB cada una.'; return }
  newPhotos.value.push(...files.map(file => ({ uid: newKey(), file, preview: URL.createObjectURL(file), scopes: [scope] })))
}
function removeNew(photo) {
  localError.value = ''
  URL.revokeObjectURL(photo.preview)
  newPhotos.value = newPhotos.value.filter(item => item.uid !== photo.uid)
}
function removeVariant(index) {
  localError.value = ''
  const variant = form.variants[index]
  if (!variant.id && gallery.value.some(photo => photo.scopes.includes(variant.client_key) && photo.scopes.length === 1)) {
    localError.value = 'Reasigna o retira las fotos de esta variante nueva antes de quitarla.'; return
  }
  if (!variant.id) for (const photo of gallery.value) photo.scopes = photo.scopes.filter(key => key !== variant.client_key)
  form.variants.splice(index, 1)
}
function movePhoto(photo, direction) {
  const list = photo.id ? existingPhotos.value : newPhotos.value
  const index = list.findIndex(item => item.uid === photo.uid), next = index + direction
  if (next < 0 || next >= list.length) return
  ;[list[index], list[next]] = [list[next], list[index]]
}
function deletePhoto(photo) {
  if (!confirm('¿Eliminar esta foto? Los cambios del formulario que no hayas guardado se descartarán.')) return
  router.delete(route('products.images.destroy', { product: props.product.id, productImage: photo.id }), { preserveScroll: true })
}
function submit() {
  localError.value = ''
  if (!form.variants.length) { localError.value = 'Agrega al menos una variante.'; return }
  if (new Set(form.variants.map(variant => `${variant.size_id}:${variant.color_id}`)).size !== form.variants.length) { localError.value = 'Cada combinación de talla y color debe ser distinta.'; return }
  if (gallery.value.some(photo => !photo.scopes.length)) { localError.value = 'Indica si cada foto es general o de una o varias variantes.'; return }
  form.transform(data => ({
    ...data,
    variants: data.variants.map(({ sku, ...variant }) => ({ ...variant, size_id: Number(variant.size_id), color_id: Number(variant.color_id), stock: Number(variant.stock) })),
    images: newPhotos.value.map(photo => photo.file), image_variant_keys: newPhotos.value.map(photo => photo.scopes),
    ...(editing && existingPhotos.value.length ? { existing_image_variant_keys: Object.fromEntries(existingPhotos.value.map(photo => [photo.id, photo.scopes])), image_order: existingPhotos.value.map(photo => photo.id) } : {}),
    ...(editing ? { _method: 'put' } : {}),
  })).post(editing ? route('products.update', props.product.id) : route('products.store'), { forceFormData: true, preserveScroll: true })
}
onBeforeUnmount(() => newPhotos.value.forEach(photo => URL.revokeObjectURL(photo.preview)))
</script>

<template>
  <form class="product-editor" @submit.prevent="submit">
    <p v-if="localError" role="alert" class="sticky top-12 z-20 border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ localError }}</p>
    <fieldset :disabled="form.processing" class="min-w-0">
      <section class="editor-section" aria-labelledby="product-data-title">
        <div class="section-heading"><div><h2 id="product-data-title">1. Datos del producto</h2><p>Información compartida por todas las variantes.</p></div><span v-if="product" class="font-mono text-xs text-stone-500">{{ product.sku }}</span></div>
        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
          <label class="field sm:col-span-2"><span>Nombre del producto</span><input v-model="form.name" required maxlength="180" autocomplete="off" placeholder="Ej. Blusa de lino" /><small>{{ form.errors.name }}</small></label>
          <label class="field"><span>Categoría</span><select v-model="form.category_id" required><option value="" disabled>Selecciona...</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><small>{{ form.errors.category_id }}</small></label>
          <label class="field"><span>Género</span><select v-model="form.gender"><option value="dama">Dama</option><option value="caballero">Caballero</option><option value="unisex">Unisex</option></select></label>
          <label v-if="editing" class="field"><span>Estado</span><select v-model="form.status"><option value="disponible">Disponible</option><option value="apartado">Apartado</option><option value="vendido">Vendido</option><option value="cancelado">Cancelado</option></select></label>
          <label class="field"><span>Precio base (opcional)</span><input v-model="form.sale_price_base" type="number" min="0" step="0.01" placeholder="0.00" /><span class="hint">Se usa cuando la variante no tiene otro precio.</span><small>{{ form.errors.sale_price_base }}</small></label>
          <label class="field sm:col-span-2 xl:col-span-3"><span>Descripción</span><textarea v-model="form.description" rows="2" placeholder="Detalles de la prenda" /></label>
          <div v-if="occasions.length" class="sm:col-span-2 xl:col-span-3"><p class="mb-2 text-xs font-semibold text-stone-700">Ocasiones o estilos</p><div class="flex flex-wrap gap-3"><label v-for="occasion in occasions" :key="occasion.id" class="inline-flex items-center gap-2 text-sm"><input v-model="form.occasion_ids" type="checkbox" :value="occasion.id" />{{ occasion.name }}</label></div></div>
        </div>
      </section>

      <section class="editor-section" aria-labelledby="product-variants-title">
        <div class="section-heading"><div><h2 id="product-variants-title">2. Variantes</h2><p>Cada combinación de color y talla tiene sus existencias, precio y fotos.</p></div><span class="text-xs font-semibold text-stone-600">{{ form.variants.length }} {{ form.variants.length === 1 ? 'variante' : 'variantes' }} · {{ totalStock }} {{ totalStock === 1 ? 'unidad' : 'unidades' }}</span></div>
        <div class="grid grid-cols-1 gap-3 bg-stone-50 p-3 xl:grid-cols-2">
          <article v-for="(variant, index) in form.variants" :key="variant.client_key" class="variant-card min-w-0 rounded-xl border border-stone-200 bg-white" :aria-label="`Variante ${index + 1}`">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-4 py-3"><div><h3 class="text-sm font-bold text-stone-900">{{ variantName(variant) }}</h3><p class="mt-0.5 font-mono text-xs text-stone-500">{{ variant.sku || 'Código al guardar' }}</p></div><button type="button" class="text-xs font-semibold text-rose-700" @click="removeVariant(index)">Quitar variante</button></header>
            <div class="grid grid-cols-2 gap-3 p-4">
              <label class="field"><span>Color</span><select v-model="variant.color_id" required><option value="" disabled>Selecciona...</option><option v-for="color in colors" :key="color.id" :value="color.id">{{ color.name }}</option></select><small>{{ form.errors[`variants.${index}.color_id`] }}</small></label>
              <label class="field"><span>Talla</span><select v-model="variant.size_id" required><option value="" disabled>Selecciona...</option><option v-for="size in sizes" :key="size.id" :value="size.id">{{ size.name }}</option></select><small>{{ form.errors[`variants.${index}.size_id`] }}</small></label>
              <label class="field"><span>Existencias</span><input v-model="variant.stock" type="number" required :min="editing ? 0 : 1" step="1" /><small>{{ form.errors[`variants.${index}.stock`] }}</small></label>
              <label class="field"><span>Precio de venta</span><input v-model="variant.sale_price" type="number" min="0" step="0.01" :placeholder="form.sale_price_base ? String(form.sale_price_base) : 'Precio base'" /><small>{{ form.errors[`variants.${index}.sale_price`] }}</small></label>
              <label v-if="can.viewPurchasePrice" class="field"><span>Precio de compra (opcional)</span><input v-model="variant.purchase_price" type="number" min="0" step="0.01" /><small>{{ form.errors[`variants.${index}.purchase_price`] }}</small></label>
            </div>
            <div class="border-t border-stone-100 p-4">
              <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-xs font-semibold text-stone-700">Fotos de esta variante ({{ assignedPhotos(variant).length }})</p><label class="picker text-xs">Agregar fotos<input type="file" multiple accept="image/jpeg,image/png,image/webp" :aria-label="`Agregar fotos a variante ${index + 1}`" @change="event => addFiles(event, variant.client_key)" /></label></div>
              <div v-if="assignedPhotos(variant).length" class="mt-3 flex gap-2 overflow-x-auto"><img v-for="photo in assignedPhotos(variant)" :key="photo.uid" :src="photo.preview" alt="Foto asignada" class="h-16 w-16 shrink-0 rounded-lg border border-stone-200 object-cover" /></div>
              <p v-else class="mt-2 text-xs text-stone-500">Puedes agregar fotos aquí o compartir una desde la galería.</p>
              <BarcodePreview v-if="codeFor(variant).length" :codes="codeFor(variant)" compact class="mt-3" />
            </div>
          </article>
          <button type="button" class="flex min-h-24 items-center justify-center gap-2 rounded-xl border-2 border-dashed border-stone-300 bg-white p-4 text-sm font-semibold text-stone-700 hover:border-stone-500" @click="form.variants.push(variantRow())"><span class="text-xl">+</span> Agregar otra variante</button>
        </div>
      </section>

      <section class="editor-section" aria-labelledby="product-photos-title">
        <div class="section-heading"><div><h2 id="product-photos-title">3. Galería del catálogo</h2><p>Todas las fotos disponibles aparecen en la galería. Elige qué variantes muestra cada una.</p></div><label class="picker">Agregar fotos generales<input type="file" multiple accept="image/jpeg,image/png,image/webp" aria-label="Agregar fotos generales" @change="addFiles" /></label></div>
        <div class="border-b border-stone-100 bg-amber-50/50 px-4 py-3 text-xs leading-relaxed text-stone-600">Una foto asignada a varias variantes se muestra mientras alguna tenga existencias. Cuando se agotan todas, se oculta del catálogo sin borrarse. Las fotos generales permanecen mientras el producto tenga alguna variante disponible.</div>
        <div v-if="gallery.length" class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 xl:grid-cols-3">
          <article v-for="(photo, index) in gallery" :key="photo.uid" class="min-w-0 overflow-hidden rounded-xl border border-stone-200 bg-white" :aria-label="`Foto ${index + 1}`">
            <div class="relative"><img :src="photo.preview || '/images/product-placeholder.svg'" :alt="`Foto ${index + 1} del producto`" class="aspect-[4/3] w-full object-cover" @error="event => { event.target.onerror = null; event.target.src = '/images/product-placeholder.svg' }" /><span class="absolute left-2 top-2 rounded-full bg-white/95 px-2 py-1 text-[10px] font-semibold text-stone-800">{{ index === 0 ? 'Portada' : `Foto ${index + 1}` }}{{ photo.id ? '' : ' · Nueva' }}</span></div>
            <div class="p-3">
              <fieldset><legend class="mb-2 text-xs font-bold text-stone-700">Esta foto muestra</legend>
                <label class="scope-option"><input type="checkbox" :checked="photo.scopes.includes('all')" @change="photo.scopes = togglePhotoScope(photo.scopes, 'all', $event.target.checked)" />General · cualquier variante</label>
                <label v-for="option in photoOptions" :key="option.key" class="scope-option"><input type="checkbox" :checked="photo.scopes.includes(option.key)" @change="photo.scopes = togglePhotoScope(photo.scopes, option.key, $event.target.checked)" />{{ option.name }}</label>
              </fieldset>
              <p class="mt-3 rounded-lg px-2 py-2 text-xs" :class="photoAvailable(photo.scopes, form.variants) ? 'bg-emerald-50 text-emerald-800' : 'bg-stone-100 text-stone-600'">{{ !photo.scopes.length ? 'Elige una asignación para esta foto.' : photoAvailable(photo.scopes, form.variants) ? 'Con estas existencias se verá en el catálogo.' : 'Se conservará, pero quedará oculta en el catálogo.' }}</p>
              <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-3"><div class="flex gap-2"><button type="button" class="photo-action" :aria-label="`Mover foto ${index + 1} antes`" @click="movePhoto(photo, -1)">←</button><button type="button" class="photo-action" :aria-label="`Mover foto ${index + 1} después`" @click="movePhoto(photo, 1)">→</button></div><button v-if="!photo.id" type="button" class="text-xs font-semibold text-rose-700" @click="removeNew(photo)">Quitar foto nueva</button><button v-else-if="can.deleteImages" type="button" class="text-xs font-semibold text-rose-700" @click="deletePhoto(photo)">Eliminar foto</button></div>
            </div>
          </article>
        </div>
        <p v-else class="p-6 text-center text-sm text-stone-500">Agrega fotos generales o desde una variante. JPG, PNG o WEBP de hasta 4 MB.</p>
        <div class="border-t border-stone-100 p-4"><label v-if="editing" class="inline-flex items-center gap-2 text-sm font-semibold text-stone-700"><input v-model="form.catalog_published" type="checkbox" />Publicar en catálogo</label><p v-else class="text-xs text-stone-500">Al guardar con fotos, el producto se publicará en el catálogo.</p></div>
      </section>
    </fieldset>
    <div class="border border-stone-200 bg-white p-4">
      <p v-for="error in errors" :key="error" role="alert" class="mb-2 text-sm text-rose-700">{{ error }}</p>
      <div class="flex flex-wrap justify-end gap-2"><Link :href="route('products.index')" class="rounded-lg border border-stone-300 px-4 py-2.5 text-sm font-semibold">Cancelar</Link><button type="submit" :disabled="form.processing" class="rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Guardando...' : editing ? 'Guardar cambios' : 'Guardar producto' }}</button></div>
    </div>
  </form>
</template>
