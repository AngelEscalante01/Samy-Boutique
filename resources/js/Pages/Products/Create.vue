<script setup>
import ImageUploader from '@/Components/Forms/ImageUploader.vue'
import UIButton from '@/Components/UI/Button.vue'
import UICard from '@/Components/UI/Card.vue'
import UIInput from '@/Components/UI/Input.vue'
import UISelect from '@/Components/UI/Select.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  categories: { type: Array, required: true },
  sizes: { type: Array, required: true },
  colors: { type: Array, required: true },
  can: {
    type: Object,
    default: () => ({ viewPurchasePrice: false }),
  },
})

const form = useForm({
  sku: '',
  name: '',
  description: '',
  category_id: '',
  gender: 'unisex',
  sale_price_base: '',
  status: 'disponible',
  images: [],
  variants: [],
})

const variantDraft = ref({
  size_id: '',
  color_id: '',
  stock: '',
  purchase_price: '',
  sale_price: '',
})

const editingIndex = ref(null)
const variantError = ref('')

const totalStock = computed(() =>
  form.variants.reduce((sum, variant) => sum + Number(variant.stock || 0), 0),
)

function onImagesChange(files) {
  form.images = files
}

function money(value) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value ?? 0)
}

function sizeName(sizeId) {
  return props.sizes.find((size) => Number(size.id) === Number(sizeId))?.name ?? '—'
}

function colorName(colorId) {
  return props.colors.find((color) => Number(color.id) === Number(colorId))?.name ?? '—'
}

function resetVariantDraft() {
  variantDraft.value = {
    size_id: '',
    color_id: '',
    stock: '',
    purchase_price: '',
    sale_price: '',
  }
  editingIndex.value = null
}

function variantPayloadFromDraft() {
  return {
    size_id: Number(variantDraft.value.size_id),
    color_id: Number(variantDraft.value.color_id),
    stock: Number(variantDraft.value.stock),
    purchase_price:
      variantDraft.value.purchase_price === '' || variantDraft.value.purchase_price === null
        ? null
        : Number(variantDraft.value.purchase_price),
    sale_price:
      variantDraft.value.sale_price === '' || variantDraft.value.sale_price === null
        ? null
        : Number(variantDraft.value.sale_price),
  }
}

function canUseCombination(sizeId, colorId, ignoreIndex = null) {
  return !form.variants.some((variant, index) =>
    index !== ignoreIndex
    && Number(variant.size_id) === Number(sizeId)
    && Number(variant.color_id) === Number(colorId),
  )
}

function addOrUpdateVariant() {
  variantError.value = ''

  if (!variantDraft.value.size_id || !variantDraft.value.color_id) {
    variantError.value = 'Selecciona talla y color para la variante.'
    return
  }

  if (!variantDraft.value.stock || Number(variantDraft.value.stock) <= 0) {
    variantError.value = 'El stock debe ser mayor a 0.'
    return
  }

  const nextVariant = variantPayloadFromDraft()

  if (!canUseCombination(nextVariant.size_id, nextVariant.color_id, editingIndex.value)) {
    variantError.value = 'La combinación talla/color ya existe.'
    return
  }

  if (editingIndex.value === null) {
    form.variants.push(nextVariant)
  } else {
    form.variants.splice(editingIndex.value, 1, nextVariant)
  }

  resetVariantDraft()
}

function editVariant(index) {
  const variant = form.variants[index]
  editingIndex.value = index
  variantDraft.value = {
    size_id: String(variant.size_id),
    color_id: String(variant.color_id),
    stock: String(variant.stock),
    purchase_price: variant.purchase_price ?? '',
    sale_price: variant.sale_price ?? '',
  }
  variantError.value = ''
}

function removeVariant(index) {
  form.variants.splice(index, 1)
  if (editingIndex.value === index) {
    resetVariantDraft()
  }
}

function submit() {
  variantError.value = ''

  if (form.variants.length === 0) {
    variantError.value = 'Agrega al menos una variante.'
    return
  }

  form.post(route('products.store'), {
    forceFormData: true,
  })
}
</script>

