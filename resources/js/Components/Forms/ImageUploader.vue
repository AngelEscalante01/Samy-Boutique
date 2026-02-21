<script setup>
import { ref } from 'vue';

const props = defineProps({
    error: { type: String, default: null },
    max:   { type: Number, default: 10 },
});

const emit = defineEmits(['change']);

const previews  = ref([]);
const dragging  = ref(false);
const inputRef  = ref(null);

function processFiles(files) {
    const existing = previews.value.length;
    const allowed  = props.max - existing;
    if (allowed <= 0) return;

    const newFiles = Array.from(files).slice(0, allowed);
    const newPreviews = newFiles.map((file) => ({
        file,
        url:  URL.createObjectURL(file),
        name: file.name,
    }));
    previews.value = [...previews.value, ...newPreviews];
    emit('change', previews.value.map((p) => p.file));
}

function onInput(e) {
    processFiles(e.target.files ?? []);
    if (inputRef.value) inputRef.value.value = '';
}

function onDrop(e) {
    dragging.value = false;
    processFiles(e.dataTransfer?.files ?? []);
}

function remove(idx) {
    URL.revokeObjectURL(previews.value[idx].url);
    previews.value.splice(idx, 1);
    emit('change', previews.value.map((p) => p.file));
}

function move(idx, dir) {
    const next = idx + dir;
    if (next < 0 || next >= previews.value.length) return;
    const tmp = previews.value[idx];
    previews.value[idx]  = previews.value[next];
    previews.value[next] = tmp;
    emit('change', previews.value.map((p) => p.file));
}

function openPicker() {
    inputRef.value?.click();
}

// Exponer reset
function reset() {
    previews.value.forEach((p) => URL.revokeObjectURL(p.url));
    previews.value = [];
}
defineExpose({ reset });
</script>

<template>
    <div class="space-y-3">
        <!-- Drop zone -->
        <div
            class="relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors"
            :class="dragging
                ? 'border-gray-400 bg-gray-50'
                : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
            @click="openPicker"
        >
            <input
                ref="inputRef"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
                @change="onInput"
            />
            <svg class="h-10 w-10 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <p class="text-sm font-medium text-gray-600">
                <span class="text-gray-900">Arrastra imágenes</span> o haz clic para seleccionar
            </p>
            <p class="text-xs text-gray-400">JPG, PNG, WEBP — máx {{ max }} fotos ({{ previews.length }}/{{ max }})</p>
        </div>

        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>

        <!-- Previews grid -->
        <div v-if="previews.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
            <div
                v-for="(p, idx) in previews"
                :key="p.url"
                class="group relative overflow-hidden rounded-xl border border-gray-100 bg-gray-50"
            >
                <img :src="p.url" :alt="p.name" class="aspect-square w-full object-cover" />

                <!-- Overlay acciones -->
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-gray-900/50 opacity-0 transition-opacity group-hover:opacity-100">
                    <div class="flex gap-1">
                        <button
                            type="button"
                            class="rounded-lg bg-white/90 px-2 py-1 text-xs font-bold text-gray-700 hover:bg-white disabled:opacity-30"
                            :disabled="idx === 0"
                            @click.stop="move(idx, -1)"
                            title="Mover izquierda"
                        >←</button>
                        <button
                            type="button"
                            class="rounded-lg bg-white/90 px-2 py-1 text-xs font-bold text-gray-700 hover:bg-white disabled:opacity-30"
                            :disabled="idx === previews.length - 1"
                            @click.stop="move(idx, 1)"
                            title="Mover derecha"
                        >→</button>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-red-500 px-3 py-1 text-xs font-semibold text-white hover:bg-red-600"
                        @click.stop="remove(idx)"
                    >Quitar</button>
                </div>

                <!-- Badge posición -->
                <span v-if="idx === 0" class="absolute left-1 top-1 rounded-full bg-gray-900 px-1.5 py-0.5 text-xs font-bold text-white">Principal</span>
            </div>
        </div>
    </div>
</template>
