<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import LoyaltyBadge from '@/Components/Customers/LoyaltyBadge.vue'

const props = defineProps({
  customer:       { type: Object, required: true },
  recentSales:    { type: Array,  default: () => [] },
  recentLayaways: { type: Array,  default: () => [] },
  stats:          { type: Object, default: () => ({}) },
  can:            { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash ?? {})

function money(v) { return Number(v ?? 0).toFixed(2) }
function fmtDate(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) }

const saleStatusLabel = { completed: 'Completada', pending: 'Pendiente', cancelled: 'Cancelada' }
const saleStatusClass  = { completed: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', cancelled: 'bg-red-100 text-red-700' }
const layawayStatusLabel = { open: 'Activo', liquidated: 'Liquidado', cancelled: 'Cancelado' }
const layawayStatusClass  = { open: 'bg-amber-100 text-amber-700', liquidated: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-red-100 text-red-700' }
</script>

<template>
  <Head :title="customer.name ?? 'Cliente'" />

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <Link :href="route('customers.index')" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </Link>
        <div class="flex items-center gap-3">
          <div class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <span class="text-lg font-bold text-gray-600">{{ customer.name?.charAt(0)?.toUpperCase() ?? '?' }}</span>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-2xl font-bold text-gray-900">{{ customer.name }}</h1>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="customer.active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                {{ customer.active ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5 font-mono">{{ customer.phone ?? '— Sin telefono' }}</p>
            <p v-if="customer.email" class="text-xs text-gray-400">{{ customer.email }}</p>
          </div>
        </div>
      </div>
      <Link v-if="can.update" :href="route('customers.edit', customer.id)"
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
        </svg>
        Editar
      </Link>
    </div>

    <!-- Flash -->
    <div v-if="flash.success" class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
      <svg class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
      {{ flash.success }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left column -->
      <div class="space-y-6">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
          <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Compras totales</p>
            <p class="mt-1 text-3xl font-bold text-gray-900">{{ customer.purchases_count }}</p>
          </div>
          <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total gastado</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">${{ money(stats.total_spent) }}</p>
          </div>
          <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 col-span-2 lg:col-span-1">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ultima compra</p>
            <p class="mt-1 text-base font-semibold text-gray-700">{{ fmtDate(stats.last_sale) }}</p>
          </div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
          <LoyaltyBadge :count="customer.purchases_count" size="md" />
        </div>
      </div>

      <!-- Right column -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Ultimas ventas -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Ultimas ventas</h2>
            <span class="text-xs text-gray-400">Ultimas {{ recentSales.length }}</span>
          </div>
          <div v-if="recentSales.length === 0" class="py-8 text-center text-sm text-gray-400">Sin ventas registradas</div>
          <table v-else class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="py-2.5 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Folio</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                <th class="py-2.5 pl-3 pr-5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="sale in recentSales" :key="sale.id" class="hover:bg-gray-50 transition">
                <td class="py-3 pl-5 pr-3 text-xs font-mono font-semibold text-gray-800">#{{ sale.id }}</td>
                <td class="px-3 py-3 text-xs text-gray-500">{{ fmtDate(sale.created_at) }}</td>
                <td class="px-3 py-3">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="saleStatusClass[sale.status] ?? 'bg-gray-100 text-gray-500'">
                    {{ saleStatusLabel[sale.status] ?? sale.status }}
                  </span>
                </td>
                <td class="py-3 pl-3 pr-5 text-right text-sm font-semibold text-gray-900">${{ money(sale.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Ultimos apartados -->
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Ultimos apartados</h2>
            <span class="text-xs text-gray-400">Ultimos {{ recentLayaways.length }}</span>
          </div>
          <div v-if="recentLayaways.length === 0" class="py-8 text-center text-sm text-gray-400">Sin apartados registrados</div>
          <table v-else class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="py-2.5 pl-5 pr-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Folio</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Saldo</th>
                <th class="py-2.5 pl-3 pr-5 text-right"/>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="la in recentLayaways" :key="la.id" class="hover:bg-gray-50 transition">
                <td class="py-3 pl-5 pr-3 text-xs font-mono font-semibold text-gray-800">#{{ la.id }}</td>
                <td class="px-3 py-3">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="layawayStatusClass[la.status] ?? 'bg-gray-100 text-gray-500'">
                    {{ layawayStatusLabel[la.status] ?? la.status }}
                  </span>
                </td>
                <td class="px-3 py-3 text-right text-sm font-semibold text-gray-900">${{ money(la.subtotal) }}</td>
                <td class="px-3 py-3 text-right text-sm font-semibold"
                  :class="Number(la.balance) > 0 ? 'text-amber-600' : 'text-gray-400'">
                  ${{ money(la.balance) }}
                </td>
                <td class="py-3 pl-3 pr-5 text-right">
                  <Link :href="route('layaways.show', la.id)"
                    class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 transition">Ver</Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
