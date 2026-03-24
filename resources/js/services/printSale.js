function hasAndroidPrinterBridge() {
    return typeof window !== 'undefined' && typeof window.AndroidPrinter !== 'undefined';
}

function canPrintTicket() {
    return hasAndroidPrinterBridge() && typeof window.AndroidPrinter?.printTicket === 'function';
}

function csrfToken() {
    return document?.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function postPrintAudit(saleId, payload) {
    if (!saleId) return;

    try {
        await fetch(route('sales.print-audit.store', saleId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
    } catch (error) {
        // No interrumpe caja ni UX de cobro si auditoria falla.
        console.warn('[PRINT] No se pudo guardar auditoria:', error);
    }
}

function normalizeConnectionMethod(value) {
    const connection = String(value || '').toLowerCase();

    if (connection === 'bluetooth' || connection === 'usb' || connection === 'wifi' || connection === 'network') {
        return connection;
    }

    return 'webview';
}

function safeDateString(value) {
    if (!value) return new Date().toISOString();

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return new Date().toISOString();

    return date.toISOString();
}

/**
 * Mapea una venta de Laravel al esquema uniforme esperado por Android/Kotlin.
 * @param {Record<string, any>} sale
 * @returns {{storeName:string,folio:string,date:string,client:string,items:Array<{qty:number,name:string,color:string,size:string,price:number}>,subtotal:number,total:number,paymentMethod:string,cashReceived:number,change:number,finalMessage:string}}
 */
export function mapSaleToTicketSchema(sale) {
    const items = Array.isArray(sale?.items)
        ? sale.items.map((item) => ({
            qty: Number(item?.qty ?? item?.quantity ?? 1),
            name: String(item?.name ?? item?.product?.name ?? 'Producto'),
            color: String(item?.color ?? item?.variant?.color?.name ?? item?.product?.color?.name ?? 'N/A'),
            size: String(item?.size ?? item?.variant?.size?.name ?? item?.product?.size?.name ?? 'N/A'),
            price: Number(item?.price ?? item?.unit_price ?? 0),
        }))
        : [];

    return {
        storeName: String(sale?.storeName ?? sale?.store?.name ?? 'Samy Boutique'),
        folio: String(sale?.folio ?? sale?.id ?? ''),
        date: safeDateString(sale?.date ?? sale?.created_at),
        client: String(sale?.client ?? sale?.customer?.name ?? 'Publico General'),
        items,
        subtotal: Number(sale?.subtotal ?? 0),
        total: Number(sale?.total ?? 0),
        paymentMethod: String(sale?.paymentMethod ?? sale?.payment_method ?? 'unknown'),
        cashReceived: Number(sale?.cashReceived ?? sale?.cash_received ?? 0),
        change: Number(sale?.change ?? 0),
        finalMessage: String(sale?.finalMessage ?? 'Gracias por su compra.'),
    };
}

/**
 * Envia datos al bridge AndroidPrinter.printTicket.
 * @param {Record<string, any>} data
 * @returns {{ok:boolean,message:string}}
 */
export function imprimirVenta(data) {
    if (!canPrintTicket()) {
        return { ok: false, message: 'Impresora Android no disponible en este dispositivo.' };
    }

    try {
        const payload = mapSaleToTicketSchema(data);
        window.AndroidPrinter.printTicket(JSON.stringify(payload));
        return { ok: true, message: 'Ticket enviado a la impresora.' };
    } catch (error) {
        return { ok: false, message: error?.message || 'No se pudo enviar el ticket a Android.' };
    }
}

/**
 * Abre configuracion nativa de impresora en Android si esta disponible.
 * @returns {{ok:boolean,message:string}}
 */
export function abrirConfiguracion() {
    console.info('[PRINT] Intentando abrir configuracion de impresora...');

    if (!hasAndroidPrinterBridge()) {
        console.warn('[PRINT] AndroidPrinter no disponible en window.');
        return { ok: false, message: 'No se detecto la app Android con integracion de impresora.' };
    }

    const openPrinterSettingsFn = window.AndroidPrinter?.openPrinterSettings;
    const openSettingsFn = window.AndroidPrinter?.openSettings;

    if (typeof openPrinterSettingsFn !== 'function' && typeof openSettingsFn !== 'function') {
        console.warn('[PRINT] No existe openPrinterSettings/openSettings en AndroidPrinter.');
        return { ok: false, message: 'La funcion de configuracion de impresora no esta disponible en la app Android.' };
    }

    try {
        if (typeof openPrinterSettingsFn === 'function') {
            console.info('[PRINT] Usando AndroidPrinter.openPrinterSettings().');
            openPrinterSettingsFn.call(window.AndroidPrinter);
        } else {
            console.info('[PRINT] Usando fallback AndroidPrinter.openSettings().');
            openSettingsFn.call(window.AndroidPrinter);
        }

        return { ok: true, message: 'Abriendo configuracion de impresora.' };
    } catch (error) {
        console.error('[PRINT] Error al abrir configuracion de impresora:', error);
        return { ok: false, message: error?.message || 'No se pudo abrir la configuracion.' };
    }
}

export function openPrinterSettings() {
    return abrirConfiguracion();
}

/**
 * Consulta estado de impresora nativa.
 * Esperado: READY.
 */
export function getPrinterStatus() {
    if (!hasAndroidPrinterBridge() || typeof window.AndroidPrinter?.getStatus !== 'function') {
        return { ok: false, status: 'BRIDGE_UNAVAILABLE' };
    }

    try {
        const status = String(window.AndroidPrinter.getStatus() || '').toUpperCase();
        return { ok: true, status: status || 'UNKNOWN' };
    } catch {
        return { ok: false, status: 'ERROR' };
    }
}

/**
 * Validacion previa para checkout. Nunca bloquea venta.
 */
export function validatePrinterReadyForCheckout() {
    const state = getPrinterStatus();

    if (!state.ok) {
        return {
            ready: false,
            message: 'No se detecto impresora Android. La venta se guardara sin impresion automatica.',
            status: state.status,
        };
    }

    if (state.status !== 'READY') {
        return {
            ready: false,
            message: `Impresora no lista (${state.status}). La venta se guardara, pero puede no imprimirse automaticamente.`,
            status: state.status,
        };
    }

    return { ready: true, message: '', status: state.status };
}

function ensurePrintAuditHook() {
    if (typeof window === 'undefined' || window.__printAuditHookReady) {
        return;
    }

    window.__printAuditHookReady = true;
    window.printAuditHook = async function printAuditHook(resultPayload) {
        const meta = resultPayload?.meta && typeof resultPayload.meta === 'object' ? resultPayload.meta : {};
        const saleId = meta.sale_id || meta.saleId || window.__lastPrintedSaleId || null;
        if (!saleId) return;

        const status = String(resultPayload?.status || '').toLowerCase();
        const success = status === 'success';

        await postPrintAudit(saleId, {
            ticket_type: String(meta.ticket_type || 'sale'),
            print_attempted: true,
            print_success: success,
            error_message: success ? null : (resultPayload?.message || 'Fallo reportado por Android.'),
            connection_method: normalizeConnectionMethod(meta.connection_method),
            printed_at: new Date().toISOString(),
            meta,
        });
    };
}

async function fetchTicketData(saleId) {
    const endpoint = route('sales.print-data', saleId);

    const response = await fetch(endpoint, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        const message = payload?.message || 'No fue posible obtener los datos del ticket.';
        throw new Error(message);
    }

    if (!payload || typeof payload !== 'object') {
        throw new Error('El ticket recibido no tiene un formato valido.');
    }

    return payload;
}

/**
 * Obtiene el ticket del backend y lo envia al bridge Android.
 * @param {number|string} saleId
 * @returns {Promise<{ok:boolean,message:string,ticket?:object}>}
 */
export async function printSale(saleId) {
    ensurePrintAuditHook();

    if (!saleId) {
        return {
            ok: false,
            message: 'No se encontro el identificador de la venta.',
        };
    }

    if (!canPrintTicket()) {
        await postPrintAudit(saleId, {
            ticket_type: 'sale',
            print_attempted: false,
            print_success: false,
            error_message: 'Bridge Android no disponible.',
            connection_method: 'webview',
            printed_at: new Date().toISOString(),
            meta: {},
        });

        return {
            ok: false,
            message: 'Impresora Android no disponible en este dispositivo.',
        };
    }

    try {
        const ticket = await fetchTicketData(saleId);
        window.__lastPrintedSaleId = saleId;

        await postPrintAudit(saleId, {
            ticket_type: String(ticket?.meta?.ticket_type || 'sale'),
            print_attempted: true,
            print_success: false,
            error_message: null,
            connection_method: normalizeConnectionMethod(ticket?.meta?.connection_method),
            printed_at: new Date().toISOString(),
            meta: ticket?.meta || {},
        });

        window.AndroidPrinter.printTicket(JSON.stringify(ticket));

        return {
            ok: true,
            message: 'Ticket enviado a la impresora.',
            ticket,
        };
    } catch (error) {
        const networkError = error instanceof TypeError;

        await postPrintAudit(saleId, {
            ticket_type: 'sale',
            print_attempted: false,
            print_success: false,
            error_message: networkError
                ? 'Error de red al obtener ticket.'
                : (error?.message || 'No se pudo imprimir el ticket.'),
            connection_method: 'webview',
            printed_at: new Date().toISOString(),
            meta: {},
        });

        return {
            ok: false,
            message: networkError
                ? 'Error de red al obtener el ticket. Intenta nuevamente.'
                : (error?.message || 'No se pudo imprimir el ticket.'),
        };
    }
}

async function fetchLayawayTicketData(layawayId) {
    const endpoint = route('layaways.print-data', layawayId);

    const response = await fetch(endpoint, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        const message = payload?.message || 'No fue posible obtener los datos del ticket de apartado.';
        throw new Error(message);
    }

    if (!payload || typeof payload !== 'object') {
        throw new Error('El ticket de apartado no tiene un formato valido.');
    }

    return payload;
}

/**
 * Imprime ticket de creacion de apartado.
 * @param {number|string} layawayId
 * @returns {Promise<{ok:boolean,message:string,ticket?:object}>}
 */
export async function printLayawayCreated(layawayId) {
    if (!layawayId) {
        return {
            ok: false,
            message: 'No se encontro el identificador del apartado.',
        };
    }

    if (!canPrintTicket()) {
        return {
            ok: false,
            message: 'Impresora Android no disponible en este dispositivo.',
        };
    }

    try {
        const ticket = await fetchLayawayTicketData(layawayId);
        window.AndroidPrinter.printTicket(JSON.stringify(ticket));

        return {
            ok: true,
            message: 'Ticket de apartado enviado a impresora.',
            ticket,
        };
    } catch (error) {
        const networkError = error instanceof TypeError;

        return {
            ok: false,
            message: networkError
                ? 'Error de red al obtener el ticket del apartado. Intenta nuevamente.'
                : (error?.message || 'No se pudo imprimir el ticket del apartado.'),
        };
    }
}

async function fetchLayawayPaymentTicketData(layawayId, paymentId) {
    const endpoint = route('layaways.print.payment', {
        layaway: layawayId,
        payment: paymentId,
    });

    const response = await fetch(endpoint, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        const message = payload?.message || 'No fue posible obtener el ticket del abono.';
        throw new Error(message);
    }

    if (!payload || typeof payload !== 'object') {
        throw new Error('El ticket de abono no tiene un formato valido.');
    }

    return payload;
}

/**
 * Imprime ticket de abono de apartado.
 * @param {number|string} layawayId
 * @param {number|string} paymentId
 * @returns {Promise<{ok:boolean,message:string,ticket?:object}>}
 */
export async function printLayawayPayment(layawayId, paymentId) {
    if (!layawayId || !paymentId) {
        return {
            ok: false,
            message: 'No se pudo identificar el abono para impresion.',
        };
    }

    if (!canPrintTicket()) {
        return {
            ok: false,
            message: 'Impresora Android no disponible en este dispositivo.',
        };
    }

    try {
        const ticket = await fetchLayawayPaymentTicketData(layawayId, paymentId);
        window.AndroidPrinter.printTicket(JSON.stringify(ticket));

        return {
            ok: true,
            message: 'Ticket de abono enviado a impresora.',
            ticket,
        };
    } catch (error) {
        const networkError = error instanceof TypeError;

        return {
            ok: false,
            message: networkError
                ? 'Error de red al obtener el ticket del abono. Intenta nuevamente.'
                : (error?.message || 'No se pudo imprimir el ticket del abono.'),
        };
    }
}

async function fetchLayawayClosedTicketData(layawayId) {
    const endpoint = route('layaways.close-print-data', layawayId);

    const response = await fetch(endpoint, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        const message = payload?.message || 'No fue posible obtener el ticket de liquidacion.';
        throw new Error(message);
    }

    if (!payload || typeof payload !== 'object') {
        throw new Error('El ticket de liquidacion no tiene un formato valido.');
    }

    return payload;
}

/**
 * Imprime ticket de liquidacion de apartado.
 * @param {number|string} layawayId
 * @returns {Promise<{ok:boolean,message:string,ticket?:object}>}
 */
export async function printLayawayClosed(layawayId) {
    if (!layawayId) {
        return {
            ok: false,
            message: 'No se encontro el identificador del apartado para liquidacion.',
        };
    }

    if (!canPrintTicket()) {
        return {
            ok: false,
            message: 'Impresora Android no disponible en este dispositivo.',
        };
    }

    try {
        const ticket = await fetchLayawayClosedTicketData(layawayId);
        window.AndroidPrinter.printTicket(JSON.stringify(ticket));

        return {
            ok: true,
            message: 'Ticket de liquidacion enviado a impresora.',
            ticket,
        };
    } catch (error) {
        const networkError = error instanceof TypeError;

        return {
            ok: false,
            message: networkError
                ? 'Error de red al obtener el ticket de liquidacion. Intenta nuevamente.'
                : (error?.message || 'No se pudo imprimir el ticket de liquidacion.'),
        };
    }
}

export function setupAndroidPrinterBridgeGlobal() {
    if (typeof window === 'undefined') return;

    window.AndroidPrinterBridge = {
        isAndroidApp: hasAndroidPrinterBridge,
        imprimirVenta,
        openSettings: abrirConfiguracion,
        openPrinterSettings,
        abrirConfiguracion,
        getStatus: () => getPrinterStatus().status,
    };
}
