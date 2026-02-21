/**
 * Samy Boutique – Premium Pages Generator
 * node _gen/premium_pages.mjs
 *
 * Genera versiones premium de:
 *   Pages/Products/Create.vue  (con UI/Card, UI/Input, UI/Select, UI/Button, UI/Badge)
 */
import { writeFileSync, mkdirSync } from 'fs'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const base = resolve(__dirname, '..', 'resources', 'js')

function write(rel, content) {
  const abs = resolve(base, rel)
  mkdirSync(dirname(abs), { recursive: true })
  writeFileSync(abs, content, 'utf-8')
  console.log('  OK', rel)
}

/* ═══════════════════════════════════════════════════════════════════════════
   Pages/Products/Create.vue
   ═══════════════════════════════════════════════════════════════════════════ */
const PRODUCTS_CREATE = `<script setup>
import ImageUploader from '@/Components/Forms/ImageUploader.vue'
import UIButton from '@/Components/UI/Button.vue'
import UICard   from '@/Components/UI/Card.vue'
import UIInput  from '@/Components/UI/Input.vue'
import UISelect from '@/Components/UI/Select.vue'
import UIBadge  from '@/Components/UI/Badge.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  categories: { type: Array, required: true },
  sizes:      { type: Array, required: true },
  colors:     { type: Array, required: true },
  can: {
    type: Object,
    default: () => ({ viewPurchasePrice: false }),
  },
})

const uploaderRef = ref(null)

const form = useForm({
  sku:            '',
  name:           '',
  description:    '',
  category_id:    '',
  gender:         'unisex',
  size_id:        '',
  color_id:       '',
  purchase_price: '',
  sale_price:     '',
  status:         'disponible',
  images:         [],
})

function onImagesChange(files) { form.images = files }

const profit = computed(() => {
  const sp = Number(form.sale_price     || 0)
  const pp = Number(form.purchase_price || 0)
  if (!sp || !pp) return null
  const g = sp - pp
  return { amount: g, pct: Math.round((g / pp) * 100) }
})

function submit() {
  form.post(route('products.store'), { forceFormData: true })
}

function money(n) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n ?? 0)
}

const STATUS_OPTS = [
  { v: 'disponible', label: 'Disponible', variant: 'success' },
  { v: 'apartado',   label: 'Apartado',   variant: 'warning' },
  { v: 'vendido',    label: 'Vendido',    variant: 'neutral' },
]
<\/script>

<template>
  <Head title="Nuevo producto" />

  <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
  <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
      <nav class="mb-1.5 flex items-center gap-1.5 text-xs text-stone-400">
        <Link :href="route('products.index')" class="hover:text-stone-600 transition-colors">Productos</Link>
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-stone-600 font-medium">Nuevo producto</span>
      </nav>
      <h1 class="text-xl font-semibold tracking-wide text-stone-900">Nuevo producto</h1>
      <p class="mt-0.5 text-sm text-stone-400">Completa los datos del artículo</p>
    </div>
    <Link :href="route('products.index')">
      <UIButton variant="secondary" size="sm">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Cancelar
      </UIButton>
    </Link>
  </div>

  <form @submit.prevent="submit">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

      <!-- ── Columna principal ────────────────────────────────────────── -->
      <div class="lg:col-span-2 space-y-5">

        <!-- Identificación -->
        <UICard>
          <template #header>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Identificación</h2>
          </template>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <UIInput
              v-model="form.sku"
              label="SKU"
              placeholder="Ej. CAM-001"
              :error="form.errors.sku"
            />
            <UIInput
              v-model="form.name"
              label="Nombre"
              placeholder="Ej. Blusa roja floral"
              required
              :error="form.errors.name"
            />
            <div class="sm:col-span-2 flex flex-col gap-1">
              <label class="text-xs font-semibold text-stone-700">Descripción</label>
              <textarea
                v-model="form.description"
                rows="3"
                placeholder="Detalles adicionales (opcional)..."
                class="block w-full rounded-xl border border-stone-300 py-2 px-3 text-sm text-stone-800
                       bg-white placeholder:text-stone-400 transition duration-200
                       focus:outline-none focus:ring-2 focus:ring-amber-300/60 focus:border-amber-400"
              />
              <p v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</p>
            </div>
          </div>
        </UICard>

        <!-- Clasificación -->
        <UICard>
          <template #header>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Clasificación</h2>
          </template>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <UISelect
              v-model="form.category_id"
              label="Categoría"
              required
              :error="form.errors.category_id"
            >
              <option value="" disabled>Selecciona...</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
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

            <UISelect
              v-model="form.size_id"
              label="Talla"
              required
              :error="form.errors.size_id"
            >
              <option value="" disabled>Selecciona...</option>
              <option v-for="s in sizes" :key="s.id" :value="s.id">{{ s.name }}</option>
            </UISelect>

            <UISelect
              v-model="form.color_id"
              label="Color"
              required
              :error="form.errors.color_id"
            >
              <option value="" disabled>Selecciona...</option>
              <option v-for="c in colors" :key="c.id" :value="c.id">{{ c.name }}</option>
            </UISelect>
          </div>
        </UICard>

        <!-- Precios -->
        <UICard>
          <template #header>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Precios</h2>
          </template>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            <div v-if="can.viewPurchasePrice" class="flex flex-col gap-1">
              <label class="text-xs font-semibold text-stone-700">
                Precio compra <span class="text-red-400 ml-0.5">*</span>
              </label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-stone-400">$</span>
                <input
                  v-model="form.purchase_price"
                  type="number" step="0.01" min="0"
                  placeholder="0.00"
                  class="block w-full rounded-xl border border-stone-300 py-2 pl-7 pr-3 text-sm text-stone-800
                         bg-white placeholder:text-stone-400 transition duration-200
                         focus:outline-none focus:ring-2 focus:ring-amber-300/60 focus:border-amber-400"
                  :class="form.errors.purchase_price ? 'border-red-400' : ''"
                />
              </div>
              <p v-if="form.errors.purchase_price" class="text-xs text-red-500">{{ form.errors.purchase_price }}</p>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-xs font-semibold text-stone-700">
                Precio venta <span class="text-red-400 ml-0.5">*</span>
              </label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-stone-400">$</span>
                <input
                  v-model="form.sale_price"
                  type="number" step="0.01" min="0"
                  placeholder="0.00"
                  class="block w-full rounded-xl border border-stone-300 py-2 pl-7 pr-3 text-sm text-stone-800
                         bg-white placeholder:text-stone-400 transition duration-200
                         focus:outline-none focus:ring-2 focus:ring-amber-300/60 focus:border-amber-400"
                  :class="form.errors.sale_price ? 'border-red-400' : ''"
                />
              </div>
              <p v-if="form.errors.sale_price" class="text-xs text-red-500">{{ form.errors.sale_price }}</p>
            </div>

            <!-- Ganancia estimada -->
            <div v-if="can.viewPurchasePrice && profit" class="sm:col-span-2">
              <div class="flex items-center gap-4 rounded-xl border border-stone-200 bg-stone-50 px-4 py-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl"
                     :class="profit.amount >= 0 ? 'bg-emerald-100' : 'bg-red-100'">
                  <svg class="h-4 w-4" :class="profit.amount >= 0 ? 'text-emerald-600' : 'text-red-600'"
                       fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      :d="profit.amount >= 0
                        ? 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941'
                        : 'M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181'" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-stone-500">Ganancia estimada</p>
                  <p class="text-lg font-semibold" :class="profit.amount >= 0 ? 'text-emerald-600' : 'text-red-600'">
                    {{ money(profit.amount) }}
                    <span class="text-sm font-medium">· {{ profit.pct }}%</span>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </UICard>

      </div>

      <!-- ── Columna lateral ──────────────────────────────────────────── -->
      <div class="space-y-5">

        <!-- Estado -->
        <UICard>
          <template #header>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Estado</h2>
          </template>
          <div class="space-y-2">
            <button
              v-for="s in STATUS_OPTS"
              :key="s.v"
              type="button"
              class="flex w-full items-center justify-between rounded-xl border px-3.5 py-2.5 text-sm font-medium transition-all duration-200"
              :class="form.status === s.v
                ? 'border-zinc-800 bg-zinc-900 text-white shadow-sm'
                : 'border-stone-200 bg-white text-stone-600 hover:bg-stone-50'"
              @click="form.status = s.v"
            >
              <span>{{ s.label }}</span>
              <UIBadge v-if="form.status === s.v" :variant="s.variant" size="sm">Activo</UIBadge>
              <span v-else class="h-4 w-4 rounded-full border-2 border-stone-300" />
            </button>
            <p v-if="form.errors.status" class="text-xs text-red-500">{{ form.errors.status }}</p>
          </div>
        </UICard>

        <!-- Fotos -->
        <UICard>
          <template #header>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-stone-400">Fotos</h2>
          </template>
          <ImageUploader
            ref="uploaderRef"
            :max="10"
            :error="form.errors.images || form.errors['images.0']"
            @change="onImagesChange"
          />
        </UICard>

        <!-- Acciones -->
        <div class="flex flex-col gap-2.5">
          <UIButton
            type="submit"
            variant="primary"
            size="lg"
            :loading="form.processing"
            class="w-full"
          >
            <svg v-if="!form.processing" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ form.processing ? 'Guardando…' : 'Guardar producto' }}
          </UIButton>

          <Link :href="route('products.index')" class="block">
            <UIButton variant="secondary" size="lg" class="w-full" type="button">Cancelar</UIButton>
          </Link>
        </div>
      </div>
    </div>
  </form>
</template>
`

/* ── Write ───────────────────────────────────────────────────────────────── */
console.log('\n▸ Páginas')
write('Pages/Products/Create.vue', PRODUCTS_CREATE)
console.log('\nDone ✓')
