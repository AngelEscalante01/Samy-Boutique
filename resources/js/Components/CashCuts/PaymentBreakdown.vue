<script setup>
defineProps({
  payments: { type: Object, required: true },
})

function money(v) {
  return Number(v ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const METHODS = [
  { key: 'cash',     label: 'Efectivo',       icon: 'banknotes',  color: 'text-emerald-600 bg-emerald-50 ring-emerald-200' },
  { key: 'card',     label: 'Tarjeta',         icon: 'card',       color: 'text-blue-600    bg-blue-50    ring-blue-200'    },
  { key: 'transfer', label: 'Transferencia',   icon: 'transfer',   color: 'text-violet-600  bg-violet-50  ring-violet-200'  },
  { key: 'other',    label: 'Otro',            icon: 'other',      color: 'text-gray-600    bg-gray-50    ring-gray-200'    },
]
</script>

<template>
  <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50">
      <h3 class="text-sm font-semibold text-gray-700">Desglose por método de pago</h3>
    </div>
    <table class="w-full text-sm">
      <tbody class="divide-y divide-gray-50">
        <tr
          v-for="m in METHODS"
          :key="m.key"
          class="hover:bg-gray-50/60 transition-colors"
        >
          <td class="px-5 py-3">
            <span
              :class="m.color"
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
            >{{ m.label }}</span>
          </td>
          <td class="px-5 py-3 text-right font-semibold tabular-nums text-gray-800">
            ${{ money(payments[m.key]) }}
          </td>
        </tr>
        <!-- Total -->
        <tr class="bg-gray-50/80 border-t border-gray-200">
          <td class="px-5 py-3 font-bold text-gray-900">Total</td>
          <td class="px-5 py-3 text-right font-bold text-gray-900 tabular-nums text-base">
            ${{ money(Object.values(payments).reduce((a, b) => a + Number(b), 0)) }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
