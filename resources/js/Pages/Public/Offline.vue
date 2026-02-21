<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
  <Head title="Sin conexión — Samy Boutique">
    <meta name="description" content="No hay conexión a internet. Vuelve a conectarte para cargar el catálogo." />
    <meta name="robots" content="noindex,nofollow" />
  </Head>

  <div class="min-h-screen bg-stone-50">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
      <section class="w-full rounded-3xl border border-stone-200 bg-white p-8 text-center shadow-sm sm:p-10">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-zinc-900 text-white">
          <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.515-3.514 9.216-3.514 12.73 0M1.924 8.674c5.272-5.272 13.881-5.272 19.153 0M12 18h.008v.008H12V18Z" />
          </svg>
        </div>

        <h1 class="text-3xl font-semibold tracking-wide text-zinc-900">Sin conexión</h1>
        <p class="mt-3 text-sm leading-relaxed text-stone-500">
          Conéctate para cargar el catálogo.
        </p>

        <div class="mt-7 space-y-3">
          <Link
            :href="route('public.catalog.index')"
            class="block rounded-2xl bg-zinc-900 px-5 py-3.5 text-sm font-semibold tracking-wide text-white transition duration-300 hover:bg-zinc-800"
          >
            Ir al catálogo
          </Link>
          <button
            type="button"
            class="w-full rounded-2xl border border-stone-200 bg-white px-5 py-3.5 text-sm font-semibold tracking-wide text-zinc-900 transition duration-300 hover:border-zinc-900"
            @click="window.location.reload()"
          >
            Reintentar
          </button>
        </div>
      </section>
    </main>
  </div>
</template>