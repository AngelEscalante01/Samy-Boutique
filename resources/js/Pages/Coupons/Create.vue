<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Toggle from '@/Components/Forms/Toggle.vue'

const form = useForm({
  code:                         '',
  name:                         '',
  active:                       true,
  discount_type:                'percent',
  discount_value:               '',
  min_total:                    '',
  starts_at:                    '',
  ends_at:                      '',
  max_redemptions:              '',
  max_redemptions_per_customer: '',
})

function submit() {
  form.transform(data => ({
    ...data,
    code:                         data.code.toUpperCase().trim(),
    min_total:                    data.min_total               || null,
    starts_at:                    data.starts_at               || null,
    ends_at:                      data.ends_at                 || null,
    max_redemptions:              data.max_redemptions         || null,
    max_redemptions_per_customer: data.max_redemptions_per_customer || null,
  })).post(route('coupons.store'))
}

// ── Validaciones frontend ─────────────────────────────────────────────────────
const warnPercent = computed(() =>
  form.discount_type === 'percent' && Number(form.discount_value) > 100
)
const errorDateRange = computed(() =>
  form.starts_at && form.ends_at && form.ends_at < form.starts_at
)

// Forzar uppercase mientras escribe
watch(() => form.code, (val) => {
  form.code = val.toUpperCase().replace(/s/g, '')
})
</script>

<template>
  <Head title="Nuevo cupón" />

  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Back + title -->
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('coupons.index')" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </Link>
      <h1 class="text-2xl font-bold text-gray-900">Nuevo cupón</h1>
    </div>

    <form @submit.prevent="submit" class="space-y-6">

      <!-- ── Sección A: Datos principales ─────────────────────────────────── -->
      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-5 flex items-center gap-2">
          <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
          </svg>
          Datos principales
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

          <!-- Código -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Código <span class="text-red-500">*</span>
            </label>
            <input v-model="form.code" type="text" maxlength="60"
              placeholder="Ej: FEB14, VERANO20, NUEVO10"
              class="w-full rounded-lg border py-2.5 px-3 text-sm font-mono uppercase tracking-wider placeholder-gray-300 focus:outline-none focus:ring-1"
              :class="form.errors.code
                ? 'border-red-400 focus:border-red-400 focus:ring-red-300'
                : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            <p v-if="form.errors.code" class="mt-1.5 text-xs text-red-600">{{ form.errors.code }}</p>
            <p v-else class="mt-1.5 text-xs text-gray-400">Solo letras, números y guiones. Se guarda en mayúsculas.</p>
          </div>

          <!-- Nombre (opcional) -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Nombre descriptivo <span class="text-xs font-normal text-gray-400">(opcional)</span>
            </label>
            <input v-model="form.name" type="text" maxlength="120"
              placeholder="Ej: Promoción Febrero 2026"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <!-- Tipo descuento -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Tipo de descuento <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
              <label v-for="opt in [{ value: 'percent', label: '% Porcentaje' }, { value: 'amount', label: '$ Monto fijo' }]" :key="opt.value"
                class="flex items-center gap-2.5 rounded-lg border cursor-pointer px-3 py-2.5 transition"
                :class="form.discount_type === opt.value
                  ? 'border-gray-900 bg-gray-900 text-white'
                  : 'border-gray-200 text-gray-700 hover:border-gray-400'">
                <input type="radio" v-model="form.discount_type" :value="opt.value" class="sr-only" />
                <span class="text-sm font-medium">{{ opt.label }}</span>
              </label>
            </div>
          </div>

          <!-- Valor -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Valor <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-gray-400 select-none">
                {{ form.discount_type === 'percent' ? '%' : '$' }}
              </span>
              <input v-model="form.discount_value" type="number" min="0.01" step="0.01"
                :placeholder="form.discount_type === 'percent' ? '10' : '50'"
                class="w-full rounded-lg border py-2.5 pl-7 pr-3 text-sm focus:outline-none focus:ring-1"
                :class="form.errors.discount_value || warnPercent
                  ? 'border-amber-400 focus:border-amber-400 focus:ring-amber-300'
                  : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            </div>
            <p v-if="form.errors.discount_value" class="mt-1.5 text-xs text-red-600">{{ form.errors.discount_value }}</p>
            <p v-else-if="warnPercent" class="mt-1.5 text-xs text-amber-600">
              ⚠ Un descuento mayor a 100% dará precio negativo.
            </p>
          </div>

          <!-- Estado activo -->
          <div class="sm:col-span-2 flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
            <div>
              <p class="text-sm font-medium text-gray-700">Cupón activo</p>
              <p class="text-xs text-gray-400 mt-0.5">Solo los cupones activos pueden aplicarse en el POS</p>
            </div>
            <Toggle v-model="form.active" />
          </div>

        </div>
      </div>

      <!-- ── Sección B: Reglas ──────────────────────────────────────────────── -->
      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-5 flex items-center gap-2">
          <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
          </svg>
          Reglas y vigencia
          <span class="text-xs font-normal text-gray-400">(todos opcionales)</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

          <!-- Vigencia desde -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Válido desde</label>
            <input v-model="form.starts_at" type="date"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <!-- Vigencia hasta -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Válido hasta</label>
            <input v-model="form.ends_at" type="date"
              class="w-full rounded-lg border py-2.5 px-3 text-sm focus:outline-none focus:ring-1"
              :class="errorDateRange
                ? 'border-red-400 focus:border-red-400 focus:ring-red-300'
                : 'border-gray-200 focus:border-gray-400 focus:ring-gray-400'" />
            <p v-if="form.errors.ends_at" class="mt-1.5 text-xs text-red-600">{{ form.errors.ends_at }}</p>
            <p v-else-if="errorDateRange" class="mt-1.5 text-xs text-red-600">La fecha final debe ser posterior a la inicial.</p>
          </div>

          <!-- Mínimo de compra -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Compra mínima</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input v-model="form.min_total" type="number" min="0" step="0.01" placeholder="0.00"
                class="w-full rounded-lg border border-gray-200 py-2.5 pl-7 pr-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
            </div>
          </div>

          <!-- Máx usos total -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Límite de usos total</label>
            <input v-model="form.max_redemptions" type="number" min="1" step="1" placeholder="Sin límite"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

          <!-- Máx usos por cliente -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Límite por cliente</label>
            <input v-model="form.max_redemptions_per_customer" type="number" min="1" step="1" placeholder="Sin límite"
              class="w-full rounded-lg border border-gray-200 py-2.5 px-3 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400" />
          </div>

        </div>
      </div>

      <!-- Botones -->
      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing || !!errorDateRange"
          class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Guardando...' : 'Crear cupón' }}
        </button>
        <Link :href="route('coupons.index')"
          class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
          Cancelar
        </Link>
      </div>

    </form>
  </div>
</template>
