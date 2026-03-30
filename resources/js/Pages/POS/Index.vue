<script setup>
import CheckoutModal from '@/Components/POS/CheckoutModal.vue';
import ProductCard from '@/Components/POS/ProductCard.vue';
import VariantSelectorModal from '@/Components/POS/VariantSelectorModal.vue';
import {
    downloadSnapshot,
    fetchSnapshotMeta,
    getLocalCatalog,
    getLocalMeta,
    getLocalProducts,
    saveSnapshot,
} from '@/offline/snapshot';
import { getPendingCounts, getUnsyncedSoldSkus, queueSale, syncAll } from '@/offline/sync';
import { printSale, validatePrinterReadyForCheckout } from '@/services/printSale';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ q: '', gender: '', category_id: 0 }),
    },
    products: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({
            createSale: false,
            applyDiscountBasic: false,
            applyDiscountHigh: false,
            applyCoupon: false,
        }),
    },
});

const page = usePage();

// Permisos — combina props.can con shared permissions para backward-compat
const canCreateSale = computed(
    () => props.can?.createSale === true || page.props.permissions?.['sales.create'] === true,
);
const canApplyCoupon = computed(
    () => props.can?.applyCoupon === true || page.props.permissions?.['sales.apply_coupon'] === true,
);
const canDiscount = computed(
    () =>
        props.can?.applyDiscountBasic === true ||
        props.can?.applyDiscountHigh === true ||
        page.props.permissions?.['sales.apply_discount_basic'] === true ||
        page.props.permissions?.['sales.apply_discount_high'] === true,
);

// ── Filtros ───────────────────────────────────────────────────────────────────
const q              = ref(props.filters.q          || '');
const genderFilter   = ref(props.filters.gender     || '');
const categoryFilter = ref(props.filters.category_id ? String(props.filters.category_id) : '');

const localProducts = ref([]);
const localCategories = ref([]);
const snapshotMeta = ref(null);
const hasLocalSnapshot = ref(false);
const loadingLocalInventory = ref(false);
const localSoldSkus = ref([]);

