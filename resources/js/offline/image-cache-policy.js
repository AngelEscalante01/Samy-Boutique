export const PRODUCT_IMAGE_CACHE = 'samy-images'

// This value is public build configuration, never an S3 endpoint or signed URL.
export function normalizePublicImageBase(value) {
    if (!value || !String(value).trim()) return ''
    try {
        const url = new URL(String(value).trim())
        const localDevelopment = url.protocol === 'http:' && ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname)
        if ((url.protocol !== 'https:' && !localDevelopment) || url.username || url.password || url.search || url.hash) return ''
        if (url.hostname.endsWith('.r2.cloudflarestorage.com')) return ''
        return url.origin + url.pathname.replace(/\/+$/, '')
    } catch {
        return ''
    }
}

export function publicImagePattern(base) {
    const normalized = normalizePublicImageBase(base)
    if (!normalized) return null
    const escaped = normalized.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    // Public product objects only. No query strings (in particular signed URLs),
    // encoded path separators, credentials, or customer/document objects.
    return new RegExp(`^${escaped}/samy/(?:products/[0-9]+/(?:migrated/[0-9]+/[a-f0-9]{64}|[a-f0-9-]{36}\\.(?:png|jpe?g|webp|gif))|demo-showcase/products/[a-z0-9-]+\\.webp)$`, 'i')
}

export function imageFetchRequest(value, appOrigin, publicBase = '') {
    try {
        const url = new URL(value, appOrigin)
        if (url.username || url.password || url.search || url.hash) return null
        const local = url.origin === new URL(appOrigin).origin
            && /\/storage\/(?:samy\/)?products\/.+\.(?:png|jpe?g|webp|gif)$/i.test(url.pathname)
        const remote = publicImagePattern(publicBase)?.test(url.href) === true
        if (!local && !remote) return null
        return {
            url: url.href,
            options: { method: 'GET', mode: 'cors', credentials: remote ? 'omit' : 'same-origin', redirect: 'error' },
        }
    } catch {
        return null
    }
}

export async function prefetchProductImages(products, { appOrigin, publicBase, fetcher = fetch, cacheStorage = globalThis.caches, batchSize = 20 }) {
    const results = { cached: 0, fetched: 0, failed: 0, rejected: 0, deferred: 0 }
    const requests = new Map()
    for (const product of products) {
        if (!product.image_url) continue
        const request = imageFetchRequest(product.image_url, appOrigin, publicBase)
        if (request) requests.set(request.url, request)
        else results.rejected++
    }
    const cache = cacheStorage ? await cacheStorage.open(PRODUCT_IMAGE_CACHE).catch(() => null) : null
    const list = [...requests.values()].slice(0, 300)
    results.deferred = requests.size - list.length
    const concurrency = Math.max(1, Math.min(20, Number(batchSize) || 20))
    for (let index = 0; index < list.length; index += concurrency) {
        await Promise.all(list.slice(index, index + concurrency).map(async ({ url, options }) => {
            try {
                const response = await fetcher(url, options)
                if (response.status !== 200 || !/^image\/(?:png|jpeg|webp|gif)(?:;|$)/i.test(response.headers.get('content-type') ?? '')) {
                    throw new Error('Invalid image response')
                }
                results.fetched++
                if (cache) {
                    await cache.put(url, response.clone())
                    results.cached++
                }
            } catch {
                results.failed++
            }
        }))
    }
    if (cache) {
        const entries = await cache.keys()
        const current = new Set(list.map(({ url }) => url))
        const stale = entries.filter(request => !current.has(typeof request === 'string' ? request : request.url))
        for (const request of stale.slice(0, Math.max(0, entries.length - 300))) await cache.delete(request)
    }
    return results
}
