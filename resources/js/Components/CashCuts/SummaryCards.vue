<script setup>
defineProps({
  summary: { type: Object, required: true },
})

function money(v) {
  return Number(v ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
  <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">

    <!-- Total vendido -->
    <div class="col-span-2 sm:col-span-1 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200
                flex flex-col gap-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total vendido</p>
      <p class="text-2xl font-bold text-gray-900">${{ money(summary.total_sales) }}</p>
      <p class="text-xs text-gray-400">{{ summary.sales_count }} venta{{ summary.sales_count === 1 ? '' : 's' }}</p>
    </div>

    <!-- Descuentos manuales -->
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 flex flex-col gap-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Desc. manual</p>
      <p class="text-xl font-bold text-amber-600">${{ money(summary.discount_manual_total) }}</p>
    </div>

    <!-- Descuentos cupón -->
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 flex flex-col gap-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Desc. cupones</p>
      <p class="text-xl font-bold text-purple-600">${{ money(summary.discount_coupon_total) }}</p>
    </div>

    <!-- Descuentos fidelidad -->
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 flex flex-col gap-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Desc. fidelidad</p>
      <p class="text-xl font-bold text-blue-600">${{ money(summary.discount_loyalty_total) }}</p>
    </div>

    <!-- Canceladas -->
    <div v-if="summary.canceled_count > 0"
         class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-red-100 flex flex-col gap-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Canceladas</p>
      <p class="text-xl font-bold text-red-500">{{ summary.canceled_count }}</p>
    </div>

  </div>
</template>
