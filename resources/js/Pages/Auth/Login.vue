<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const visible = ref(false);
const showPassword = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 50));

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión — Samy Boutique" />

    <!-- Fondo con gradiente y glow champagne -->
    <div class="min-h-screen bg-gradient-to-br from-stone-50 via-zinc-50 to-stone-100 flex flex-col items-center justify-center px-4 py-12 relative overflow-hidden">

        <!-- Glow decorativo esquina superior derecha -->
        <div class="pointer-events-none absolute -top-32 -right-32 w-96 h-96 rounded-full bg-amber-100/50 blur-3xl"></div>
        <!-- Glow decorativo esquina inferior izquierda -->
        <div class="pointer-events-none absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-stone-200/60 blur-3xl"></div>

        <!-- Card principal con animación de entrada -->
        <div
            class="relative w-full max-w-md transition-all duration-500 ease-out"
            :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
        >
            <div class="bg-white rounded-2xl shadow-sm border border-stone-200 overflow-hidden">

                <!-- Header del card -->
                <div class="px-8 pt-8 pb-6 text-center border-b border-stone-100">
                    <!-- Logo -->
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white border border-stone-200 shadow-sm mb-4 overflow-hidden">
                        <img :src="$page.props.logoUrl" alt="Samy Boutique" class="w-full h-full object-contain p-1" />
                    </div>
                    <h1 class="text-2xl font-semibold tracking-wide text-zinc-900">Samy Boutique</h1>
                    <p class="mt-1 text-sm text-stone-400 tracking-wide">Accede para gestionar ventas e inventario</p>
                </div>

                <!-- Body del card -->
                <div class="px-8 py-7">

                    <!-- Mensaje de estado (reset password, etc.) -->
                    <div v-if="status" class="mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" novalidate>

                        <!-- Email -->
                        <div class="mb-5">
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
                                class="w-full rounded-xl border px-4 py-2.5 text-sm text-zinc-900 placeholder-stone-400 outline-none transition
                                       focus:ring-2 focus:ring-amber-200/70 focus:border-amber-400"
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

                        <!-- Contraseña -->
                        <div class="mb-5">
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="password" class="block text-xs font-semibold uppercase tracking-widest text-zinc-500">
                                    Contraseña
                                </label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-xs text-stone-400 hover:text-amber-600 transition-colors duration-150 focus:outline-none focus-visible:underline"
                                >
                                    ¿Olvidaste tu contraseña?
                                </Link>
                            </div>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    placeholder="••••••••"
                                    class="w-full rounded-xl border px-4 py-2.5 pr-10 text-sm text-zinc-900 placeholder-stone-400 outline-none transition
                                           focus:ring-2 focus:ring-amber-200/70 focus:border-amber-400"
                                    :class="form.errors.password
                                        ? 'border-red-300 bg-red-50/30 focus:ring-red-200/50 focus:border-red-400'
                                        : 'border-stone-300 bg-white'"
                                />
                                <!-- Botón ojo -->
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-3 flex items-center text-stone-400 hover:text-zinc-600 transition-colors"
                                    tabindex="-1"
                                    :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                >
                                    <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 flex items-center gap-1 text-xs text-red-500">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Recordarme -->
                        <div class="mb-6">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                                <div class="relative">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        v-model="form.remember"
                                        class="sr-only"
                                    />
                                    <div
                                        class="w-4 h-4 rounded border transition-all duration-150 flex items-center justify-center"
                                        :class="form.remember
                                            ? 'bg-zinc-900 border-zinc-900'
                                            : 'bg-white border-stone-300 group-hover:border-stone-400'"
                                    >
                                        <svg v-if="form.remember" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                </div>
                                <span class="text-sm text-stone-500 group-hover:text-zinc-700 transition-colors">Recordarme</span>
                            </label>
                        </div>

                        <!-- Botón submit -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold tracking-wide text-white shadow-sm transition-all duration-150
                                   hover:bg-zinc-800 hover:shadow-md
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:ring-offset-2
                                   disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <!-- Spinner -->
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ form.processing ? 'Ingresando…' : 'Iniciar sesión' }}
                        </button>

                    </form>
                </div>

                <!-- Footer del card -->
                <div class="px-8 py-4 bg-stone-50/60 border-t border-stone-100 flex items-center justify-between">
                    <span class="text-xs text-stone-400 tracking-wide">© 2026 Samy Boutique</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200">Gerente</span>
                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 ring-1 ring-inset ring-zinc-200">Cajero</span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
