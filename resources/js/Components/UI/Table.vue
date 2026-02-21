<script setup>
defineProps({
  striped:   { type: Boolean, default: false },
  bordered:  { type: Boolean, default: false },
  empty:     { type: Boolean, default: false },
  emptyText: { type: String,  default: 'Sin registros.' },
})
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-stone-100 text-sm">
        <!-- Head -->
        <thead class="bg-stone-50">
          <tr class="text-xs font-semibold uppercase tracking-wide text-stone-500">
            <slot name="head" />
          </tr>
        </thead>

        <!-- Body -->
        <tbody
          :class="[
            'bg-white',
            striped  ? '[&>tr:nth-child(even)]:bg-stone-50/50' : 'divide-y divide-stone-50',
          ]"
        >
          <slot name="body" />

          <!-- Empty state -->
          <tr v-if="empty">
            <td
              colspan="100%"
              class="px-5 py-10 text-center text-sm text-stone-400"
            >
              <slot name="empty">{{ emptyText }}</slot>
            </td>
          </tr>
        </tbody>

        <!-- Foot -->
        <tfoot v-if="$slots.foot" class="border-t border-stone-100 bg-stone-50/40">
          <slot name="foot" />
        </tfoot>
      </table>
    </div>

    <!-- Pagination slot (outside table) -->
    <div v-if="$slots.pagination" class="border-t border-stone-100 bg-stone-50/40 px-5 py-3">
      <slot name="pagination" />
    </div>
  </div>
</template>
