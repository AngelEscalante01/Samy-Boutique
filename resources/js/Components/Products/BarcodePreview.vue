<script setup>
defineProps({ codes: { type: Array, default: () => [] }, compact: { type: Boolean, default: false } })
</script>

<template>
  <section v-if="codes.length" aria-label="Códigos de barras del producto" :class="compact ? 'min-w-0 border-t border-stone-100 bg-white pt-3' : 'border border-stone-200 bg-white p-3'">
    <h2 v-if="!compact" class="text-sm font-semibold text-stone-900">Códigos de barras</h2>
    <p v-if="!compact" class="mt-1 text-xs text-stone-500">Código guardado de cada variante. Las variantes nuevas tendrán su código al guardar.</p>
    <div :class="compact ? 'min-w-0' : 'mt-3 flex flex-wrap gap-3'">
      <figure v-for="code in codes" :key="code.sku" class="min-w-0 max-w-full bg-white" :class="compact ? '' : 'rounded-lg border border-stone-200 p-3'">
        <figcaption v-if="!compact" class="mb-2 text-sm font-medium text-stone-900">{{ code.label || 'Variante' }}<span v-if="!code.active" class="ml-2 text-xs text-stone-500">Inactiva</span></figcaption>
        <p v-if="code.error" class="text-xs text-red-700">{{ code.error }}</p>
        <div v-else class="max-w-full overflow-x-auto bg-white py-1">
          <svg :width="(code.modules + 20) * 2" height="64" :viewBox="`0 0 ${code.modules + 20} 64`" role="img" :aria-label="`Código de barras ${code.sku}`" class="block max-w-none" preserveAspectRatio="none" shape-rendering="crispEdges">
            <rect :width="code.modules + 20" height="64" fill="white" />
            <rect v-for="(bar, index) in code.bars" :key="index" :x="bar[0] + 10" y="0" :width="bar[1]" height="64" fill="black" />
          </svg>
        </div>
        <p class="mt-2 break-all text-center font-mono text-xs text-stone-900">{{ code.sku || 'Sin SKU' }}</p>
      </figure>
    </div>
  </section>
</template>
