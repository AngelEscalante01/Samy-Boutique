<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String },
});

const form = useForm({ email: '' });
const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 50));

const submit = () => { form.post(route('password.email')); };
</script>

<template>
    <Head title="Recuperar contraseña — Samy Boutique" />

    <div class="min-h-screen bg-gradient-to-br from-stone-50 via-zinc-50 to-stone-100 flex flex-col items-center justify-center px-4 py-12 relative overflow-hidden">

        <div class="pointer-events-none absolute -top-32 -right-32 w-96 h-96 rounded-full bg-amber-100/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-stone-200/60 blur-3xl"></div>

        <div
            class="relative w-full max-w-md transition-all duration-500 ease-out"
            :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
        >
            <div class="bg-white rounded-2xl shadow-sm border border-stone-200 overflow-hidden">

                <!-- Header -->
                <div class="px-8 pt-8 pb-6 text-center border-b border-stone-100">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-950 mb-4">
                        <svg class="w-7 h-7 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3a2 2 0 0 1 2 2c0 .8-.4 1.5-1 1.9L20 13H4l7-6.1A2 2 0 0 1 10 5a2 2 0 0 1 2-2z"/>
                            <line x1="4" y1="13" x2="4" y2="20"/>
                            <line x1="20" y1="13" x2="20" y2="20"/>
                            <line x1="4" y1="20" x2="20" y2="20"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold tracking-wide text-zinc-900">Samy Boutique</h1>
                    <p class="mt-1 text-sm text-stone-400 tracking-wide">Recupera el acceso a tu cuenta</p>
                </div>

                <!-- Body -->
                <div class="px-8 py-7">

                    <div v-if="status" class="mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ status }}
                    </div>

                    <p class="mb-6 text-sm text-stone-500 leading-relaxed">
                        Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
                    </p>

                    <form @submit.prevent="submit" novalidate>
                        <div class="mb-6">
                            <label for="email" class="block text-xs font-semibold uppercase tracking-widest text-zinc-500 mb-1.5">
                                Correo electrónico
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                autofocus
                                required
                                placeholder="tu@correo.com"
                                class="w-full rounded-xl border px-4 py-2.5 text-sm text-zinc-900 placeholder-stone-400 outline-none transition focus:ring-2 focus:ring-amber-200/70 focus:border-amber-400"
                                :class="form.errors.email
                                    ? 'border-red-300 bg-red-50/30 focus:ring-red-200/50 focus:border-red-400'
                                    : 'border-stone-300 bg-white'"
                            />
                            <p v-if="form.errors.email" class="mt-1.5 flex items-center gap-1 text-xs text-red-500">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold tracking-wide text-white shadow-sm transition-all duration-150 hover:bg-zinc-800 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ form.processing ? 'Enviando…' : 'Enviar enlace de recuperación' }}
                        </button>
                    </form>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-stone-50/60 border-t border-stone-100 flex items-center justify-between">
                    <Link
                        :href="route('login')"
                        class="flex items-center gap-1.5 text-xs text-stone-400 hover:text-zinc-700 transition-colors focus:outline-none focus-visible:underline"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Volver al inicio de sesión
                    </Link>
                    <span class="text-xs text-stone-400 tracking-wide">© 2026 Samy Boutique</span>
                </div>

            </div>
        </div>
    </div>
</template>
