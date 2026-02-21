<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show:         { type: Boolean, default: false },
    total:        { type: Number,  default: 0 },
    isProcessing: { type: Boolean, default: false },
    errors:       { type: Object,  default: () => ({}) },
});

const emit = defineEmits(['close', 'confirm']);

// ── Payment ──────────────────────────────────────────────────────────────────
const paymentMode   = ref('cash');
const paymentLines  = ref([{ method: 'cash', amount: '' }]);
const cashReceived  = ref('');
const paymentError  = ref('');

const cashChange = computed(() => {
    const recv = Number(cashReceived.value || 0);
    return recv > props.total ? recv - props.total : 0;
});

watch(() => props.show, (v) => { if (v) reset(); });

watch(paymentMode, () => {
    paymentError.value = '';
    cashReceived.value = '';
    if (paymentMode.value === 'mixed') {
        paymentLines.value = [
            { method: 'cash',   amount: '' },
            { method: 'card',   amount: '' },
        ];
    } else {
        paymentLines.value = [{ method: paymentMode.value, amount: fmt(props.total) }];
    }
});

function fmt(n) { return Number(n ?? 0).toFixed(2); }

function money(n) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n ?? 0);
}

function paymentsSum() {
    return paymentLines.value.reduce((sum, p) => sum + Number(p.amount || 0), 0);
}

function addPaymentLine() {
    paymentLines.value.push({ method: 'transfer', amount: '' });
}

function removePaymentLine(idx) {
    if (paymentLines.value.length > 1) paymentLines.value.splice(idx, 1);
}

// ── Validation + submit ───────────────────────────────────────────────────────
function reset() {
    paymentMode.value  = 'cash';
    paymentLines.value = [{ method: 'cash', amount: fmt(props.total) }];
    cashReceived.value = '';
    paymentError.value = '';
}

function validate() {
    paymentError.value = '';
    if (paymentMode.value === 'mixed') {
        const lines = paymentLines.value.filter(p => Number(p.amount || 0) > 0);
        if (!lines.length) { paymentError.value = 'Agrega al menos un pago con monto.'; return false; }
        const sum = Number(paymentsSum().toFixed(2));
        if (sum !== Number(props.total.toFixed(2))) {
            paymentError.value = `La suma (${money(sum)}) debe ser igual al total (${money(props.total)}).`;
            return false;
        }
    }
    return true;
}

function buildPayments() {
    if (paymentMode.value === 'mixed') {
        return paymentLines.value
            .filter(p => Number(p.amount || 0) > 0)
            .map(p => ({ method: p.method, amount: Number(p.amount) }));
    }
    return [{ method: paymentMode.value, amount: props.total }];
}

function submit() {
    if (!validate()) return;
    emit('confirm', { payments: buildPayments() });
}

const methodLabels = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' };
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-gray-950/50 backdrop-blur-sm" @click="$emit('close')" />

                <!-- Dialog -->
                <div class="relative w-full max-w-lg rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Confirmar cobro</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Completa el registro de la venta</p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="$emit('close')">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="max-h-[80vh] overflow-y-auto px-5 py-4 space-y-5">

                        <!-- Total grande -->
                        <div class="rounded-xl bg-gray-900 px-5 py-4 text-center">
                            <p class="text-xs font-medium uppercase tracking-widest text-gray-400">Total a cobrar</p>
                            <p class="mt-1 text-4xl font-black text-white tabular-nums">
                                {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(total) }}
                            </p>
                        </div>

                        <!-- Método de pago - tabs -->
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Método de pago</p>
                            <div class="grid grid-cols-4 gap-1 rounded-xl bg-gray-100 p-1">
                                <button
                                    v-for="m in ['cash', 'card', 'transfer', 'mixed']"
                                    :key="m"
                                    type="button"
                                    class="rounded-lg py-2 text-xs font-semibold transition-all"
                                    :class="paymentMode === m ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    @click="paymentMode = m"
                                >
                                    {{ m === 'cash' ? 'Efectivo' : m === 'card' ? 'Tarjeta' : m === 'transfer' ? 'Transfer.' : 'Mixto' }}
                                </button>
                            </div>
                        </div>

                        <!-- Efectivo: recibido + cambio -->
                        <div v-if="paymentMode === 'cash'" class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Recibido</label>
                                <input
                                    v-model="cashReceived"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="w-full rounded-lg border-gray-200 py-2.5 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Cambio</label>
                                <div class="flex h-[42px] items-center rounded-lg border border-gray-200 bg-gray-50 px-3">
                                    <span class="text-sm font-bold" :class="cashChange > 0 ? 'text-emerald-600' : 'text-gray-400'">
                                        {{ cashChange > 0 ? new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(cashChange) : '—' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta / Transferencia: simple info -->
                        <div v-else-if="paymentMode !== 'mixed'" class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            Se registrará un pago de
                            <span class="font-bold text-gray-900">
                                {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(total) }}
                            </span>
                            por {{ methodLabels[paymentMode] }}.
                        </div>

                        <!-- Mixto: líneas de pago -->
                        <div v-else class="space-y-2">
                            <div
                                v-for="(line, idx) in paymentLines"
                                :key="idx"
                                class="flex gap-2"
                            >
                                <select
                                    v-model="line.method"
                                    class="w-32 shrink-0 rounded-lg border-gray-200 py-2 pl-2 pr-6 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400"
                                >
                                    <option value="cash">Efectivo</option>
                                    <option value="card">Tarjeta</option>
                                    <option value="transfer">Transfer.</option>
                                </select>
                                <input
                                    v-model="line.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Monto"
                                    class="w-full rounded-lg border-gray-200 py-2 text-sm shadow-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400"
                                />
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-500 hover:bg-gray-50"
                                    @click="removePaymentLine(idx)"
                                >✕</button>
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium" @click="addPaymentLine">
                                    + Agregar línea
                                </button>
                                <span class="text-xs text-gray-500">
                                    Suma:
                                    <span class="font-bold" :class="Math.abs(paymentsSum() - total) < 0.01 ? 'text-emerald-600' : 'text-red-600'">
                                        {{ new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(paymentsSum()) }}
                                    </span>
                                </span>
                            </div>

                            <p v-if="paymentError" class="text-xs text-red-600">{{ paymentError }}</p>
                        </div>

                        <!-- Errores de backend -->
                        <div v-if="Object.keys(errors).length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                            <p class="text-xs font-semibold text-red-800">Error al registrar la venta:</p>
                            <ul class="mt-1 list-disc pl-4 text-xs text-red-700">
                                <li v-for="(msg, key) in errors" :key="key">{{ msg }}</li>
                            </ul>
                        </div>

                    </div><!-- /scrollable body -->

                    <!-- Footer buttons -->
                    <div class="flex gap-3 border-t border-gray-100 px-5 py-4">
                        <button
                            type="button"
                            class="flex-1 rounded-xl border border-gray-200 py-3 text-sm font-semibold text-gray-600 hover:bg-gray-50"
                            :disabled="isProcessing"
                            @click="$emit('close')"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-gray-900 py-3 text-sm font-bold text-white hover:bg-gray-700 disabled:opacity-50"
                            :disabled="isProcessing"
                            @click="submit"
                        >
                            <span v-if="isProcessing" class="flex items-center justify-center gap-2">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg>
                                Procesando...
                            </span>
                            <span v-else>Confirmar venta</span>
                        </button>
                    </div>
                </div>

            </div>
        </transition>
    </teleport>
</template>
