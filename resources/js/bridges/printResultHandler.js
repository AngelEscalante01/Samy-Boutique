const PRINT_RESULT_EVENT = 'app:print-result';
const APP_NOTIFY_EVENT = 'app:notify';

function normalizePayload(input) {
    if (typeof input === 'string') {
        return { status: input, message: null, meta: {} };
    }

    if (input && typeof input === 'object') {
        return {
            status: String(input.status || 'unknown').toLowerCase(),
            message: typeof input.message === 'string' ? input.message : null,
            meta: input.meta && typeof input.meta === 'object' ? input.meta : {},
        };
    }

    return { status: 'unknown', message: null, meta: {} };
}

function messageForStatus(status) {
    if (status === 'success') return 'Impresion completada correctamente.';
    if (status === 'error') return 'Hubo un problema al imprimir. Intenta nuevamente.';
    if (status === 'disconnected') return 'La impresora esta desconectada. Verifica la conexion.';
    return 'Se recibio un estado de impresion no reconocido.';
}

function levelForStatus(status) {
    if (status === 'success') return 'success';
    if (status === 'error' || status === 'disconnected') return 'error';
    return 'info';
}

function logResult(payload, friendlyMessage) {
    const logData = {
        status: payload.status,
        message: friendlyMessage,
        meta: payload.meta,
        at: new Date().toISOString(),
    };

    if (payload.status === 'success') {
        console.info('[PRINT] Resultado:', logData);
        return;
    }

    if (payload.status === 'error' || payload.status === 'disconnected') {
        console.warn('[PRINT] Resultado:', logData);
        return;
    }

    console.log('[PRINT] Resultado:', logData);
}

function notifyUser(message, level) {
    const detail = { text: message, type: level };

    if (typeof window !== 'undefined' && window.Swal && typeof window.Swal.fire === 'function') {
        const icon = level === 'success' ? 'success' : (level === 'error' ? 'error' : 'info');
        window.Swal.fire({
            icon,
            title: level === 'success' ? 'Impresion' : 'Aviso de impresion',
            text: message,
            timer: 2800,
            showConfirmButton: false,
        });
    }

    if (typeof window.showGlobalToast === 'function') {
        window.showGlobalToast(detail.text, detail.type);
    }

    window.dispatchEvent(new CustomEvent(APP_NOTIFY_EVENT, { detail }));
}

async function recordAudit(payload, friendlyMessage) {
    // Hook no-op para conectar auditoria backend despues.
    // Puedes reemplazar window.printAuditHook con una funcion async personalizada.
    if (typeof window.printAuditHook !== 'function') {
        return;
    }

    try {
        await window.printAuditHook({
            status: payload.status,
            message: friendlyMessage,
            meta: payload.meta,
            created_at: new Date().toISOString(),
        });
    } catch (error) {
        console.error('[PRINT] Error registrando auditoria:', error);
    }
}

export function setupGlobalPrintResultHandler() {
    window.onPrintResult = async function onPrintResult(status) {
        const payload = normalizePayload(status);
        const defaultMessage = messageForStatus(payload.status);
        const friendlyMessage = payload.message || defaultMessage;
        const level = levelForStatus(payload.status);

        logResult(payload, friendlyMessage);
        notifyUser(friendlyMessage, level);

        window.dispatchEvent(new CustomEvent(PRINT_RESULT_EVENT, {
            detail: {
                status: payload.status,
                message: friendlyMessage,
                meta: payload.meta,
            },
        }));

        await recordAudit(payload, friendlyMessage);
    };
}

export { APP_NOTIFY_EVENT, PRINT_RESULT_EVENT };
