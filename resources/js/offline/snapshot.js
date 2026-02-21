import {
    STORES,
    clearStore,
    getAllRecords,
    getRecord,
    putRecord,
} from '@/offline/db'

function resolveEndpoint(name, fallback) {
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
        try {
            return window.route(name)
        } catch {
            return fallback
        }
    }

    return fallback
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

export async function fetchSnapshotMeta() {
    const url = resolveEndpoint('offline.snapshot.meta', '/offline/snapshot/meta')
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    })

    if (!response.ok) {
        throw new Error('No se pudo obtener metadata del snapshot.')
    }

    return response.json()
}

export async function downloadSnapshot() {
    const url = resolveEndpoint('offline.snapshot', '/offline/snapshot')
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    })

    if (!response.ok) {
        throw new Error('No se pudo descargar snapshot offline.')
    }

    return response.json()
}

async function precacheImagesInBatches(products, batchSize = 20) {
    const urls = products
        .map((p) => p.image_url)
        .filter(Boolean)

    for (let index = 0; index < urls.length; index += batchSize) {
        const batch = urls.slice(index, index + batchSize)

        await Promise.allSettled(
            batch.map((url) => fetch(url, { method: 'GET', credentials: 'same-origin' })),
        )

        await new Promise((resolve) => setTimeout(resolve, 30))
    }
}

export async function saveSnapshot(data, options = {}) {
    const products = Array.isArray(data?.products) ? data.products : []
    const catalogs = data?.catalogs ?? {}

    await clearStore(STORES.products)

    for (const product of products) {
        await putRecord(STORES.products, {
            ...product,
            images: product.image_url ? [{ url: product.image_url, path: null }] : [],
        })
    }

    await putRecord(STORES.catalogs, {
        type: 'categories',
        items: Array.isArray(catalogs.categories) ? catalogs.categories : [],
    })

    await putRecord(STORES.catalogs, {
        type: 'sizes',
        items: Array.isArray(catalogs.sizes) ? catalogs.sizes : [],
    })

    await putRecord(STORES.catalogs, {
        type: 'colors',
        items: Array.isArray(catalogs.colors) ? catalogs.colors : [],
    })

    await putRecord(STORES.snapshotMeta, {
        key: 'current',
        version: data?.version ?? null,
        generated_at: data?.generated_at ?? null,
        products_count: products.length,
    })

    if (options.precacheImages !== false) {
        await precacheImagesInBatches(products)
    }

    return {
        version: data?.version ?? null,
        generated_at: data?.generated_at ?? null,
        products_count: products.length,
    }
}

export async function getLocalProducts(filters = {}) {
    const rows = await getAllRecords(STORES.products)

    const query = String(filters.q ?? '').trim().toLowerCase()
    const gender = String(filters.gender ?? '').trim().toLowerCase()
    const categoryId = String(filters.category_id ?? '').trim()
    const hiddenSkus = new Set((filters.excludeSkus ?? []).map((sku) => String(sku)))

    return rows
        .filter((p) => p.status === 'disponible')
        .filter((p) => !hiddenSkus.has(String(p.sku)))
        .filter((p) => {
            if (!query) return true
            const haystack = `${p.sku ?? ''} ${p.name ?? ''}`.toLowerCase()
            return haystack.includes(query)
        })
        .filter((p) => (!gender ? true : String(p.gender ?? '').toLowerCase() === gender))
        .filter((p) => {
            if (!categoryId) return true
            return String(p.category?.id ?? '') === categoryId
        })
        .sort((a, b) => Number(b.id || 0) - Number(a.id || 0))
}

export async function getLocalCatalog(type) {
    const row = await getRecord(STORES.catalogs, type)
    return row?.items ?? []
}

export async function getLocalMeta() {
    const row = await getRecord(STORES.snapshotMeta, 'current')

    return row
        ? {
            version: row.version ?? null,
            generated_at: row.generated_at ?? null,
            products_count: row.products_count ?? 0,
        }
        : null
}
