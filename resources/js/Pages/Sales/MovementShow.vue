<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import SaleStatusBadge from '@/Components/Sales/SaleStatusBadge.vue'

const props = defineProps({
  movement: { type: Object, required: true },
  back_url: { type: String, required: true },
})

const flashStatus = computed(() => {
  return props.movement.status === 'applied' ? 'applied' : props.movement.status
})

function money(value) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function fmtDate(value) {
  if (!value) return '—'

  return new Date(value).toLocaleString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function paymentMethodLabel(method) {
  if (method === 'cash') return 'Efectivo'
  if (method === 'card') return 'Tarjeta'
  if (method === 'transfer') return 'Transferencia'
  if (method === 'other') return 'Otro'
  return method || 'Sin metodo'
}
</script>

<template>
  <Head :title="`Movimiento ${movement.folio}`" />

  <div class="mx-auto max-w-4xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">
    <section class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
      <div class="flex items-center gap-3">
        <Link :href="back_url" class="text-slate-400 transition hover:text-slate-600">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </Link>

        <div>
          <h1 class="text-2xl font-bold tracking-tight text-slate-900">Movimiento {{ movement.folio }}</h1>
          <p class="mt-0.5 text-sm text-slate-500">Tipo: Abono</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 bg-orange-100 text-orange-700 ring-orange-200">
          Abono
        </span>
        <SaleStatusBadge :status="flashStatus" size="sm" />
      </div>
    </section>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
        <h2 class="text-sm font-semibold text-slate-700">Detalle del abono</h2>

        <dl class="mt-3 space-y-2 text-sm">
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Folio</dt>
            <dd class="font-mono font-semibold text-slate-900">{{ movement.folio }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Fecha y hora</dt>
            <dd class="font-medium text-slate-800">{{ fmtDate(movement.created_at) }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Monto abonado</dt>
            <dd class="font-bold text-cyan-700">{{ money(movement.amount) }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Metodo de pago</dt>
            <dd class="font-medium text-slate-800">{{ paymentMethodLabel(movement.payment_method) }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Usuario que registro</dt>
            <dd class="font-medium text-slate-800">{{ movement.registered_by || 'No disponible' }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Saldo anterior</dt>
            <dd class="font-semibold text-amber-700">{{ money(movement.balance_before) }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Saldo restante</dt>
            <dd class="font-semibold text-emerald-700">{{ money(movement.balance_after) }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Referencia pago</dt>
            <dd class="font-medium text-slate-800">{{ movement.reference || '—' }}</dd>
          </div>
        </dl>
      </article>

      <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-[0_10px_30px_-24px_rgba(15,23,42,1)]">
        <h2 class="text-sm font-semibold text-slate-700">Relacion con apartado</h2>

        <dl class="mt-3 space-y-2 text-sm">
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Apartado</dt>
            <dd class="font-mono font-semibold text-slate-900">{{ movement.layaway?.folio || '—' }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Estado de apartado</dt>
            <dd class="font-medium text-slate-800">{{ movement.layaway?.status || '—' }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Cliente</dt>
            <dd class="font-medium text-slate-800">{{ movement.customer?.name || 'Sin cliente' }}</dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-slate-500">Telefono</dt>
            <dd class="font-medium text-slate-800">{{ movement.customer?.phone || '—' }}</dd>
          </div>
          <div class="pt-2">
            <Link
              :href="movement.layaway?.url"
              class="inline-flex items-center rounded-md border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              Ver apartado relacionado
            </Link>
          </div>
        </dl>
      </article>
    </section>
  </div>
</template>
