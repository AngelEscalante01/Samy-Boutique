import test from 'node:test'
import assert from 'node:assert/strict'
import { normalizePublicImageBase, publicImagePattern, imageFetchRequest, prefetchProductImages } from '../../resources/js/offline/image-cache-policy.js'

const appOrigin = 'http://localhost:18080'
const publicBase = 'https://pub-test.r2.dev'
const key = '/samy/products/42/migrated/8/' + 'a'.repeat(64)
const publicUrl = publicBase + key

test('public HTTPS URL and explicit loopback development hosts are accepted without credentials or signatures', () => {
    assert.equal(normalizePublicImageBase(publicBase + '/'), publicBase)
    for (const value of ['http://example.com', 'https://user:secret@example.com', 'https://example.com?token=x', 'https://example.com#secret', 'https://account.r2.cloudflarestorage.com', 'not a url']) {
        assert.equal(normalizePublicImageBase(value), '')
    }
})

test('local MinIO and showcase product photos follow the same public-only cache policy', () => {
    const localBase = 'http://localhost:19000/samy-public-local'
    assert.equal(normalizePublicImageBase(localBase), localBase)
    assert.equal(normalizePublicImageBase('http://127.0.0.1:19000/samy-public-local'), 'http://127.0.0.1:19000/samy-public-local')
    const image = localBase + '/samy/demo-showcase/products/vestido-marfil.webp'
    assert.equal(imageFetchRequest(image, appOrigin, localBase).options.credentials, 'omit')
    for (const rejected of [localBase + '/samy/demo-showcase/customers/foto.webp', image + '?signature=x', 'http://localhost.attacker.test:19000/samy-public-local', 'http://192.168.1.1/private']) {
        assert.equal(imageFetchRequest(rejected, appOrigin, localBase), null)
    }
})

test('migrated extensionless objects and UUID uploads match the same Workbox rule', () => {
    const rule = publicImagePattern(publicBase)
    assert.equal(rule.test(publicUrl), true)
    assert.equal(rule.test(publicBase + '/samy/products/42/12345678-1234-1234-1234-123456789abc.png'), true)
    for (const value of [publicUrl + '?X-Amz-Signature=secret', publicUrl + '#fragment', publicUrl.replace('pub-test', 'attacker'), publicBase + '/samy/customers/42/photo.png', publicBase + '/samy/products/42/../../private.txt', 'https://pub-test.r2.dev.attacker.com' + key]) {
        assert.equal(rule.test(value), false)
        assert.equal(imageFetchRequest(value, appOrigin, publicBase), null)
    }
})

test('same-origin legacy files remain supported while foreign storage URLs are rejected', () => {
    assert.equal(imageFetchRequest('/storage/products/a.png', appOrigin, publicBase).url, appOrigin + '/storage/products/a.png')
    assert.equal(imageFetchRequest('/subapp/storage/samy/products/42/a.webp', appOrigin, publicBase).options.credentials, 'same-origin')
    assert.equal(imageFetchRequest('https://foreign.example/storage/products/a.png', appOrigin, publicBase), null)
    assert.equal(imageFetchRequest('/storage/documents/private.png', appOrigin, publicBase), null)
    assert.equal(imageFetchRequest(publicUrl, appOrigin, ''), null)
})

test('remote prefetch omits credentials, rejects redirects and deduplicates variants', async () => {
    const calls = []
    const saved = new Map()
    const cache = { put: async (url, response) => saved.set(url, response), keys: async () => [...saved.keys()], delete: async key => saved.delete(key) }
    const result = await prefetchProductImages([{ image_url: publicUrl }, { image_url: publicUrl }, { image_url: 'https://foreign.example/a.png' }], {
        appOrigin, publicBase,
        fetcher: async (url, options) => { calls.push({ url, options }); return new Response('png bytes', { status: 200, headers: { 'content-type': 'image/png' } }) },
        cacheStorage: { open: async name => { assert.equal(name, 'samy-images'); return cache } },
    })
    assert.equal(calls.length, 1)
    assert.deepEqual(calls[0].options, { method: 'GET', mode: 'cors', credentials: 'omit', redirect: 'error' })
    assert.deepEqual(result, { cached: 1, fetched: 1, failed: 0, rejected: 1, deferred: 0 })
    assert.equal(await saved.get(publicUrl).text(), 'png bytes')
})

test('CORS failure, HTTP errors and non-image bodies are not reported as cached', async () => {
    for (const fetcher of [async () => { throw new TypeError('CORS blocked') }, async () => new Response('missing', { status: 404 }), async () => new Response('<html>', { headers: { 'content-type': 'text/html' } })]) {
        const result = await prefetchProductImages([{ image_url: publicUrl }], { appOrigin, publicBase, fetcher, cacheStorage: null })
        assert.equal(result.cached, 0)
        assert.equal(result.failed, 1)
    }
})

test('absent cache API never claims offline readiness and cache volume is bounded', async () => {
    const result = await prefetchProductImages([{ image_url: publicUrl }], {
        appOrigin, publicBase, cacheStorage: null, fetcher: async () => new Response('png', { headers: { 'content-type': 'image/png' } }),
    })
    assert.equal(result.fetched, 1)
    assert.equal(result.cached, 0)
    const large = await prefetchProductImages(Array.from({ length: 302 }, (_, i) => ({ image_url: publicUrl.replace('/42/', `/${i}/`) })), {
        appOrigin, publicBase, cacheStorage: null, fetcher: async () => new Response('png', { headers: { 'content-type': 'image/png' } }),
    })
    assert.equal(large.fetched, 300)
    assert.equal(large.deferred, 2)
})

test('cache cap evicts old snapshot URLs instead of refreshed current images', async () => {
    const saved = new Map(Array.from({ length: 300 }, (_, i) => [publicUrl.replace('/42/', `/${i}/`), null]))
    const cache = { put: async (url, response) => saved.set(url, response), keys: async () => [...saved.keys()], delete: async key => saved.delete(key) }
    const products = [0, ...Array.from({ length: 299 }, (_, i) => i + 300)].map(i => ({ image_url: publicUrl.replace('/42/', `/${i}/`) }))
    const result = await prefetchProductImages(products, {
        appOrigin, publicBase, cacheStorage: { open: async () => cache },
        fetcher: async () => new Response('png', { headers: { 'content-type': 'image/png' } }),
    })
    assert.equal(result.cached, 300)
    assert.equal(saved.size, 300)
    for (const product of products) assert.ok(saved.has(product.image_url))
})
