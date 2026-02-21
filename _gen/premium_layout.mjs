/**
 * Samy Boutique – Premium AppLayout Generator
 * node _gen/premium_layout.mjs
 *
 * Sobreescribe Layouts/AppLayout.vue con el estilo boutique premium.
 * Preserva toda la lógica (navItems, permisos, roles, Ziggy routes).
 */
import { writeFileSync, mkdirSync } from 'fs'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const base = resolve(__dirname, '..', 'resources', 'js')

function write(rel, content) {
  const abs = resolve(base, rel)
  mkdirSync(dirname(abs), { recursive: true })
  writeFileSync(abs, content, 'utf-8')
  console.log('  OK', rel)
}

const LAYOUT = `<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const sidebarOpen = ref(false);

const page = usePage();

const user      = computed(() => page.props.auth?.user ?? null);
const role      = computed(() => user.value?.role ?? (user.value?.roles?.[0] ?? null));
const roleLabel = computed(() => (role.value ? String(role.value).toUpperCase() : null));
const isManager = computed(() => String(role.value ?? '').toLowerCase() === 'gerente');

const permissionsSet = computed(() => {
    const perms = user.value?.permissions;
    if (Array.isArray(perms)) return new Set(perms);
    const legacy = page.props.permissions;
    if (legacy && typeof legacy === 'object') {
        return new Set(Object.entries(legacy).filter(([, v]) => v === true).map(([k]) => k));
    }
    return new Set();
});

function can(permission) {
    if (!permission) return true;
    return permissionsSet.value.has(permission);
}

function canAny(permissions = []) {
    return permissions.some((p) => can(p));
}

const icons = {
    dashboard:    { viewBox: '0 0 24 24', paths: ['M3 10.5L12 3l9 7.5', 'M5 9.5V21h14V9.5'] },
    salesHistory: { viewBox: '0 0 24 24', paths: ['M6 3h12a2 2 0 0 1 2 2v16H4V5a2 2 0 0 1 2-2Z', 'M8 7h8', 'M8 11h8', 'M8 15h6'] },
    pos:          { viewBox: '0 0 24 24', paths: ['M7 7h10', 'M7 11h10', 'M7 15h6', 'M6 3h12a2 2 0 0 1 2 2v14H4V5a2 2 0 0 1 2-2Z'] },
    layaways:     { viewBox: '0 0 24 24', paths: ['M7 7h10', 'M7 11h10', 'M7 15h10', 'M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16'] },
    customers:    { viewBox: '0 0 24 24', paths: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M20 21v-2a3 3 0 0 0-2-2.83', 'M18 3.17a4 4 0 0 1 0 7.66'] },
    products:     { viewBox: '0 0 24 24', paths: ['M6 7h12', 'M6 7l1 14h10l1-14', 'M9 7V5a3 3 0 0 1 6 0v2'] },
    catalogs:     { viewBox: '0 0 24 24', paths: ['M4 19a2 2 0 0 0 2 2h14', 'M4 5a2 2 0 0 1 2-2h14v18H6a2 2 0 0 1-2-2V5Z'] },
    coupons:      { viewBox: '0 0 24 24', paths: ['M3 9a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v6a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2V9Z', 'M12 7v10'] },
    reports:      { viewBox: '0 0 24 24', paths: ['M4 19V5', 'M8 17v-6', 'M12 17V7', 'M16 17v-4', 'M20 17v-8'] },
    cashcuts:     { viewBox: '0 0 24 24', paths: ['M12 1v22', 'M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6'] },
    settings:     { viewBox: '0 0 24 24', paths: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M19.4 15a7.97 7.97 0 0 0 .1-1 7.97 7.97 0 0 0-.1-1l2.1-1.6-2-3.4-2.5 1a8.2 8.2 0 0 0-1.7-1L15 3h-6l-.4 3a8.2 8.2 0 0 0-1.7 1l-2.5-1-2 3.4L4.6 13a7.97 7.97 0 0 0-.1 1c0 .34.03.67.1 1L2.5 16.6l2 3.4 2.5-1a8.2 8.2 0 0 0 1.7 1l.4 3h6l.4-3a8.2 8.2 0 0 0 1.7-1l2.5 1 2-3.4L19.4 15Z'] },
    users:        { viewBox: '0 0 24 24', paths: ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M23 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'] },
};

const navItems = computed(() => [
    {
        key: 'dashboard',
        label: 'Dashboard',
        href: route('dashboard'),
        permission: null,
        show: isManager.value,
        active: route().current('dashboard'),
    },
    {
        key: 'pos',
        label: 'POS · Ventas',
        href: route('pos.index'),
        permission: 'pos.view',
        active: route().current('pos.index'),
    },
    {
        key: 'salesHistory',
        label: 'Historial ventas',
        href: route('sales.index'),
        permission: 'sales.view',
        active: route().current('sales.index'),
    },
    {
        key: 'layaways',
        label: 'Apartados',
        href: route('layaways.index'),
        permission: 'pos.view',
        active: route().current('layaways.index') || route().current('layaways.show'),
    },
    {
        key: 'customers',
        label: 'Clientes',
        href: route('customers.index'),
        permission: 'customers.view',
        active: route().current('customers.*') || page.url.startsWith('/customers'),
    },
    {
        key: 'products',
        label: 'Productos',
        href: route('products.index'),
        permission: 'products.view',
        active: route().current('products.index') || route().current('products.create') || route().current('products.edit'),
    },
    {
        key: 'catalogs',
        label: 'Catálogos',
        href: route('catalogs.categories.index'),
        permission: 'catalogs.manage',
        active: page.url.startsWith('/catalogs'),
    },
    {
        key: 'coupons',
        label: 'Cupones',
        href: route('coupons.index'),
        permission: 'coupons.manage',
        active: page.url.startsWith('/coupons'),
    },
    {
        key: 'cashcuts',
        label: 'Corte diario',
        href: route('cashcuts.index'),
        permission: null,
        show: isManager.value && canAny(['cash_cuts.create', 'reports.view']),
        active: route().current('cashcuts.index') || page.url.startsWith('/cash-cuts'),
    },
    {
        key: 'reports',
        label: 'Reportes',
        href: route('reports.index'),
        permission: 'reports.view',
        active: route().current('reports.index') || page.url.startsWith('/reports'),
    },
    {
        key: 'users',
        label: 'Usuarios',
        href: route('users.index'),
        permission: 'users.manage',
        active: route().current('users.index') || route().current('users.create') || route().current('users.edit') || page.url.startsWith('/users'),
    },
    {
        key: 'settings',
        label: 'Configuración',
        href: route('settings.index'),
        permission: 'settings.manage',
        show: isManager.value,
        active: route().current('settings.*') || page.url.startsWith('/settings'),
    },
]);

const visibleNavItems = computed(() =>
    navItems.value.filter((item) => {
        if (item.show === false) return false;
        return can(item.permission);
    }),
);

function closeMobileSidebar() { sidebarOpen.value = false; }
function iconFor(key) { return icons[key] ?? null; }
<\/script>

<template>
    <div class="min-h-screen bg-stone-50">

        <!-- ── Overlay móvil ───────────────────────────────────────────────── -->
        <transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 md:hidden"
                @click="closeMobileSidebar"
                aria-hidden="true"
            >
                <div class="absolute inset-0 bg-zinc-950/70 backdrop-blur-sm" />
            </div>
        </transition>

        <!-- ── Sidebar móvil ──────────────────────────────────────────────── -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full bg-zinc-950 shadow-2xl transition-transform duration-200 ease-out md:hidden"
            :class="{ 'translate-x-0': sidebarOpen }"
            aria-label="Menú principal"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between border-b border-white/8 px-5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-400/20 text-amber-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
                        </svg>
                    </span>
                    <span class="text-sm font-semibold tracking-wider text-white">Samy Boutique</span>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-white/10 hover:text-white"
                    @click="closeMobileSidebar"
                    aria-label="Cerrar menú"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="px-3 py-4">
                <div class="space-y-0.5">
                    <Link
                        v-for="item in visibleNavItems"
                        :key="item.key"
                        :href="item.href"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150"
                        :class="item.active
                            ? 'border-l-4 border-amber-400 pl-2 bg-zinc-900 text-white'
                            : 'border-l-4 border-transparent text-zinc-400 hover:bg-zinc-900/70 hover:text-zinc-100'"
                        @click="closeMobileSidebar"
                    >
                        <svg
                            v-if="iconFor(item.key)"
                            class="h-4.5 w-4.5 shrink-0 transition-colors duration-150"
                            :class="item.active ? 'text-amber-300' : 'text-zinc-500 group-hover:text-zinc-300'"
                            :viewBox="iconFor(item.key).viewBox"
                            fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path v-for="d in iconFor(item.key).paths" :key="d" :d="d" />
                        </svg>
                        <span class="truncate">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>
        </aside>

        <!-- ── Sidebar desktop ────────────────────────────────────────────── -->
        <aside
            class="hidden md:fixed md:inset-y-0 md:flex md:w-64 md:flex-col bg-zinc-950"
            aria-label="Menú principal"
        >
            <!-- Logo -->
            <div class="flex h-16 flex-shrink-0 items-center gap-3 border-b border-white/8 px-5">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-400/15 text-amber-300 ring-1 ring-amber-400/20">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold tracking-wider text-white">Samy Boutique</p>
                    <p class="text-[10px] tracking-widest text-zinc-500 uppercase">Point of Sale</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-zinc-800">
                <div class="space-y-0.5">
                    <Link
                        v-for="item in visibleNavItems"
                        :key="item.key"
                        :href="item.href"
                        class="group flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 text-sm font-medium transition-all duration-150"
                        :class="item.active
                            ? 'border-amber-400 bg-zinc-900 text-white'
                            : 'border-transparent text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-100'"
                    >
                        <svg
                            v-if="iconFor(item.key)"
                            class="h-4.5 w-4.5 shrink-0 transition-colors duration-150"
                            :class="item.active ? 'text-amber-300' : 'text-zinc-500 group-hover:text-zinc-300'"
                            :viewBox="iconFor(item.key).viewBox"
                            fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path v-for="d in iconFor(item.key).paths" :key="d" :d="d" />
                        </svg>
                        <span class="truncate">{{ item.label }}</span>
                        <!-- Active indicator dot -->
                        <span v-if="item.active" class="ml-auto h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0" />
                    </Link>
                </div>
            </nav>

            <!-- Footer del sidebar -->
            <div class="border-t border-white/8 px-4 py-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-xs font-bold text-zinc-200">
                        {{ user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-medium text-zinc-200">{{ user?.name }}</p>
                        <p class="text-[10px] text-zinc-500 truncate">{{ user?.email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ── Columna principal ───────────────────────────────────────────── -->
        <div class="flex min-h-screen flex-col md:pl-64">

            <!-- Header -->
            <header class="sticky top-0 z-30 border-b border-stone-200 bg-white/95 backdrop-blur-sm">
                <div class="flex h-14 items-center justify-between px-4 sm:px-6">

                    <!-- Hamburger (móvil) -->
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl p-2 text-stone-600 transition-colors hover:bg-stone-100 md:hidden"
                            @click="sidebarOpen = true"
                            aria-label="Abrir menú"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <!-- Brand (móvil) -->
                        <span class="text-sm font-semibold tracking-wider text-stone-800 md:hidden">Samy Boutique</span>
                    </div>

                    <!-- Right side -->
                    <div class="flex items-center gap-3">
                        <!-- Role badge -->
                        <span
                            v-if="roleLabel"
                            :class="isManager
                                ? 'bg-amber-100 text-amber-900 ring-amber-200'
                                : 'bg-zinc-100 text-zinc-600 ring-zinc-200'"
                            class="hidden sm:inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                        >
                            {{ roleLabel }}
                        </span>

                        <!-- User dropdown -->
                        <Dropdown align="right" width="52">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 shadow-sm transition-colors hover:bg-stone-50"
                                >
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white">
                                        {{ user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                                    </span>
                                    <span class="hidden sm:block">{{ user?.name }}</span>
                                    <svg class="h-4 w-4 text-stone-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="px-4 py-3 border-b border-stone-100">
                                    <p class="text-sm font-semibold text-stone-900">{{ user?.name }}</p>
                                    <p class="text-xs text-stone-400 mt-0.5">{{ user?.email }}</p>
                                    <span
                                        v-if="roleLabel"
                                        :class="isManager
                                            ? 'bg-amber-100 text-amber-900 ring-amber-200'
                                            : 'bg-zinc-100 text-zinc-600 ring-zinc-200'"
                                        class="mt-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                                    >{{ roleLabel }}</span>
                                </div>
                                <DropdownLink :href="route('profile.edit')">
                                    <svg class="mr-2 h-4 w-4 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    Perfil
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    <svg class="mr-2 h-4 w-4 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                    </svg>
                                    Cerrar sesión
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <transition
                    mode="out-in"
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <div :key="page.url">
                        <slot />
                    </div>
                </transition>
            </main>
        </div>
    </div>
</template>
`

console.log('\n▸ AppLayout')
write('Layouts/AppLayout.vue', LAYOUT)
console.log('\nDone ✓')
