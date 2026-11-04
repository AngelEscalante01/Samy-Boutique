import { ref } from 'vue';

function toPositiveNumber(value, fallback = 1) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) return fallback;
    return parsed;
}

function sanitizeDiscountType(value) {
    return value === 'amount' || value === 'percent' ? value : null;
}

function sanitizeDiscountValue(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) return null;
    return parsed;
}

function serializeProduct(product) {
    if (!product || typeof product !== 'object') return null;

    return {
        id: Number(product.id ?? 0) || null,
        sku: String(product.sku ?? ''),
        name: String(product.name ?? ''),
        sale_price: Number(product.sale_price ?? 0),
        images: Array.isArray(product.images) ? product.images.slice(0, 1) : [],
    };
}

function serializeVariant(variant) {
    if (!variant || typeof variant !== 'object') return null;

    return {
        id: Number(variant.id ?? 0) || null,
        sku: String(variant.sku ?? ''),
        stock: Number(variant.stock ?? 0),
        sale_price: Number(variant.sale_price ?? 0),
        sale_price_effective: Number(variant.sale_price_effective ?? variant.sale_price ?? 0),
        size: variant.size ? { id: Number(variant.size.id ?? 0) || null, name: String(variant.size.name ?? '') } : null,
        color: variant.color ? { id: Number(variant.color.id ?? 0) || null, name: String(variant.color.name ?? '') } : null,
    };
}

function sanitizeCustomer(customer) {
    if (!customer || typeof customer !== 'object') return null;

    const id = Number(customer.id ?? 0);
    if (!Number.isFinite(id) || id <= 0) return null;

    return {
        id,
        name: String(customer.name ?? ''),
        phone: customer.phone ? String(customer.phone) : null,
        email: customer.email ? String(customer.email) : null,
    };
}