function applySearch() {
    if (isOffline.value) {
        refreshLocalProducts();
        return;
    }

    router.get(
        route('pos.index'),
        { q: q.value, gender: genderFilter.value, category_id: categoryFilter.value },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

watch([genderFilter, categoryFilter], () => applySearch());

watch(q, () => {
    if (isOffline.value) refreshLocalProducts();
});

const displayedProducts = computed(() => {
    if (isOffline.value) {
        return localProducts.value;
    }

    return props.products.data ?? [];
});

const displayedCategories = computed(() => {
    if (isOffline.value) {
        return localCategories.value;
    }

    return props.categories ?? [];
});

const lastSnapshotAt = computed(() => snapshotMeta.value?.generated_at ?? null);

// ── Tabs móvil ────────────────────────────────────────────────────────────────
const mobileTab = ref('products');

const variantSelectorOpen = ref(false);
const selectedProductForVariant = ref(null);

// ── Toast ─────────────────────────────────────────────────────────────────────
const toast        = ref(null); // { text, type }
const uiMessage    = ref('');
let toastTimer     = null;
const printAlert = ref(null); // { type: 'error' | 'warning', text: string }

const isOffline = ref(typeof window !== 'undefined' ? !window.navigator.onLine : false);
const pendingCounts = ref({ sales: 0, layaways: 0, total: 0, conflicts: 0 });
const syncingNow = ref(false);
const isAutoPrinting = ref(false);

function showToast(message, type = 'success') {
    clearTimeout(toastTimer);
    toast.value = { text: message, type };
    toastTimer  = setTimeout(() => (toast.value = null), 4000);
}

function showAlert(message, type = 'error') {
    printAlert.value = { text: message, type };
}

function clearAlert() {
    printAlert.value = null;
}

async function refreshPendingCounts() {
    try {
        pendingCounts.value = await getPendingCounts();
    } catch {
        pendingCounts.value = { sales: 0, layaways: 0, total: 0, conflicts: 0 };
    }
}

function normalizeToIsoDate(value) {
    if (!value) return null;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return date.toISOString();
}

function formatSnapshotDate(value) {
    if (!value) return '—';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    return date.toLocaleString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function refreshLocalProducts() {
    localProducts.value = await getLocalProducts({
        q: q.value,
        gender: genderFilter.value,
        category_id: categoryFilter.value,
        excludeSkus: localSoldSkus.value,
    });
}

async function loadLocalInventory() {
    loadingLocalInventory.value = true;

    try {
        const [meta, categories, soldSkus] = await Promise.all([
            getLocalMeta(),
            getLocalCatalog('categories'),
            getUnsyncedSoldSkus(),
        ]);

        snapshotMeta.value = meta;
        hasLocalSnapshot.value = !!meta;
        localCategories.value = categories;
        localSoldSkus.value = soldSkus;

        await refreshLocalProducts();
    } finally {
        loadingLocalInventory.value = false;
    }
}

async function checkSnapshotAndUpdate() {
    const remoteMeta = await fetchSnapshotMeta();
    const localMeta = await getLocalMeta();

    const localVersion = localMeta?.version ?? null;
    const remoteVersion = remoteMeta?.version ?? null;

    if (!localVersion || localVersion !== remoteVersion) {
        const snapshot = await downloadSnapshot();
        await saveSnapshot(snapshot, { precacheImages: true });
        snapshotMeta.value = {
            version: snapshot.version,
            generated_at: snapshot.generated_at,
            products_count: snapshot.products?.length ?? 0,
        };
    } else {
        snapshotMeta.value = localMeta;
    }

    hasLocalSnapshot.value = true;
    localCategories.value = await getLocalCatalog('categories');
    localSoldSkus.value = await getUnsyncedSoldSkus();
    await refreshLocalProducts();
}

async function syncPendingNow({ silentWhenEmpty = false, silentSuccess = false } = {}) {
    if (syncingNow.value || isOffline.value) return;

    syncingNow.value = true;

    try {
        await refreshPendingCounts();

        if ((pendingCounts.value.sales ?? 0) === 0) {
            if (!silentWhenEmpty) showToast('No hay ventas pendientes por sincronizar.');
            return;
        }

        const result = await syncAll({ salesEndpoint: route('sync.sales.store') });
        await refreshPendingCounts();
        localSoldSkus.value = await getUnsyncedSoldSkus();

        if (isOffline.value) {
            await refreshLocalProducts();
        }

        if (result.conflicts > 0) {
            showToast(`Sincronizadas ${result.synced}. ${result.conflicts} en conflicto.`, 'error');
            return;
        }

        if (result.errors > 0) {
            showToast(`Sincronizadas ${result.synced}. ${result.errors} con error.`, 'error');
            return;
        }

        if (!silentSuccess) {
            showToast(`Sincronización completa: ${result.synced} ventas.`);
        }
    } catch {
        showToast('Error al sincronizar ventas pendientes.', 'error');
    } finally {
        syncingNow.value = false;
    }
}

async function handleOnline() {
    isOffline.value = false;
    await checkSnapshotAndUpdate();
    await syncPendingNow({ silentWhenEmpty: true, silentSuccess: true });
}

async function handleOffline() {
    isOffline.value = true;
    await loadLocalInventory();
}

onMounted(async () => {
    await refreshPendingCounts();
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!isOffline.value) {
        await checkSnapshotAndUpdate();
        await syncPendingNow({ silentWhenEmpty: true, silentSuccess: true });
    } else {
        await loadLocalInventory();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
});

// ── Carrito ───────────────────────────────────────────────────────────────────
const cart = ref([]);

function addToCart(product, variant, qty = 1) {
    if (!canCreateSale.value) { showToast('Sin permiso para registrar ventas.', 'error'); return; }
    if (!variant || Number(variant.stock ?? 0) <= 0) {
        showToast('La variante seleccionada no tiene stock.', 'error');
        return;
    }

    const safeQty = Math.max(1, Number(qty ?? 1));
    const stock = Number(variant.stock ?? 0);
    const existing = cart.value.find((item) => item.variant.id === variant.id);

    if (existing) {
        existing.qty = Math.min(stock, existing.qty + safeQty);
        if (existing.qty >= stock) {
            showToast('Cantidad ajustada al stock disponible.', 'error');
        }
    } else {
        cart.value.push({
            product,
            variant,
            qty: Math.min(stock, safeQty),
            discount_type: null,
            discount_value: null,
        });
    }

    if (mobileTab.value === 'products') uiMessage.value = `"${product.name}" agregado.`;
}

function openVariantSelector(product) {
    if (!canCreateSale.value) {
        showToast('Sin permiso para registrar ventas.', 'error');
        return;
    }

    const hasStock = (product?.variants ?? []).some((variant) => Number(variant?.stock ?? 0) > 0);
    if (!hasStock) {
        showToast('Este producto no tiene variantes disponibles.', 'error');
        return;
    }

    selectedProductForVariant.value = product;
    variantSelectorOpen.value = true;
}

function closeVariantSelector() {
    variantSelectorOpen.value = false;
    selectedProductForVariant.value = null;
}

function onVariantConfirm({ variant, qty }) {
    if (!selectedProductForVariant.value) return;
    addToCart(selectedProductForVariant.value, variant, qty);
    closeVariantSelector();
}

function removeFromCart(variantId) {
    cart.value = cart.value.filter((i) => i.variant.id !== variantId);
}

function incrementQty(variantId) {
    const item = cart.value.find((row) => row.variant.id === variantId);
    if (!item) return;
    const maxStock = Number(item.variant.stock ?? 0);
    if (item.qty >= maxStock) {
        showToast('No puedes exceder el stock disponible.', 'error');
        return;
    }
    item.qty += 1;
}

function decrementQty(variantId) {
    const item = cart.value.find((row) => row.variant.id === variantId);
    if (!item) return;
    if (item.qty <= 1) return;
    item.qty -= 1;
}

function setQty(variantId, nextQty) {
    const item = cart.value.find((row) => row.variant.id === variantId);
    if (!item) return;

    const stock = Number(item.variant.stock ?? 0);
    const parsed = Number(nextQty ?? 1);
    const clamped = Math.max(1, Math.min(stock, Number.isFinite(parsed) ? parsed : 1));

    if (parsed > stock) {
        showToast('No puedes exceder el stock disponible.', 'error');
    }

    item.qty = clamped;
}

function clearCart() {
    cart.value = [];
    globalDiscountType.value  = null;
    globalDiscountValue.value = null;
    couponCode.value           = '';
    couponApplied.value        = false;
    previewTotals.value        = null;
    previewError.value         = '';
    clearCustomer();
}

const isInCartVariant = (variantId) => cart.value.some((i) => i.variant.id === variantId);

// ── Descuentos globales ───────────────────────────────────────────────────────
const globalDiscountType  = ref(null);
const globalDiscountValue = ref(null);

// ── Cupón ─────────────────────────────────────────────────────────────────────
const couponCode           = ref('');
const couponApplied        = ref(false);
const previewTotals        = ref(null);
const previewError         = ref('');

// ── Cliente ──────────────────────────────────────────────────────────────────
const selectedCustomer    = ref(null);
const customerQuery       = ref('');
const customerResults     = ref([]);
const searchingCustomers  = ref(false);
let customerSearchTimeout = null;

watch(customerQuery, (v) => {
    if (selectedCustomer.value) return;
    clearTimeout(customerSearchTimeout);
    if (!v.trim()) { customerResults.value = []; return; }
    customerSearchTimeout = setTimeout(() => fetchCustomers(v), 300);
});

async function fetchCustomers(q) {
    searchingCustomers.value = true;
    try {
        const url = route('pos.customers.search') + '?q=' + encodeURIComponent(q);
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        customerResults.value = await res.json();
    } catch {
        customerResults.value = [];
    } finally {
        searchingCustomers.value = false;
    }
}

function selectCustomer(c) {
    selectedCustomer.value = c;
    customerQuery.value    = `${c.name}${c.phone ? ' · ' + c.phone : ''}`;
    customerResults.value  = [];
}

function clearCustomer() {
    selectedCustomer.value = null;
    customerQuery.value    = '';
    customerResults.value  = [];
}

const csrfToken = computed(() =>
    document?.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
);

function money(n) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n ?? 0);
}

function computeDiscountAmount(base, type, value) {
    const b = Number(base ?? 0);
    const v = Number(value ?? 0);
    if (!type || !v || v <= 0) return 0;
    if (type === 'percent') return Number((b * Math.max(0, Math.min(100, v)) / 100).toFixed(2));
    return Number(Math.min(b, Math.max(0, v)).toFixed(2));
}

const subtotal = computed(() =>
    Number(
        cart.value
            .reduce((a, i) => a + (Number(i.variant?.sale_price_effective ?? i.product.sale_price ?? 0) * Number(i.qty ?? 1)), 0)
            .toFixed(2),
    ),
);

const itemDiscountTotal = computed(() => {
    if (!canDiscount.value) return 0;
    return Number(
        cart.value
            .reduce((a, i) => {
                const lineBase = Number(i.variant?.sale_price_effective ?? i.product.sale_price ?? 0) * Number(i.qty ?? 1);
                return a + computeDiscountAmount(lineBase, i.discount_type, i.discount_value);
            }, 0)
            .toFixed(2),
    );
});

const globalDiscountAmount = computed(() => {
    if (!canDiscount.value) return 0;
    return computeDiscountAmount(
        Math.max(0, subtotal.value - itemDiscountTotal.value),
        globalDiscountType.value,
        globalDiscountValue.value,
    );
});

const discountTotal = computed(() =>
    Number((itemDiscountTotal.value + globalDiscountAmount.value).toFixed(2)),
);

const computedTotalWithoutCoupon = computed(() =>
    Number(Math.max(0, subtotal.value - discountTotal.value).toFixed(2)),
);

const couponDiscountTotal = computed(() => {
    if (!couponApplied.value) return 0;
    return Number(Number(previewTotals.value?.coupon_discount_total ?? 0).toFixed(2));
});

const total = computed(() => {
    if (couponApplied.value && previewTotals.value?.total != null) {
        return Number(Number(previewTotals.value.total).toFixed(2));
    }
    return computedTotalWithoutCoupon.value;
});

function buildSalePayload(payments = [], customerId = null, dineroRecibido = null) {
    const items = cart.value.map((item) => {
        const p = {
            variant_id: item.variant.id,
            qty: Number(item.qty ?? 1),
        };
        if (canDiscount.value && item.discount_type && Number(item.discount_value ?? 0) > 0) {
            p.discount_type  = item.discount_type;
            p.discount_value = Number(item.discount_value);
        }
        return p;
    });

    const payload = { customer_id: customerId, items, payments };
    if (dineroRecibido !== null && dineroRecibido !== '') {
        payload.dinero_recibido = Number(dineroRecibido);
    }
    if (canDiscount.value && globalDiscountType.value && Number(globalDiscountValue.value ?? 0) > 0) {
        payload.global_discount_type  = globalDiscountType.value;
        payload.global_discount_value = Number(globalDiscountValue.value);
    }
    if (couponCode.value?.trim()) payload.coupon_code = couponCode.value.trim();
    return payload;
}

async function applyCouponPreview() {
    previewError.value = '';
    if (!couponCode.value?.trim()) { couponApplied.value = false; previewTotals.value = null; return; }
    if (!canApplyCoupon.value) { previewError.value = 'Sin permiso para aplicar cupones.'; return; }

    try {
        const res = await fetch(route('sales.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken.value,
                Accept: 'application/json',
            },
            body: JSON.stringify(buildSalePayload([], selectedCustomer.value?.id ?? null)),
        });
        const data = await res.json();
        if (!res.ok) {
            previewError.value = data?.errors?.coupon_code?.[0] || data?.errors?.discount?.[0] || data?.message || 'Cupón inválido.';
            couponApplied.value = false; previewTotals.value = null; return;
        }
        previewTotals.value = data.totals;
        couponApplied.value = true;
    } catch {
        previewError.value = 'Error de red al validar el cupón.';
        couponApplied.value = false; previewTotals.value = null;
    }
}