<template>
  <Head title="Nuevo producto" />

  <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold tracking-wide text-stone-900">Nuevo producto modelo</h1>
      <p class="mt-0.5 text-sm text-stone-400">Registra el modelo y sus variantes en una sola pantalla</p>
    </div>
    <Link :href="route('products.index')">
      <UIButton variant="secondary" size="sm">Cancelar</UIButton>
    </Link>
  </div>

  <form @submit.prevent="submit" class="space-y-6">
    <UICard>
      <template #header>
        <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Datos del producto</h2>
      </template>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <UIInput
          v-model="form.name"
          label="Nombre"
          required
          placeholder="Ej. Vestido corte midi"
          :error="form.errors.name"
        />

        <UIInput
          v-model="form.sku"
          label="SKU modelo (opcional)"
          placeholder="Se autogenera si lo dejas vacío"
          :error="form.errors.sku"
        />

        <div class="sm:col-span-2 flex flex-col gap-1">
          <label class="text-xs font-semibold text-stone-700">Descripción</label>
          <textarea
            v-model="form.description"
            rows="3"
            class="block w-full rounded-xl border border-stone-300 py-2 px-3 text-sm text-stone-800
                   bg-white placeholder:text-stone-400 transition duration-200
                   focus:outline-none focus:ring-2 focus:ring-amber-300/60 focus:border-amber-400"
            placeholder="Descripción del modelo"
          />
          <p v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</p>
        </div>

        <UISelect
          v-model="form.category_id"
          label="Categoría"
          required
          :error="form.errors.category_id"
        >
          <option value="" disabled>Selecciona...</option>
          <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
        </UISelect>

        <UISelect
          v-model="form.gender"
          label="Género"
          required
          :error="form.errors.gender"
        >
          <option value="dama">Dama</option>
          <option value="caballero">Caballero</option>
          <option value="unisex">Unisex</option>
        </UISelect>

      </div>
    </UICard>

    <UICard>
      <template #header>
        <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Imágenes del modelo</h2>
      </template>
      <ImageUploader
        :max="10"
        :error="form.errors.images || form.errors['images.0']"
        @change="onImagesChange"
      />
    </UICard>

    <UICard>
      <template #header>
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Variantes</h2>
          <p class="text-xs font-semibold text-stone-500">Stock total: {{ totalStock }}</p>
        </div>
      </template>

      <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
        <UISelect v-model="variantDraft.color_id" label="Color">
          <option value="" disabled>Selecciona...</option>
          <option v-for="color in colors" :key="color.id" :value="String(color.id)">{{ color.name }}</option>
        </UISelect>

        <UISelect v-model="variantDraft.size_id" label="Talla">
          <option value="" disabled>Selecciona...</option>
          <option v-for="size in sizes" :key="size.id" :value="String(size.id)">{{ size.name }}</option>
        </UISelect>

        <UIInput
          v-model="variantDraft.stock"
          type="number"
          label="Stock"
          placeholder="1"
        />

        <UIInput
          v-if="can.viewPurchasePrice"
          v-model="variantDraft.purchase_price"
          type="number"
          label="Precio compra (opc.)"
          placeholder="0.00"
        />

        <UIInput
          v-model="variantDraft.sale_price"
          type="number"
          label="Precio venta (opc.)"
          placeholder="0.00"
        />
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-2">
        <UIButton type="button" @click="addOrUpdateVariant">
          {{ editingIndex === null ? 'Agregar variante' : 'Actualizar variante' }}
        </UIButton>
        <UIButton v-if="editingIndex !== null" type="button" variant="secondary" @click="resetVariantDraft">
          Cancelar edición
        </UIButton>
      </div>

      <p v-if="variantError" class="mt-2 text-xs text-red-500">{{ variantError }}</p>
      <p v-if="form.errors.variants" class="mt-2 text-xs text-red-500">{{ form.errors.variants }}</p>

      <div class="mt-4 overflow-x-auto rounded-xl border border-stone-200">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
          <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-widest text-stone-500">
            <tr>
              <th class="px-3 py-2">Color</th>
              <th class="px-3 py-2">Talla</th>
              <th class="px-3 py-2">Stock</th>
              <th class="px-3 py-2">Precio venta</th>
              <th class="px-3 py-2">Precio compra</th>
              <th class="px-3 py-2 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100 bg-white">
            <tr v-for="(variant, index) in form.variants" :key="`${variant.color_id}-${variant.size_id}-${index}`">
              <td class="px-3 py-2">{{ colorName(variant.color_id) }}</td>
              <td class="px-3 py-2">{{ sizeName(variant.size_id) }}</td>
              <td class="px-3 py-2 font-semibold text-stone-700">{{ variant.stock }}</td>
              <td class="px-3 py-2">{{ variant.sale_price == null ? '—' : money(variant.sale_price) }}</td>
              <td class="px-3 py-2">{{ variant.purchase_price == null ? '—' : money(variant.purchase_price) }}</td>
              <td class="px-3 py-2">
                <div class="flex justify-end gap-2">
                  <UIButton type="button" size="sm" variant="secondary" @click="editVariant(index)">Editar</UIButton>
                  <UIButton type="button" size="sm" variant="danger" @click="removeVariant(index)">Eliminar</UIButton>
                </div>
              </td>
            </tr>
            <tr v-if="form.variants.length === 0">
              <td colspan="6" class="px-3 py-4 text-center text-stone-400">Sin variantes agregadas</td>
            </tr>
          </tbody>
        </table>
      </div>
    </UICard>

    <div class="flex flex-wrap justify-end gap-2">
      <Link :href="route('products.index')">
        <UIButton type="button" variant="secondary">Cancelar</UIButton>
      </Link>
      <UIButton type="submit" :loading="form.processing">Guardar producto</UIButton>
    </div>
  </form>
</template>
