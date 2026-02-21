import {
    STORES,
    countRecords,
    deleteRecord,
    getAllRecords,
    getRecord,
    putRecord,
} from '@/offline/db'

function generateUuid() {
    if (typeof window !== 'undefined' && window.crypto?.randomUUID) {
        return window.crypto.randomUUID()
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

function defaultSalesEndpoint() {
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
        try {
            return window.route('sync.sales.store')
        } catch {
            return '/sync/sales'
        }
    }

    return '/sync/sales'
}

async function putSaleStatus(uuid, status, error = null, conflicts = null) {
    await putRecord(STORES.pendingSalesStatus, {
        uuid,
        status,
        error,
        conflicts: conflicts ?? [],
        updated_at: new Date().toISOString(),
    })
}

export async function queueSale(payload, options = {}) {
    const uuid = options.uuid || generateUuid()

    await putRecord(STORES.pendingSales, {
        uuid,
        payload,
        skus: Array.isArray(options.skus) ? options.skus : [],
        created_at: new Date().toISOString(),
    })

    await putSaleStatus(uuid, 'pending', null, [])

    return uuid
}

async function syncSale(uuid, payload, salesEndpoint) {
    const response = await fetch(salesEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ uuid, payload }),
    })

    const body = await response.json().catch(() => ({}))

    if (response.ok && (body?.status === 'synced' || body?.status === 'already_synced')) {
        await putSaleStatus(uuid, 'synced', null, [])
        return { result: 'synced', uuid }
    }

    if (response.status === 409 || body?.status === 'conflict') {
        await putSaleStatus(
            uuid,
            'conflict',
            body?.message ?? 'Conflicto: producto ya no disponible.',
            body?.conflicts ?? [],
        )

        return { result: 'conflict', uuid, conflicts: body?.conflicts ?? [] }
    }

    await putSaleStatus(uuid, 'pending', body?.message ?? 'Error al sincronizar.', body?.conflicts ?? [])
    return { result: 'error', uuid, message: body?.message ?? 'Error al sincronizar.' }
}

export async function syncAll(options = {}) {
    if (typeof window !== 'undefined' && !window.navigator.onLine) {
        return { synced: 0, conflicts: 0, errors: 0, total: 0 }
    }

    const salesEndpoint = options.salesEndpoint || defaultSalesEndpoint()
    const includeConflicts = options.includeConflicts === true

    const [salesRows, statusRows] = await Promise.all([
        getAllRecords(STORES.pendingSales),
        getAllRecords(STORES.pendingSalesStatus),
    ])

    const statusMap = new Map(statusRows.map((row) => [row.uuid, row]))

    const queue = salesRows.filter((row) => {
        const status = statusMap.get(row.uuid)?.status ?? 'pending'
        if (status === 'synced') return false
        if (status === 'conflict' && !includeConflicts) return false
        return true
    })

    let synced = 0
    let conflicts = 0
    let errors = 0

    for (const row of queue) {
        try {
            const result = await syncSale(row.uuid, row.payload, salesEndpoint)
            if (result.result === 'synced') synced += 1
            if (result.result === 'conflict') conflicts += 1
            if (result.result === 'error') errors += 1
        } catch {
            errors += 1
            await putSaleStatus(row.uuid, 'pending', 'Error de red durante sincronización.', [])
        }
    }

    return {
        synced,
        conflicts,
        errors,
        total: queue.length,
    }
}

export async function retrySale(uuid, options = {}) {
    const row = await getRecord(STORES.pendingSales, uuid)
    if (!row) return { result: 'missing', uuid }

    const salesEndpoint = options.salesEndpoint || defaultSalesEndpoint()
    return syncSale(row.uuid, row.payload, salesEndpoint)
}

export async function removePendingSale(uuid) {
    await Promise.all([
        deleteRecord(STORES.pendingSales, uuid),
        deleteRecord(STORES.pendingSalesStatus, uuid),
    ])
}

export async function listPendingSales() {
    const [salesRows, statusRows] = await Promise.all([
        getAllRecords(STORES.pendingSales),
        getAllRecords(STORES.pendingSalesStatus),
    ])

    const statusMap = new Map(statusRows.map((row) => [row.uuid, row]))

    return salesRows
        .map((row) => {
            const status = statusMap.get(row.uuid)
            return {
                ...row,
                status: status?.status ?? 'pending',
                last_error: status?.error ?? null,
                conflicts: status?.conflicts ?? [],
                updated_at: status?.updated_at ?? null,
            }
        })
        .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
}

export async function getPendingCounts() {
    const [salesTotal, statusRows] = await Promise.all([
        countRecords(STORES.pendingSales),
        getAllRecords(STORES.pendingSalesStatus),
    ])

    const conflicts = statusRows.filter((row) => row.status === 'conflict').length
    const synced = statusRows.filter((row) => row.status === 'synced').length
    const pending = Math.max(0, salesTotal - synced)

    return {
        sales: pending,
        total: pending,
        conflicts,
        synced,
    }
}

export async function getUnsyncedSoldSkus() {
    const rows = await listPendingSales()

    return rows
        .filter((row) => row.status !== 'synced')
        .flatMap((row) => row.skus ?? [])
        .filter(Boolean)
}