watch(
    () => [
        couponCode.value,
        globalDiscountType.value,
        globalDiscountValue.value,
        selectedCustomer.value?.id ?? null,
        cart.value.map((i) => [i.variant.id, i.qty, i.discount_type, i.discount_value]),
    ],
    () => { previewTotals.value = null; couponApplied.value = false; previewError.value = ''; },
    { deep: true },
);

// ── Checkout modal ────────────────────────────────────────────────────────────
const checkoutOpen = ref(false);
const checkoutTotal = ref(0);

const form = useForm({
    customer_id: null,
    items: [],
    global_discount_type: null,
    global_discount_value: null,
    coupon_code: null,
    payments: [],
    dinero_recibido: null,
});

async function resolveServerCheckoutTotal() {
    const fallbackTotal = Number(Number(total.value ?? 0).toFixed(2));

    try {
        const previewPayload = buildSalePayload([], selectedCustomer.value?.id ?? null);
        const res = await fetch(route('sales.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken.value,
                Accept: 'application/json',
            },
            body: JSON.stringify(previewPayload),
        });

        const data = await res.json();
        if (!res.ok || data?.totals?.total == null) {
            return fallbackTotal;
        }

        return Number(Number(data.totals.total).toFixed(2));
    } catch {
        return fallbackTotal;
    }
}

