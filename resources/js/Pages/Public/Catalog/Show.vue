<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  product: { type: Object, required: true },
  whatsappNumber: { type: String, default: '' },
})

const currentImage = ref(0)
const images = computed(() => props.product.images ?? [])

function imageUrl(image) {
  return image?.url ?? (image?.path ? `/storage/${image.path}` : null)
}

function money(v) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0)
}

const whatsappLink = computed(() => {
  const number = (props.whatsappNumber || '').replace(/\D+/g, '')
  if (!number) return null
  const text = `Hola, me interesa el producto: ${props.product.name} (SKU: ${props.product.sku}) — Precio: ${money(props.product.sale_price)}. ¿Sigue disponible?`
  return `https://wa.me/${number}?text=${encodeURIComponent(text)}`
})

const metaDescription = computed(
  () =>
    `${props.product.name} — ${money(props.product.sale_price)}. Disponible en Samy Boutique.`,
)

const isOffline = ref(typeof window !== 'undefined' ? !window.navigator.onLine : false)

function syncConnectivityState() {
  if (typeof window === 'undefined') return
  isOffline.value = !window.navigator.onLine
}

onMounted(() => {
  syncConnectivityState()
  window.addEventListener('online', syncConnectivityState)
  window.addEventListener('offline', syncConnectivityState)
})

onBeforeUnmount(() => {
  window.removeEventListener('online', syncConnectivityState)
  window.removeEventListener('offline', syncConnectivityState)
})
</script>

