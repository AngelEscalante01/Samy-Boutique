<script setup>
import ImageUploader from '@/Components/Forms/ImageUploader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    product:    { type: Object, required: true },
    categories: { type: Array,  required: true },
    sizes:      { type: Array,  required: true },
    colors:     { type: Array,  required: true },
    can: {
        type: Object,
        default: () => ({ viewPurchasePrice: false, deleteImages: false }),
    },
});

const uploaderRef = ref(null);

const form = useForm({
    sku:            props.product.sku             ?? '',
    name:           props.product.name            ?? '',
    description:    props.product.description     ?? '',
    category_id:    props.product.category?.id   ?? '',
    gender:         props.product.gender          ?? 'unisex',
    size_id:        props.product.size?.id        ?? '',
    color_id:       props.product.color?.id       ?? '',
    purchase_price: props.product.purchase_price  ?? '',
    sale_price:     props.product.sale_price      ?? '',
    status:         props.product.status          ?? 'disponible',
    sold_at:        props.product.sold_at         ?? null,
    images:         [],
});

function onImagesChange(files) {
    form.images = files;
}

const existingImages = computed(() => props.product.images ?? []);

function deleteImage(img) {
    if (!confirm('Eliminar esta imagen permanentemente?')) return;
    router.delete(
        route('products.images.destroy', { product: props.product.id, productImage: img.id }),
        { preserveScroll: true },
    );
}

const profit = computed(() => {
    const sp = Number(form.sale_price     || 0);
    const pp = Number(form.purchase_price || 0);
    if (!sp || !pp) return null;
    const g = sp - pp;
    return { amount: g, pct: Math.round((g / pp) * 100) };
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('products.update', props.product.id), { forceFormData: true });
}

function money(n) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n ?? 0);
}

function imgUrl(img) {
    return img?.url ?? (img?.path ? `/storage/${img.path}` : null);
}

const inputCls = 'mt-1 block w-full rounded-lg border-gray-200 py-2 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400';
</script>