async function openCheckout() {
    if (cart.value.length === 0) { showToast('Agrega productos al carrito.', 'error'); return; }
    if (couponCode.value?.trim() && !couponApplied.value) {
        showToast('Aplica el cupón antes de cobrar.', 'error'); return;
    }

    checkoutTotal.value = await resolveServerCheckoutTotal();
    checkoutOpen.value = true;
}

async function onCheckoutConfirm({ payments, dinero_recibido }) {
    clearAlert();

    const printerCheck = validatePrinterReadyForCheckout();
    if (!printerCheck.ready) {
        showToast(printerCheck.message, 'error');
        showAlert(printerCheck.message, 'warning');
    }

    const payload = buildSalePayload(
        payments,
        selectedCustomer.value?.id ?? null,
        dinero_recibido,
    );

    if (isOffline.value) {
        try {
            const soldSkus = cart.value.map((item) => item.variant?.sku || item.product?.sku).filter(Boolean);

            await queueSale(payload, { skus: soldSkus });
            checkoutOpen.value = false;
            clearCart();
            form.reset();
            await refreshPendingCounts();
            localSoldSkus.value = await getUnsyncedSoldSkus();
            await refreshLocalProducts();
            showToast(`Venta guardada offline. Pendientes: ${pendingCounts.value.sales}.`);
        } catch {
            showToast('No se pudo guardar la venta offline.', 'error');
        }

        return;
    }

    form.customer_id          = payload.customer_id;
    form.items                = payload.items;
    form.global_discount_type  = payload.global_discount_type  ?? null;
    form.global_discount_value = payload.global_discount_value ?? null;
    form.coupon_code           = payload.coupon_code           ?? null;
    form.payments              = payload.payments;
    form.dinero_recibido       = payload.dinero_recibido ?? null;
    form.clearErrors();

    form.post(route('sales.store'), {
        preserveScroll: true,
        onError: () => {
            showToast('No se pudo guardar la venta. Revisa los datos e intenta nuevamente.', 'error');
            showAlert('No se pudo guardar la venta. Verifica los datos de cobro e intenta de nuevo.', 'error');
        },
        onSuccess: async (pageResponse) => {
            const savedSaleId = pageResponse?.props?.flash?.print_sale_id
                ?? page.props?.flash?.print_sale_id
                ?? null;

            checkoutOpen.value = false;
            clearCart();
            showToast('Venta registrada correctamente.');
            form.reset();
            await refreshPendingCounts();

            if (!savedSaleId) {
                showToast('La venta se guardo, pero no se pudo identificar para impresion automatica.', 'error');
                showAlert('Venta guardada, pero no se encontro el folio para imprimir automaticamente.', 'warning');
                return;
            }

            isAutoPrinting.value = true;
            try {
                const printResult = await printSale(savedSaleId);
                if (printResult.ok) {
                    showToast('Ticket enviado a impresora.');
                    clearAlert();
                    return;
                }

                showToast(`Venta guardada, pero fallo la impresion: ${printResult.message}`, 'error');
                showAlert(`Venta guardada, pero no se pudo imprimir el ticket: ${printResult.message}`, 'error');
            } finally {
                isAutoPrinting.value = false;
            }
        },
    });
}
</script>