<template>
  <Head :title="`${product.name} — Samy Boutique`">
    <meta name="description" :content="metaDescription" />
    <meta name="robots" content="noindex,nofollow" />
  </Head>

  <div class="min-h-screen bg-stone-50">

    <!-- ── NAV MIN ────────────────────────────────────────────── -->
    <nav class="border-b border-stone-200 bg-white px-4 py-4 sm:px-6">
      <div class="mx-auto flex max-w-6xl items-center justify-between">
        <Link
          :href="route('public.catalog.index')"
          class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-stone-500 transition hover:text-zinc-900"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
          Catálogo
        </Link>
        <span class="text-sm font-semibold tracking-widest text-zinc-900">Samy Boutique</span>
      </div>
    </nav>

    <!-- ── PRODUCTO ───────────────────────────────────────────── -->
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-12">
      <div
        v-if="isOffline"
        class="mb-6 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-center text-xs font-semibold tracking-wide text-stone-500"
      >
        Offline: mostrando última versión guardada
      </div>

      <div class="grid grid-cols-1 gap-0 overflow-hidden rounded-2xl bg-white shadow-sm lg:grid-cols-5">

        <!-- Imagen — 60% -->
        <section class="lg:col-span-3">
          <!-- Imagen principal -->
          <div class="aspect-[3/4] overflow-hidden bg-stone-100 lg:rounded-none">
            <img
              v-if="images.length && imageUrl(images[currentImage])"
              :src="imageUrl(images[currentImage])"
              :alt="product.name"
              class="h-full w-full object-cover transition duration-300"
            />
            <div v-else class="flex h-full items-center justify-center">
              <svg class="h-20 w-20 text-stone-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </div>
          </div>

          <!-- Thumbnails -->
          <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto border-t border-stone-100 bg-white p-3">
            <button
              v-for="(img, idx) in images"
              :key="img.id ?? idx"
              type="button"
              class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition duration-150"
              :class="idx === currentImage ? 'border-zinc-900 opacity-100' : 'border-transparent opacity-60 hover:opacity-100'"
              @click="currentImage = idx"
            >
              <img :src="imageUrl(img)" :alt="product.name" class="h-full w-full object-cover" />
            </button>
          </div>
        </section>

        <!-- Info — 40% -->
        <section class="flex flex-col justify-between border-t border-stone-100 p-7 lg:col-span-2 lg:border-l lg:border-t-0">
          <div>
            <!-- Nombre y precio -->
            <h1 class="text-2xl font-semibold tracking-wide text-zinc-900 sm:text-3xl">
              {{ product.name }}
            </h1>
            <p class="mt-1 font-mono text-xs text-stone-400">SKU: {{ product.sku }}</p>

            <p class="mt-6 text-3xl font-semibold tracking-wide text-zinc-900">
              {{ money(product.sale_price) }}
            </p>

            <!-- Atributos -->
            <div class="mt-8 space-y-3 border-t border-stone-100 pt-6">
              <div class="flex items-center justify-between">
                <span class="text-sm text-stone-500">Categoría</span>
                <span class="text-sm font-semibold text-zinc-900">{{ product.category?.name ?? '—' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-stone-500">Género</span>
                <span class="text-sm font-semibold capitalize text-zinc-900">{{ product.gender ?? '—' }}</span>
              </div>
              <div v-if="product.size" class="flex items-center justify-between">
                <span class="text-sm text-stone-500">Talla</span>
                <span class="rounded-full border border-stone-200 px-3 py-0.5 text-sm font-semibold text-zinc-900">
                  {{ product.size.name }}
                </span>
              </div>
              <div v-if="product.color" class="flex items-center justify-between">
                <span class="text-sm text-stone-500">Color</span>
                <span class="rounded-full border border-stone-200 px-3 py-0.5 text-sm font-semibold text-zinc-900">
                  {{ product.color.name }}
                </span>
              </div>
            </div>

            <!-- Texto elegante -->
            <p class="mt-8 text-sm leading-relaxed text-stone-500">
              ¿Te interesa? Contáctanos para apartar este artículo antes de que se agote.
            </p>
          </div>

          <!-- Botones -->
          <div class="mt-8 space-y-3">
            <a
              v-if="whatsappLink"
              :href="whatsappLink"
              target="_blank"
              rel="noopener noreferrer"
              class="flex w-full items-center justify-center gap-3 rounded-2xl bg-zinc-900 py-4 text-sm font-semibold tracking-wide text-white transition duration-200 hover:bg-zinc-800"
            >
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20.52 3.48A11.82 11.82 0 0 0 12.1 0C5.54 0 .2 5.34.2 11.9c0 2.1.55 4.16 1.6 5.97L0 24l6.32-1.75a11.86 11.86 0 0 0 5.68 1.45h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.17-3.49-8.32Zm-8.42 18.2h-.01a9.9 9.9 0 0 1-5.05-1.39l-.36-.21-3.75 1.04 1-3.65-.23-.38a9.92 9.92 0 0 1-1.52-5.2c0-5.47 4.45-9.92 9.92-9.92 2.65 0 5.14 1.03 7.01 2.9a9.86 9.86 0 0 1 2.9 7.01c0 5.47-4.45 9.92-9.91 9.92Zm5.44-7.4c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.47-.89-.79-1.5-1.76-1.67-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.48-.5-.67-.51l-.57-.01c-.2 0-.52.07-.8.37-.27.3-1.04 1.01-1.04 2.45 0 1.44 1.06 2.84 1.2 3.03.15.2 2.07 3.16 5.02 4.43.7.3 1.25.48 1.67.61.7.22 1.33.19 1.83.12.56-.08 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35Z"/>
              </svg>
              Enviar por WhatsApp
            </a>

            <Link
              :href="route('public.catalog.index')"
              class="flex w-full items-center justify-center gap-2 rounded-2xl border border-stone-200 bg-white py-4 text-sm font-semibold tracking-wide text-zinc-900 transition duration-200 hover:border-zinc-900"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
              </svg>
              Volver al catálogo
            </Link>
          </div>
        </section>
      </div>
    </main>

    <!-- ── FOOTER ─────────────────────────────────────────────── -->
    <footer class="mt-8 border-t border-stone-200 bg-white py-8 text-center">
      <p class="text-xs uppercase tracking-widest text-stone-400">Samy Boutique · {{ new Date().getFullYear() }}</p>
    </footer>
  </div>
</template>
