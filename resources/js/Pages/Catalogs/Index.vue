<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, required: true },
    sizes: { type: Array, required: true },
    colors: { type: Array, required: true },
});

const tab = ref('categories');

const categoriesSorted = computed(() => [...props.categories].sort((a, b) => String(a.name).localeCompare(String(b.name))));
const sizesSorted = computed(() => [...props.sizes].sort((a, b) => String(a.name).localeCompare(String(b.name))));
const colorsSorted = computed(() => [...props.colors].sort((a, b) => String(a.name).localeCompare(String(b.name))));

function resetEditing() {
    editingId.value = null;
    editingType.value = null;
}

const editingId = ref(null);
const editingType = ref(null);

const categoryForm = useForm({ name: '', active: true });
const sizeForm = useForm({ name: '', active: true });
const colorForm = useForm({ name: '', hex: '', active: true });

function startEdit(type, item) {
    editingType.value = type;
    editingId.value = item.id;

    if (type === 'categories') {
        categoryForm.name = item.name ?? '';
        categoryForm.active = !!item.active;
        tab.value = 'categories';
    }

    if (type === 'sizes') {
        sizeForm.name = item.name ?? '';
        sizeForm.active = !!item.active;
        tab.value = 'sizes';
    }

    if (type === 'colors') {
        colorForm.name = item.name ?? '';
        colorForm.hex = item.hex ?? '';
        colorForm.active = !!item.active;
        tab.value = 'colors';
    }
}

function cancelEdit() {
    resetEditing();
    categoryForm.reset();
    sizeForm.reset();
    colorForm.reset();
    categoryForm.clearErrors();
    sizeForm.clearErrors();
    colorForm.clearErrors();
}

function submitCategory() {
    if (editingType.value === 'categories' && editingId.value) {
        categoryForm.put(route('catalogs.categories.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
        return;
    }

    categoryForm.post(route('catalogs.categories.store'), {
        preserveScroll: true,
        onSuccess: () => categoryForm.reset(),
    });
}

function submitSize() {
    if (editingType.value === 'sizes' && editingId.value) {
        sizeForm.put(route('catalogs.sizes.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
        return;
    }

    sizeForm.post(route('catalogs.sizes.store'), {
        preserveScroll: true,
        onSuccess: () => sizeForm.reset(),
    });
}

function submitColor() {
    if (editingType.value === 'colors' && editingId.value) {
        colorForm.put(route('catalogs.colors.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
        return;
    }

    colorForm.post(route('catalogs.colors.store'), {
        preserveScroll: true,
        onSuccess: () => colorForm.reset(),
    });
}

function isEditing(type, id) {
    return editingType.value === type && Number(editingId.value) === Number(id);
}
</script>

<template>
    <Head title="Catálogos" />

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-lg font-semibold text-gray-900">Catálogos</h1>
        </div>

        <div class="rounded-lg bg-white p-4 shadow">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm"
                    :class="tab === 'categories' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    @click="tab = 'categories'"
                >
                    Categorías
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm"
                    :class="tab === 'sizes' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    @click="tab = 'sizes'"
                >
                    Tallas
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm"
                    :class="tab === 'colors' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    @click="tab = 'colors'"
                >
                    Colores
                </button>

                <button
                    v-if="editingId"
                    type="button"
                    class="ml-auto rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200"
                    @click="cancelEdit"
                >
                    Cancelar edición
                </button>
            </div>
        </div>

        <!-- Categorías -->
        <div v-if="tab === 'categories'" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-sm font-semibold text-gray-900">{{ editingType === 'categories' ? 'Editar categoría' : 'Nueva categoría' }}</h2>
                <form class="mt-4 space-y-3" @submit.prevent="submitCategory">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nombre</label>
                        <input
                            v-model="categoryForm.name"
                            type="text"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div v-if="categoryForm.errors.name" class="mt-1 text-sm text-red-600">{{ categoryForm.errors.name }}</div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="categoryForm.active" type="checkbox" class="rounded border-gray-300" />
                        Activo
                    </label>
                    <div v-if="categoryForm.errors.active" class="text-sm text-red-600">{{ categoryForm.errors.active }}</div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                            :disabled="categoryForm.processing"
                        >
                            {{ editingType === 'categories' ? 'Guardar cambios' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="c in categoriesSorted" :key="c.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ c.name }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="c.active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ c.active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200"
                                        :class="isEditing('categories', c.id) ? 'ring-2 ring-gray-800' : ''"
                                        @click="startEdit('categories', c)"
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!categoriesSorted.length">
                                <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-500">Sin categorías.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tallas -->
        <div v-if="tab === 'sizes'" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-sm font-semibold text-gray-900">{{ editingType === 'sizes' ? 'Editar talla' : 'Nueva talla' }}</h2>
                <form class="mt-4 space-y-3" @submit.prevent="submitSize">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nombre</label>
                        <input
                            v-model="sizeForm.name"
                            type="text"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div v-if="sizeForm.errors.name" class="mt-1 text-sm text-red-600">{{ sizeForm.errors.name }}</div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="sizeForm.active" type="checkbox" class="rounded border-gray-300" />
                        Activo
                    </label>
                    <div v-if="sizeForm.errors.active" class="text-sm text-red-600">{{ sizeForm.errors.active }}</div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                            :disabled="sizeForm.processing"
                        >
                            {{ editingType === 'sizes' ? 'Guardar cambios' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="s in sizesSorted" :key="s.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ s.name }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="s.active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ s.active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200"
                                        :class="isEditing('sizes', s.id) ? 'ring-2 ring-gray-800' : ''"
                                        @click="startEdit('sizes', s)"
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!sizesSorted.length">
                                <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-500">Sin tallas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Colores -->
        <div v-if="tab === 'colors'" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-sm font-semibold text-gray-900">{{ editingType === 'colors' ? 'Editar color' : 'Nuevo color' }}</h2>
                <form class="mt-4 space-y-3" @submit.prevent="submitColor">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nombre</label>
                        <input
                            v-model="colorForm.name"
                            type="text"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div v-if="colorForm.errors.name" class="mt-1 text-sm text-red-600">{{ colorForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">HEX (opcional)</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input
                                v-model="colorForm.hex"
                                type="text"
                                placeholder="#FF00AA"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <div
                                class="h-10 w-10 rounded-md border"
                                :style="{ backgroundColor: colorForm.hex || '#ffffff' }"
                                title="Preview"
                            />
                        </div>
                        <div v-if="colorForm.errors.hex" class="mt-1 text-sm text-red-600">{{ colorForm.errors.hex }}</div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="colorForm.active" type="checkbox" class="rounded border-gray-300" />
                        Activo
                    </label>
                    <div v-if="colorForm.errors.active" class="text-sm text-red-600">{{ colorForm.errors.active }}</div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                            :disabled="colorForm.processing"
                        >
                            {{ editingType === 'colors' ? 'Guardar cambios' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">HEX</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="c in colorsSorted" :key="c.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ c.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div class="flex items-center gap-2">
                                        <span>{{ c.hex ?? '-' }}</span>
                                        <span
                                            v-if="c.hex"
                                            class="inline-block h-4 w-4 rounded border"
                                            :style="{ backgroundColor: c.hex }"
                                        />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="c.active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'"
                                    >
                                        {{ c.active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200"
                                        :class="isEditing('colors', c.id) ? 'ring-2 ring-gray-800' : ''"
                                        @click="startEdit('colors', c)"
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!colorsSorted.length">
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">Sin colores.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