<template>
    <Head title="POS" />

    <!-- Toast -->
    <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="toast"
            class="fixed right-4 top-4 z-50 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-xl"
            :class="toast.type === 'success' ? 'bg-zinc-900 text-white ring-1 ring-amber-400/30' : 'bg-red-600 text-white'"
        >
            {{ toast.text }}
        </div>
    </transition>

    <!-- Mobile tabs -->
    <div class="sticky top-0 z-20 border-b border-stone-200 bg-white px-4 py-2 lg:hidden">
        <div class="grid grid-cols-2 gap-1 rounded-xl bg-stone-100 p-1">
            <button
                type="button"
                class="rounded-lg py-2 text-sm font-semibold transition-all duration-200"
                :class="mobileTab === 'products' ? 'bg-white shadow-sm text-stone-900' : 'text-stone-500 hover:text-stone-700'"
                @click="mobileTab = 'products'"
            >
                Productos
            </button>
            <button
                type="button"
                class="relative rounded-lg py-2 text-sm font-semibold transition-all duration-200"
                :class="mobileTab === 'cart' ? 'bg-white shadow-sm text-stone-900' : 'text-stone-500 hover:text-stone-700'"
                @click="mobileTab = 'cart'"
            >
                Carrito
                <span v-if="cart.length" class="ml-1.5 inline-flex items-center justify-center rounded-full bg-zinc-900 px-1.5 py-0.5 text-xs font-bold text-white">
                    {{ cart.length }}
                </span>
            </button>
        </div>
    </div>

    <!-- Main layout -->
    <div class="flex h-[calc(100vh-8rem)] overflow-hidden lg:h-auto lg:overflow-visible">

        <!-- LEFT: Catálogo -->
        <section
            class="flex flex-1 flex-col overflow-y-auto"
            :class="mobileTab === 'cart' ? 'hidden lg:flex' : 'flex'"
        >
            <!-- Barra de búsqueda + filtros -->
            <div class="sticky top-0 z-10 space-y-2.5 border-b border-stone-200 bg-white/95 backdrop-blur-sm px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="isOffline ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                    >
                        {{ isOffline ? 'Offline' : 'Online' }}
                    </span>

                    <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700">
                        Pendientes: {{ pendingCounts.sales }}
                    </span>

                    <span
                        v-if="pendingCounts.conflicts > 0"
                        class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700"
                    >
                        Conflictos: {{ pendingCounts.conflicts }}
                    </span>

                    <button
                        v-if="!isOffline"
                        type="button"
                        class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-700 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="syncingNow"
                        @click="syncPendingNow()"
                    >
                        {{ syncingNow ? 'Sincronizando...' : 'Sincronizar ahora' }}
                    </button>

                    <button
                        type="button"
                        class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-700 transition hover:bg-stone-100"
                        @click="router.visit(route('sync.index'))"
                    >
                        Ver pendientes
                    </button>
                </div>

                <div class="rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-xs text-stone-600">
                    Última actualización: {{ formatSnapshotDate(lastSnapshotAt) }}
                    <span v-if="isOffline" class="ml-2 font-semibold text-amber-700">Modo offline: usando inventario guardado</span>
                </div>

                <div
                    v-if="printAlert"
                    class="rounded-lg border px-3 py-2 text-xs"
                    :class="printAlert.type === 'warning'
                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                        : 'border-red-200 bg-red-50 text-red-800'"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold">{{ printAlert.text }}</p>
                        <button
                            type="button"
                            class="shrink-0 rounded px-1 text-[11px] font-bold opacity-80 hover:opacity-100"
                            @click="clearAlert"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>

                <div
                    v-if="isAutoPrinting"
                    class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800"
                >
                    Imprimiendo ticket...
                </div>

                <!-- Search -->
                <form class="flex gap-2" @submit.prevent="applySearch">
                    <div class="relative flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-stone-400"
                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input
                            v-model="q"
                            type="text"
                            placeholder="Buscar por SKU o nombre..."
                            class="w-full rounded-xl border border-stone-300 py-2 pl-9 pr-3 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-amber-300/60 focus:border-amber-400 transition duration-200"
                        />
                    </div>
                    <button
                        type="submit"
                        class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800 transition-colors duration-200"
                    >Buscar</button>
                </form>

                <!-- Género chips -->
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="g in [{ v: '', label: 'Todos' }, { v: 'dama', label: 'Dama' }, { v: 'caballero', label: 'Caballero' }, { v: 'unisex', label: 'Unisex' }]"
                        :key="g.v"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-semibold transition-all duration-200"
                        :class="genderFilter === g.v
                            ? 'bg-zinc-900 text-white shadow-sm'
                            : 'bg-stone-100 text-stone-600 hover:bg-stone-200'"
                        @click="genderFilter = g.v"
                    >{{ g.label }}</button>

                    <!-- Categorías -->
                    <select
                        v-model="categoryFilter"
                        class="rounded-full border border-stone-200 bg-stone-100 py-1 pl-3 pr-7 text-xs font-semibold text-stone-600
                               focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400 transition duration-200"
                    >
                        <option value="">Categoría</option>
                        <option v-for="cat in displayedCategories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
                    </select>
                </div>
            </div>

            <div
                v-if="isOffline && !hasLocalSnapshot && !loadingLocalInventory"
                class="mx-4 mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                Sin conexión. Conéctate una vez para cargar inventario.
            </div>

            <!-- Notificación inline -->
            <div v-if="uiMessage" class="mx-4 mt-3 rounded-xl border border-stone-200 bg-amber-50 px-4 py-2 text-sm text-stone-700">
                {{ uiMessage }}
            </div>

            <!-- Grid de productos -->
            <div class="flex-1 p-4">
                <div
                    v-if="displayedProducts?.length"
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                >
                    <ProductCard
                        v-for="product in displayedProducts"
                        :key="product.id"
                        :product="product"
                        :in-cart-variant-ids="cart.map((item) => item.variant.id)"
                        @select="() => openVariantSelector(product)"
                    />
                </div>
                <div v-else class="flex flex-col items-center justify-center gap-3 py-20 text-center text-gray-400">
                    <svg class="h-14 w-14 text-gray-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                    <p class="text-sm font-medium">Sin productos disponibles</p>
                    <p class="text-xs">Prueba con otro filtro o búsqueda</p>
                </div>

                <!-- Paginación -->
                <div v-if="!isOffline && products.meta?.links?.length" class="mt-6 flex flex-wrap gap-2">
                    <template v-for="link in products.meta.links" :key="link.label">
                        <span
                            v-if="!link.url"
                            class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400"
                            v-html="link.label"
                        />
                        <button
                            v-else
                            type="button"
                            class="rounded-xl border px-3 py-2 text-sm"
                            :class="link.active ? 'border-zinc-800 bg-zinc-900 text-white' : 'border-stone-200 bg-white text-stone-700 hover:bg-stone-50'"
                            v-html="link.label"
                            @click="router.visit(link.url, { preserveScroll: true, preserveState: true })"
                        />
                    </template>
                </div>
            </div>
        </section>

        <!-- RIGHT: Carrito -->
        <aside
            class="flex w-full flex-col overflow-y-auto border-l border-stone-200 bg-white lg:w-[360px] lg:shrink-0"
            :class="mobileTab === 'products' ? 'hidden lg:flex' : 'flex'"
        >
            <!-- Header carrito -->
            <div class="flex items-center justify-between border-b border-stone-200 bg-stone-50/40 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold tracking-wide text-stone-900">Carrito</h2>
                    <p class="text-xs text-stone-400">{{ cart.length }} {{ cart.length === 1 ? 'producto' : 'productos' }}</p>
                </div>
                <button v-if="cart.length" type="button"
                    class="rounded-xl border border-stone-200 px-3 py-1.5 text-xs font-medium text-stone-500 hover:bg-stone-100 transition-colors duration-200"
                    @click="clearCart">
                    Vaciar
                </button>
            </div>

            <!-- Items -->
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2">
                <div v-if="cart.length === 0" class="flex flex-col items-center justify-center gap-2 py-12 text-center text-gray-400">
                    <svg class="h-12 w-12 text-gray-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <p class="text-sm font-medium">Carrito vacío</p>
                    <p class="text-xs">Agrega productos del catálogo</p>
                </div>

                <div
                    v-for="item in cart"
                    :key="item.variant.id"
                    class="rounded-xl border border-stone-200 bg-stone-50 p-3"
                >
                    <div class="flex gap-3">
                        <!-- Miniatura -->
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-gray-200">
                            <img
                                v-if="item.product.images?.[0]"
                                :src="item.product.images[0].url ?? `/storage/${item.product.images[0].path}`"
                                :alt="item.product.name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <svg class="h-6 w-6 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                </svg>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-1">
                                <div class="min-w-0">
                                    <p class="truncate text-xs text-gray-400">{{ item.product.sku }}</p>
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ item.product.name }}</p>
                                    <p class="text-xs text-gray-500">{{ item.variant.size?.name ?? '—' }} · {{ item.variant.color?.name ?? '—' }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="ml-1 shrink-0 text-gray-300 hover:text-red-500"
                                    @click="removeFromCart(item.variant.id)"
                                    title="Quitar"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-sm">
                                <span class="text-gray-400 text-xs">Precio unitario</span>
                                <span class="font-bold text-gray-900">{{ money(item.variant.sale_price_effective) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-gray-500">
                                <span>Cantidad</span>
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" class="rounded border border-stone-200 px-2 py-0.5" @click="decrementQty(item.variant.id)">-</button>
                                    <input
                                        :value="item.qty"
                                        type="number"
                                        min="1"
                                        :max="item.variant.stock"
                                        class="w-14 rounded border border-stone-200 px-1 py-0.5 text-center"
                                        @input="setQty(item.variant.id, Number($event.target.value))"
                                    >
                                    <button type="button" class="rounded border border-stone-200 px-2 py-0.5" @click="incrementQty(item.variant.id)">+</button>
                                </div>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-gray-500">
                                <span>Stock disponible</span>
                                <span>{{ item.variant.stock }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Descuento por ítem -->
                    <div v-if="canDiscount" class="mt-2 flex gap-2">
                        <select
                            v-model="item.discount_type"
                            class="flex-1 rounded-xl border border-stone-300 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400"
                        >
                            <option :value="null">Sin descuento</option>
                            <option value="amount">$ Monto</option>
                            <option value="percent">% Porcentaje</option>
                        </select>
                        <input
                            v-model="item.discount_value"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0"
                            class="w-20 rounded-xl border border-stone-300 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400"
                        />
                    </div>
                </div>
            </div>

            <!-- Totales + acciones -->
            <div class="border-t border-stone-200 bg-stone-50/30 px-4 py-4 space-y-4">
            <!-- Descuento global -->
                <div v-if="canDiscount && cart.length">
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-stone-400">Descuento global</p>
                    <div class="flex gap-2">
                        <select
                            v-model="globalDiscountType"
                            class="flex-1 rounded-xl border border-stone-300 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-amber-400"
                        >
                            <option :value="null">Sin descuento</option>
                            <option value="amount">$ Monto</option>
                            <option value="percent">% Porcentaje</option>
                        </select>
                        <input
                            v-model="globalDiscountValue"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0"
                            class="w-24 rounded-xl border border-stone-300 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-amber-400"
                        />
                    </div>
                </div>

                <!-- Cliente -->
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-stone-400">Cliente</p>
                    <div class="relative">
                        <div class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2">
                            <svg class="h-3.5 w-3.5 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11c0 2.21-1.79 4-4 4s-4-1.79-4-4 1.79-4 4-4 4 1.79 4 4zM2 20c0-4 3.58-7 8-7h.01c.33 0 .66.01.99.04"/>
                            </svg>
                        </div>
                        <input
                            v-model="customerQuery"
                            type="text"
                            placeholder="Buscar por nombre o teléfono..."
                            autocomplete="off"
                            class="w-full rounded-xl border border-stone-300 py-2 pl-8 pr-7 text-sm focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400 transition"
                            :class="selectedCustomer ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-white'"
                            :disabled="!!selectedCustomer"
                        />
                        <button v-if="customerQuery" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-stone-400 hover:text-red-500 transition-colors" @click="clearCustomer">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>

                        <!-- Spinner búsqueda -->
                        <div v-if="searchingCustomers" class="absolute right-2 top-1/2 -translate-y-1/2">
                            <svg class="h-3.5 w-3.5 animate-spin text-stone-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>

                        <!-- Dropdown resultados -->
                        <div v-if="customerResults.length" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-stone-200 bg-white shadow-lg">
                            <button
                                v-for="c in customerResults"
                                :key="c.id"
                                type="button"
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm hover:bg-stone-50 transition-colors"
                                @click="selectCustomer(c)"
                            >
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-amber-400">
                                    {{ c.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-zinc-900">{{ c.name }}</p>
                                    <p class="truncate text-xs text-stone-400">{{ c.phone ?? 'Sin teléfono' }}</p>
                                </div>
                            </button>
                        </div>
                    </div>
                    <p v-if="selectedCustomer" class="mt-1 flex items-center gap-1 text-xs font-medium text-emerald-600">
                        <svg class="h-3 w-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ selectedCustomer.name }}
                    </p>
                </div>

                <!-- Cupón -->
                <div v-if="canApplyCoupon && cart.length">
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-stone-400">Cupón</p>
                    <div class="flex gap-2">
                        <input
                            v-model="couponCode"
                            type="text"
                            placeholder="Código"
                            class="flex-1 rounded-xl border border-stone-300 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400"
                        />
                        <button
                            type="button"
                            class="rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white hover:bg-zinc-800 disabled:opacity-40 transition-colors duration-200"
                            :disabled="!couponCode.trim() || !cart.length"
                            @click="applyCouponPreview"
                        >Aplicar</button>
                    </div>
                    <p v-if="previewError" class="mt-1 text-xs text-red-600">{{ previewError }}</p>
                    <p v-else-if="couponApplied" class="mt-1 text-xs text-emerald-600 font-medium">✓ Cupón aplicado</p>
                </div>

                <!-- Resumen totales -->
                <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm space-y-1.5">
                    <div class="flex justify-between text-gray-500">
                        <span>Subtotal</span><span class="font-medium text-gray-900">{{ money(subtotal) }}</span>
                    </div>
                    <div v-if="discountTotal > 0" class="flex justify-between text-gray-500">
                        <span>Descuentos</span><span class="font-medium text-emerald-600">−{{ money(discountTotal) }}</span>
                    </div>
                    <div v-if="couponApplied && couponDiscountTotal > 0" class="flex justify-between text-gray-500">
                        <span>Cupón</span><span class="font-medium text-emerald-600">−{{ money(couponDiscountTotal) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold text-gray-900">
                        <span>Total</span><span>{{ money(total) }}</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="w-full rounded-xl bg-gray-900 py-3.5 text-sm font-bold tracking-wide text-white transition-colors hover:bg-gray-700 disabled:opacity-40"
                    :disabled="cart.length === 0 || !canCreateSale"
                    @click="openCheckout"
                >
                    Cobrar {{ cart.length ? money(total) : '' }}
                </button>
            </div>
        </aside>
    </div>

    <!-- Checkout modal -->
    <CheckoutModal
        :show="checkoutOpen"
        :total="checkoutTotal"
        :is-processing="form.processing"
        :errors="form.errors"
        @close="checkoutOpen = false"
        @confirm="onCheckoutConfirm"
    />

    <VariantSelectorModal
        :open="variantSelectorOpen"
        :product="selectedProductForVariant"
        :in-cart-variant-ids="cart.map((item) => item.variant.id)"
        @close="closeVariantSelector"
        @confirm="onVariantConfirm"
    />
</template>