export function usePersistentCart(storageKey = 'samy_pos_cart') {
    const isRestoring = ref(false);

    // addToCart: inserta o incrementa una línea del carrito y respeta stock
    function addToCart(cartItems, nextItem) {
        if (!Array.isArray(cartItems) || !nextItem?.variant?.id) {
            return { ok: false, reason: 'invalid_item' };
        }

        const stock = Math.max(0, Number(nextItem.variant.stock ?? 0));
        if (stock <= 0) {
            return { ok: false, reason: 'no_stock' };
        }

        const qtyToAdd = toPositiveNumber(nextItem.qty, 1);
        const existing = cartItems.find((item) => Number(item?.variant?.id) === Number(nextItem.variant.id));

        if (existing) {
            existing.qty = Math.min(stock, toPositiveNumber(existing.qty, 1) + qtyToAdd);
            return { ok: true, action: 'updated', item: existing };
        }

        const item = {
            product: nextItem.product,
            variant: nextItem.variant,
            qty: Math.min(stock, qtyToAdd),
            discount_type: sanitizeDiscountType(nextItem.discount_type),
            discount_value: sanitizeDiscountValue(nextItem.discount_value),
        };

        cartItems.push(item);
        return { ok: true, action: 'created', item };
    }

    // removeFromCart: elimina una línea por id de variante
    function removeFromCart(cartItems, variantId) {
        if (!Array.isArray(cartItems)) return [];
        return cartItems.filter((item) => Number(item?.variant?.id) !== Number(variantId));
    }

    function saveCart(state) {
        if (typeof window === 'undefined') return false;

        const cart = Array.isArray(state?.cart)
            ? state.cart
                .filter((item) => Number(item?.variant?.id) > 0)
                .map((item) => ({
                    product: serializeProduct(item.product),
                    variant: serializeVariant(item.variant),
                    qty: toPositiveNumber(item.qty, 1),
                    discount_type: sanitizeDiscountType(item.discount_type),
                    discount_value: sanitizeDiscountValue(item.discount_value),
                }))
            : [];

        const payload = {
            version: 1,
            updated_at: new Date().toISOString(),
            cart,
            selectedCustomer: sanitizeCustomer(state?.selectedCustomer),
            globalDiscountType: sanitizeDiscountType(state?.globalDiscountType),
            globalDiscountValue: sanitizeDiscountValue(state?.globalDiscountValue),
            couponCode: String(state?.couponCode ?? ''),
            couponApplied: Boolean(state?.couponApplied),
            searchQuery: String(state?.searchQuery ?? ''),
            previewTotals: state?.previewTotals && typeof state.previewTotals === 'object'
                ? state.previewTotals
                : null,
        };

        try {
            window.localStorage.setItem(storageKey, JSON.stringify(payload));
            return true;
        } catch {
            return false;
        }
    }

    // loadCart: restaura estado de carrito con saneo y control de JSON corrupto
    function loadCart(options = {}) {
        if (typeof window === 'undefined') {
            return {
                state: null,
                invalidItems: 0,
                corrupted: false,
            };
        }

        const resolver = typeof options.resolveVariantById === 'function'
            ? options.resolveVariantById
            : null;

        const saved = window.localStorage.getItem(storageKey);
        if (!saved) {
            return {
                state: null,
                invalidItems: 0,
                corrupted: false,
            };
        }

        isRestoring.value = true;

        try {
            let parsed;
            try {
                parsed = JSON.parse(saved);
            } catch {
                clearCart();
                return {
                    state: null,
                    invalidItems: 0,
                    corrupted: true,
                };
            }

            if (!parsed || typeof parsed !== 'object') {
                clearCart();
                return {
                    state: null,
                    invalidItems: 0,
                    corrupted: true,
                };
            }

            const rawCart = Array.isArray(parsed.cart) ? parsed.cart : [];
            const restoredCart = [];
            let invalidItems = 0;

            for (const rawItem of rawCart) {
                if (!rawItem || typeof rawItem !== 'object') {
                    invalidItems += 1;
                    continue;
                }

                const variantId = Number(rawItem?.variant?.id ?? 0);
                if (!Number.isFinite(variantId) || variantId <= 0) {
                    invalidItems += 1;
                    continue;
                }

                const resolved = resolver ? resolver(variantId) : null;
                const product = resolved?.product ?? rawItem.product ?? null;
                const variant = resolved?.variant ?? rawItem.variant ?? null;

                if (!product || !variant) {
                    invalidItems += 1;
                    continue;
                }

                const stock = Number(variant.stock ?? 0);
                if (!Number.isFinite(stock) || stock <= 0) {
                    invalidItems += 1;
                    continue;
                }

                restoredCart.push({
                    product,
                    variant,
                    qty: Math.min(stock, toPositiveNumber(rawItem.qty, 1)),
                    discount_type: sanitizeDiscountType(rawItem.discount_type),
                    discount_value: sanitizeDiscountValue(rawItem.discount_value),
                });
            }

            const state = {
                cart: restoredCart,
                selectedCustomer: sanitizeCustomer(parsed.selectedCustomer),
                globalDiscountType: sanitizeDiscountType(parsed.globalDiscountType),
                globalDiscountValue: sanitizeDiscountValue(parsed.globalDiscountValue),
                couponCode: String(parsed.couponCode ?? ''),
                couponApplied: Boolean(parsed.couponApplied),
                searchQuery: String(parsed.searchQuery ?? ''),
                previewTotals: parsed.previewTotals && typeof parsed.previewTotals === 'object'
                    ? parsed.previewTotals
                    : null,
            };

            return {
                state,
                invalidItems,
                corrupted: false,
            };
        } finally {
            isRestoring.value = false;
        }
    }

    // clearCart: elimina el estado persistido por completo
    function clearCart() {
        if (typeof window === 'undefined') return;
        window.localStorage.removeItem(storageKey);
    }

    return {
        isRestoring,
        loadCart,
        saveCart,
        clearCart,
        addToCart,
        removeFromCart,
    };
}
