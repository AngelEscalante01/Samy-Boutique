<script setup>
import { computed } from 'vue'

const props = defineProps({
  payments: { type: Array, required: true },
  compact:  { type: Boolean, default: false },
})

const METHOD = {
  cash:     { label: 'Efectivo',      color: 'text-emerald-600 bg-emerald-50', dot: 'bg-emerald-400' },
  card:     { label: 'Tarjeta',       color: 'text-blue-600    bg-blue-50',    dot: 'bg-blue-400'    },
  transfer: { label: 'Transferencia', color: 'text-violet-600  bg-violet-50',  dot: 'bg-violet-400'  },
  other:    { label: 'Otro',          color: 'text-gray-600    bg-gray-50',    dot: 'bg-gray-400'    },
}

function cfg(method) { return METHOD[method] ?? METHOD.other }
function money(v)    { return Number(v ?? 0).toFixed(2) }

const isMixed = computed(() => new Set(props.payments.map(p => p.method)).size > 1)
</script>

<template>
  <!-- Inline badge list (compact=true): used in Index table -->
  <template v-if="compact">
    <span v-if="isMixed"
      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-purple-50 text-purple-700 ring-1 ring-purple-200">
      Mixto
    </span>
    <span v-else-if="payments.length"
      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
      :class="cfg(payments[0].method).color + ' ring-' + cfg(payments[0].method).dot.replace('bg-', '')">
      {{ cfg(payments[0].method).label }}
    </span>
    <span v-else class="text-xs text-gray-400">—</span>
  </template>

  <!-- Full list (compact=false): used in Show page -->
  <template v-else>
    <div v-if="!payments.length" class="text-sm text-gray-400 italic">Sin pagos registrados</div>
    <ul v-else class="space-y-2">
      <li v-for="pmt in payments" :key="pmt.id"
        class="flex items-start justify-between rounded-lg px-4 py-3 ring-1 ring-gray-100 bg-gray-50">
        <div class="flex items-center gap-3">
          <span class="h-2 w-2 rounded-full flex-shrink-0 mt-1.5" :class="cfg(pmt.method).dot" />
          <div>
            <p class="text-sm font-semibold text-gray-800">{{ cfg(pmt.method).label }}</p>
            <p v-if="pmt.method === 'transfer' && pmt.reference"
              class="text-xs text-gray-400 mt-0.5 font-mono">
              Ref: {{ pmt.reference }}
            </p>
          </div>
        </div>
        <span class="text-sm font-bold text-gray-900">${{ money(pmt.amount) }}</span>
      </li>
    </ul>
    <div v-if="payments.length > 1" class="flex justify-between border-t border-gray-200 pt-2 mt-2">
      <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total pagado</span>
      <span class="text-sm font-bold text-emerald-700">
        ${{ money(payments.reduce((s, p) => s + Number(p.amount), 0)) }}
      </span>
    </div>
  </template>
</template>