<template>
    <Head title="Editar producto" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-black text-gray-900">Editar producto</h1>
            <p class="text-xs text-gray-400 mt-0.5">{{ product.sku }} - {{ product.name }}</p>
        </div>
        <Link
            :href="route('products.index')"
            class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Volver
        </Link>
    </div>

    <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            <div class="lg:col-span-2 space-y-5">

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Identificacion</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-gray-700">SKU</label>
                            <input v-model="form.sku" type="text" :class="inputCls" />
                            <p v-if="form.errors.sku" class="mt-1 text-xs text-red-600">{{ form.errors.sku }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Nombre <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" :class="inputCls" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold text-gray-700">Descripcion</label>
                            <textarea v-model="form.description" rows="3" :class="inputCls" />
                            <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Clasificacion</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Categoria <span class="text-red-500">*</span></label>
                            <select v-model="form.category_id" :class="inputCls">
                                <option value="" disabled>Selecciona...</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-600">{{ form.errors.category_id }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Genero <span class="text-red-500">*</span></label>
                            <select v-model="form.gender" :class="inputCls">
                                <option value="dama">Dama</option>
                                <option value="caballero">Caballero</option>
                                <option value="unisex">Unisex</option>
                            </select>
                            <p v-if="form.errors.gender" class="mt-1 text-xs text-red-600">{{ form.errors.gender }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Talla <span class="text-red-500">*</span></label>
                            <select v-model="form.size_id" :class="inputCls">
                                <option value="" disabled>Selecciona...</option>
                                <option v-for="s in sizes" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="form.errors.size_id" class="mt-1 text-xs text-red-600">{{ form.errors.size_id }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Color <span class="text-red-500">*</span></label>
                            <select v-model="form.color_id" :class="inputCls">
                                <option value="" disabled>Selecciona...</option>
                                <option v-for="c in colors" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.color_id" class="mt-1 text-xs text-red-600">{{ form.errors.color_id }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Precios</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div v-if="can.viewPurchasePrice">
                            <label class="text-xs font-semibold text-gray-700">Precio compra <span class="text-red-500">*</span></label>
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">$</span>
                                <input v-model="form.purchase_price" type="number" step="0.01" min="0" class="block w-full rounded-lg border-gray-200 py-2 pl-7 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400" />
                            </div>
                            <p v-if="form.errors.purchase_price" class="mt-1 text-xs text-red-600">{{ form.errors.purchase_price }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700">Precio venta <span class="text-red-500">*</span></label>
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">$</span>
                                <input v-model="form.sale_price" type="number" step="0.01" min="0" class="block w-full rounded-lg border-gray-200 py-2 pl-7 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400" />
                            </div>
                            <p v-if="form.errors.sale_price" class="mt-1 text-xs text-red-600">{{ form.errors.sale_price }}</p>
                        </div>
                        <div v-if="can.viewPurchasePrice && profit" class="sm:col-span-2 flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3">
                            <div>
                                <p class="text-xs text-gray-500">Ganancia estimada</p>
                                <p class="text-lg font-black" :class="profit.amount >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                    {{ money(profit.amount) }}
                                    <span class="text-sm font-bold">({{ profit.pct }}%)</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-400">
                        Fotos actuales
                        <span class="ml-1 font-normal text-gray-400">({{ existingImages.length }})</span>
                    </h2>
                    <div v-if="existingImages.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                        <div
                            v-for="img in existingImages"
                            :key="img.id"
                            class="group relative overflow-hidden rounded-xl border border-gray-100"
                        >
                            <img
                                v-if="imgUrl(img)"
                                :src="imgUrl(img)"
                                :alt="product.name"
                                class="aspect-square w-full object-cover"
                            />
                            <div v-else class="aspect-square bg-gray-100" />
                            <button
                                v-if="can.deleteImages"
                                type="button"
                                class="absolute inset-0 flex items-center justify-center bg-red-900/60 opacity-0 transition-opacity group-hover:opacity-100"
                                @click="deleteImage(img)"
                            >
                                <span class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white">Eliminar</span>
                            </button>
                        </div>
                    </div>
                    <p v-else class="rounded-xl bg-gray-50 py-6 text-center text-sm text-gray-400">Sin imagenes aun</p>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-400">Agregar mas fotos</h2>
                    <ImageUploader
                        ref="uploaderRef"
                        :max="10"
                        :error="form.errors.images || form.errors['images.0']"
                        @change="onImagesChange"
                    />
                </div>
            </div>

            <div class="space-y-5">

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-400">Estado del producto</h2>
                    <div class="grid grid-cols-1 gap-1.5">
                        <button
                            v-for="s in ['disponible', 'apartado', 'vendido', 'cancelado']"
                            :key="s"
                            type="button"
                            class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-semibold capitalize transition-all"
                            :class="form.status === s
                                ? 'border-gray-900 bg-gray-900 text-white'
                                : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                            @click="form.status = s"
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :class="{
                                    'bg-emerald-400': s === 'disponible' && form.status !== s,
                                    'bg-amber-400':   s === 'apartado'   && form.status !== s,
                                    'bg-gray-400':    s === 'vendido'    && form.status !== s,
                                    'bg-red-400':     s === 'cancelado'  && form.status !== s,
                                    'bg-white':       form.status === s,
                                }"
                            />
                            {{ s }}
                        </button>
                    </div>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                </div>

                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-5 space-y-2 text-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Resumen</p>
                    <div class="flex justify-between text-gray-600">
                        <span>Precio venta</span>
                        <span class="font-black text-gray-900">{{ money(form.sale_price) }}</span>
                    </div>
                    <div v-if="can.viewPurchasePrice && form.purchase_price" class="flex justify-between text-gray-600">
                        <span>Precio compra</span>
                        <span class="font-medium text-gray-700">{{ money(form.purchase_price) }}</span>
                    </div>
                    <div v-if="can.viewPurchasePrice && profit" class="flex justify-between border-t border-gray-200 pt-2">
                        <span class="text-gray-500">Ganancia</span>
                        <span class="font-black" :class="profit.amount >= 0 ? 'text-emerald-600' : 'text-red-600'">
                            {{ money(profit.amount) }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-xl bg-gray-900 py-3 text-sm font-bold text-white hover:bg-gray-700 disabled:opacity-50"
                    >
                        <span v-if="form.processing" class="flex items-center justify-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg>
                            Guardando...
                        </span>
                        <span v-else>Guardar cambios</span>
                    </button>
                    <Link
                        :href="route('products.index')"
                        class="w-full rounded-xl border border-gray-200 py-3 text-center text-sm font-semibold text-gray-600 hover:bg-gray-50"
                    >Cancelar</Link>
                </div>
            </div>
        </div>
    </form>
</template>