<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    loyalty: {
        enabled: !!props.settings?.loyalty?.enabled,
        type: props.settings?.loyalty?.type ?? 'percent',
        value: props.settings?.loyalty?.value ?? 0,
    },
});

function submit() {
    form.put(route('settings.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Configuración" />

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Configuración</h1>
            <p class="text-sm text-gray-500">Ajustes generales del sistema</p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-sm font-semibold text-gray-900">Fidelidad (5ta compra)</h2>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div class="flex items-center gap-2">
                    <input id="enabled" v-model="form.loyalty.enabled" type="checkbox" class="rounded border-gray-300 text-gray-900 shadow-sm" />
                    <label for="enabled" class="text-sm text-gray-700">Habilitar descuento automático</label>
                </div>
                <div v-if="form.errors['loyalty.enabled']" class="text-sm text-red-600">{{ form.errors['loyalty.enabled'] }}</div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Tipo</label>
                        <select v-model="form.loyalty.type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="percent">Porcentaje</option>
                            <option value="amount">Monto</option>
                        </select>
                        <div v-if="form.errors['loyalty.type']" class="mt-1 text-sm text-red-600">{{ form.errors['loyalty.type'] }}</div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Valor</label>
                        <input
                            v-model="form.loyalty.value"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div v-if="form.errors['loyalty.value']" class="mt-1 text-sm text-red-600">{{ form.errors['loyalty.value'] }}</div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                        :disabled="form.processing"
                    >
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
