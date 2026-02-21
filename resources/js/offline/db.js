const DB_NAME = 'samy_boutique_offline'
const DB_VERSION = 2

export const STORES = {
    snapshotMeta: 'snapshot_meta',
    products: 'products',
    catalogs: 'catalogs',
    pendingSales: 'pending_sales',
    pendingSalesStatus: 'pending_sales_status',
}

let dbPromise = null

function openDB() {
    if (dbPromise) return dbPromise

    dbPromise = new Promise((resolve, reject) => {
        const request = window.indexedDB.open(DB_NAME, DB_VERSION)

        request.onerror = () => reject(request.error)
        request.onsuccess = () => resolve(request.result)

        request.onupgradeneeded = () => {
            const db = request.result

            if (!db.objectStoreNames.contains(STORES.snapshotMeta)) {
                db.createObjectStore(STORES.snapshotMeta, { keyPath: 'key' })
            }

            if (!db.objectStoreNames.contains(STORES.products)) {
                const productsStore = db.createObjectStore(STORES.products, { keyPath: 'sku' })
                productsStore.createIndex('status', 'status', { unique: false })
                productsStore.createIndex('updated_at', 'updated_at', { unique: false })
            }

            if (!db.objectStoreNames.contains(STORES.catalogs)) {
                db.createObjectStore(STORES.catalogs, { keyPath: 'type' })
            }

            if (!db.objectStoreNames.contains(STORES.pendingSales)) {
                const salesStore = db.createObjectStore(STORES.pendingSales, { keyPath: 'uuid' })
                salesStore.createIndex('created_at', 'created_at', { unique: false })
            }

            if (!db.objectStoreNames.contains(STORES.pendingSalesStatus)) {
                const statusStore = db.createObjectStore(STORES.pendingSalesStatus, { keyPath: 'uuid' })
                statusStore.createIndex('status', 'status', { unique: false })
                statusStore.createIndex('updated_at', 'updated_at', { unique: false })
            }
        }
    })

    return dbPromise
}

function run(storeName, mode, operation) {
    return openDB().then((db) =>
        new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, mode)
            const store = tx.objectStore(storeName)

            let request
            try {
                request = operation(store)
            } catch (error) {
                reject(error)
                return
            }

            tx.oncomplete = () => resolve(request?.result)
            tx.onerror = () => reject(tx.error)
            tx.onabort = () => reject(tx.error)
        }),
    )
}

export function putRecord(storeName, value) {
    return run(storeName, 'readwrite', (store) => store.put(value))
}

export function getRecord(storeName, key) {
    return run(storeName, 'readonly', (store) => store.get(key))
}

export function getAllRecords(storeName) {
    return run(storeName, 'readonly', (store) => store.getAll())
}

export function deleteRecord(storeName, key) {
    return run(storeName, 'readwrite', (store) => store.delete(key))
}

export function clearStore(storeName) {
    return run(storeName, 'readwrite', (store) => store.clear())
}

export function countRecords(storeName) {
    return run(storeName, 'readonly', (store) => store.count())
}
