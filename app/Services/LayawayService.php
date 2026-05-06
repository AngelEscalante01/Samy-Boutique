<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Layaway;
use App\Models\LayawayItem;
use App\Models\LayawayPayment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LayawayService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly NotificationService $notificationService,
    ) {}

    public function create(array $payload, User $user): Layaway
    {
        $layaway = DB::transaction(function () use ($payload, $user) {
            $customerId = Arr::get($payload, 'customer_id');
            $customerId = $customerId !== null ? (int) $customerId : null;
            $vigenciaDias = max(1, (int) Arr::get($payload, 'vigencia_dias', 0));

            $itemsPayload = Arr::get($payload, 'items', []);
            $variantIds = collect($itemsPayload)
                ->pluck('variant_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($variantIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['El carrito está vacío.'],
                ]);
            }

            $customer = null;
            if ($customerId) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            }

            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->with('product')
                ->get()
                ->keyBy('id');

            if ($variants->count() !== $variantIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Una o más variantes no existen.'],
                ]);
            }

            foreach ($itemsPayload as $item) {
                $variantId = (int) $item['variant_id'];
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $variant = $variants->get($variantId);

                if (! $variant || ! $variant->active || (int) $variant->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["La variante {$variantId} no tiene stock suficiente para apartado."],
                    ]);
                }
            }

            $subtotal = '0.00';
            foreach ($itemsPayload as $item) {
                $variant = $variants->get((int) $item['variant_id']);
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $unitPrice = $this->inventoryService->effectiveSalePrice($variant);
                $lineTotal = $unitPrice * $qty;
                $subtotal = bcadd($subtotal, number_format($lineTotal, 2, '.', ''), 2);
            }

            $layaway = Layaway::create([
                'customer_id' => $customer?->id,
                'created_by' => (int) $user->id,
                'status' => 'open',
                'subtotal' => $subtotal,
                'paid_total' => '0.00',
                'vigencia_dias' => $vigenciaDias,
                'fecha_vencimiento' => now()->startOfDay()->addDays($vigenciaDias)->toDateString(),
                'observaciones' => Arr::get($payload, 'observaciones'),
            ]);

            foreach ($itemsPayload as $item) {
                /** @var ProductVariant $variant */
                $variant = $variants->get((int) $item['variant_id']);
                /** @var Product $product */
                $product = $variant->product;
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $unitPrice = number_format($this->inventoryService->effectiveSalePrice($variant), 2, '.', '');

                LayawayItem::create([
                    'layaway_id' => $layaway->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $qty,
                    'qty' => $qty,
                    'sku' => $variant->sku ?: $product->sku,
                    'name' => $product->name,
                    'unit_price' => $unitPrice,
                ]);

                $this->inventoryService->decrementVariantStock($variant, $qty);
            }

            $this->inventoryService->updateManySoldOutAt(
                $variants->pluck('product_id')->map(fn ($id) => (int) $id)
            );

            // Pago inicial opcional
            $paymentsPayload = Arr::get($payload, 'payments', []);
            foreach ($paymentsPayload as $payment) {
                $this->addPaymentInternal($layaway, array_merge($payment, [
                    'created_by' => (int) $user->id,
                ]));
            }

            return $layaway->fresh(['items.product', 'items.variant.size', 'items.variant.color', 'payments', 'customer', 'creator']);
        });

        $this->notificationService->notifyLayawayCreated($layaway, $user);

        return $layaway;
    }

    public function updateVigencia(Layaway $layaway, int $vigenciaDias): Layaway
    {
        return DB::transaction(function () use ($layaway, $vigenciaDias) {
            /** @var Layaway $locked */
            $locked = Layaway::query()->lockForUpdate()->findOrFail($layaway->id);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages([
                    'layaway' => ['La vigencia solo se puede modificar en apartados abiertos.'],
                ]);
            }

            $baseDate = $locked->created_at?->copy()->startOfDay() ?? now()->startOfDay();

            $locked->update([
                'vigencia_dias' => $vigenciaDias,
                'fecha_vencimiento' => $baseDate->addDays($vigenciaDias)->toDateString(),
            ]);

            return $locked->fresh(['items.product', 'items.variant.size', 'items.variant.color', 'payments', 'customer', 'creator', 'sale']);
        });
    }

    public function addPayment(Layaway $layaway, array $payload, ?User $user = null): LayawayPayment
    {
        $payment = DB::transaction(function () use ($layaway, $payload, $user) {
            /** @var Layaway $locked */
            $locked = Layaway::query()->lockForUpdate()->findOrFail($layaway->id);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages([
                    'layaway' => ['Solo se puede abonar a un apartado abierto.'],
                ]);
            }

            $payment = $this->addPaymentInternal($locked, array_merge($payload, [
                'created_by' => $user?->id,
            ]));

            return $payment;
        });

        if ($user !== null) {
            $layaway->refresh();
            $this->notificationService->notifyLayawayPayment($layaway, $payment, $user);
        }

        return $payment;
    }

    public function cancel(Layaway $layaway): Layaway
    {
        return DB::transaction(function () use ($layaway) {
            /** @var Layaway $locked */
            $locked = Layaway::query()->lockForUpdate()->with('items')->findOrFail($layaway->id);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages([
                    'layaway' => ['Solo se puede cancelar un apartado abierto.'],
                ]);
            }

            $variantIds = $locked->items
                ->pluck('product_variant_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($locked->items as $item) {
                $variant = $variants->get((int) ($item->product_variant_id ?? 0));
                if (! $variant) {
                    continue;
                }

                $qty = max(1, (int) ($item->qty ?? $item->quantity ?? 1));
                $this->inventoryService->incrementVariantStock($variant, $qty);
            }

            $this->inventoryService->updateManySoldOutAt(
                $variants->pluck('product_id')->map(fn ($id) => (int) $id)
            );

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return $locked->fresh(['items.product', 'items.variant.size', 'items.variant.color', 'payments', 'customer', 'creator']);
        });
    }

    /**
     * Liquidar: crea Sale ligada al layaway y marca productos como vendido.
     *
     * Reglas:
     * - Layaway debe estar open
     * - Productos deben estar apartado
     * - Requiere que los pagos (abonos + pagos finales) cubran exactamente el total final
     * - Fidelidad: si purchases_count==4, aplica descuento automático sobre el saldo restante
     */
    public function liquidate(Layaway $layaway, array $payload, User $user): Sale
    {
        $sale = DB::transaction(function () use ($layaway, $payload, $user) {
            /** @var Layaway $locked */
            $locked = Layaway::query()
                ->lockForUpdate()
                ->with(['items', 'payments', 'customer'])
                ->findOrFail($layaway->id);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages([
                    'layaway' => ['Solo se puede liquidar un apartado abierto.'],
                ]);
            }

            $customer = null;
            if ($locked->customer_id) {
                $customer = Customer::query()->lockForUpdate()->find($locked->customer_id);
            }

            $variantIds = $locked->items
                ->pluck('product_variant_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->with('product.images')
                ->get()
                ->keyBy('id');

            $subtotal = (float) $locked->subtotal;
            $paidTotal = (float) $locked->paid_total;
            $remaining = max(0, round($subtotal - $paidTotal, 2));

            // Fidelidad sobre saldo restante
            $loyaltyApplied = false;
            $loyaltyDiscountTotal = 0.0;

            if ($customer && (int) $customer->purchases_count === 4) {
                $enabled = (bool) Setting::get('loyalty.enabled', false);
                if ($enabled && $remaining > 0) {
                    $type = $this->normalizeDiscountType((string) Setting::get('loyalty.type', 'percent'));
                    $value = (float) Setting::get('loyalty.value', 0);

                    if ($type !== null && $value > 0) {
                        $loyaltyDiscountTotal = $this->computeDiscountAmount($remaining, $type, $value);
                        $loyaltyDiscountTotal = min($remaining, round($loyaltyDiscountTotal, 2));
                        if ($loyaltyDiscountTotal > 0) {
                            $loyaltyApplied = true;
                        }
                    }
                }
            }

            $finalRemaining = max(0, round($remaining - $loyaltyDiscountTotal, 2));

            $finalPayments = collect(Arr::get($payload, 'payments', []))
                ->map(function ($payment) {
                    return [
                        'method' => (string) ($payment['method'] ?? 'cash'),
                        'amount' => round((float) ($payment['amount'] ?? 0), 2),
                        'reference' => $payment['reference'] ?? null,
                    ];
                })
                ->filter(fn ($payment) => $payment['amount'] > 0)
                ->values()
                ->all();

            $finalPaymentsSum = 0.0;
            foreach ($finalPayments as $p) {
                $finalPaymentsSum += (float) $p['amount'];
            }

            if (round($finalPaymentsSum, 2) !== round($finalRemaining, 2)) {
                throw ValidationException::withMessages([
                    'payments' => ["Los pagos finales deben ser exactamente {$finalRemaining}."],
                ]);
            }

            $total = round($subtotal - $loyaltyDiscountTotal, 2);

            $sale = Sale::create([
                'customer_id' => $locked->customer_id,
                'created_by' => (int) $user->id,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount_total' => number_format($loyaltyDiscountTotal, 2, '.', ''),
                'coupon_discount_total' => '0.00',
                'loyalty_discount_total' => number_format($loyaltyDiscountTotal, 2, '.', ''),
                'loyalty_applied' => $loyaltyApplied,
                'total' => number_format($total, 2, '.', ''),
                'global_discount_type' => null,
                'global_discount_value' => null,
                'coupon_code' => null,
                'coupon_id' => null,
                'status' => 'completed',
            ]);

            // Items de la venta
            foreach ($locked->items as $item) {
                $variant = $variants->get((int) ($item->product_variant_id ?? 0));
                $product = $variant?->product;
                $qty = max(1, (int) ($item->qty ?? $item->quantity ?? 1));
                $lineTotal = number_format((float) $item->unit_price * $qty, 2, '.', '');

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $qty,
                    'qty' => $qty,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'unit_price' => (string) $item->unit_price,
                    'discount_type' => null,
                    'discount_value' => null,
                    'discount_amount' => '0.00',
                    'discount' => '0.00',
                    'line_total' => $lineTotal,
                    'final_price' => $lineTotal,
                ]);
            }

            // Pagos: copiar abonos + pagos finales
            $paymentsTotal = 0.0;
            foreach ($locked->payments as $p) {
                $paymentsTotal += (float) $p->amount;

                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $p->method,
                    'amount' => (string) $p->amount,
                    'reference' => $p->reference,
                ]);
            }

            foreach ($finalPayments as $p) {
                $paymentsTotal += (float) $p['amount'];

                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $p['method'],
                    'amount' => number_format((float) $p['amount'], 2, '.', ''),
                    'reference' => $p['reference'] ?? null,
                ]);
            }

            if (round($paymentsTotal, 2) !== round($total, 2)) {
                throw ValidationException::withMessages([
                    'payments' => ['La suma total de pagos (abonos + pagos finales) debe coincidir con el total de la venta.'],
                ]);
            }

            $this->inventoryService->updateManySoldOutAt(
                $variants->pluck('product_id')->map(fn ($id) => (int) $id)
            );

            // Actualiza layaway
            $locked->update([
                'status' => 'liquidated',
                'sale_id' => $sale->id,
                'liquidated_at' => now(),
            ]);

            // Fidelidad: purchases_count cuenta ventas pagadas
            if ($customer) {
                if ($loyaltyApplied) {
                    $customer->forceFill(['purchases_count' => 0])->save();
                } else {
                    $customer->increment('purchases_count');
                }
            }

            return $sale->fresh(['items', 'payments', 'customer', 'creator']);
        });

        $this->notificationService->notifySale($sale, $user);

        return $sale;
    }

    private function addPaymentInternal(Layaway $layaway, array $payload): LayawayPayment
    {
        $method = (string) ($payload['method'] ?? '');
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if (! in_array($method, ['cash', 'card', 'transfer', 'other'], true)) {
            throw ValidationException::withMessages([
                'method' => ['Método inválido.'],
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El monto debe ser mayor a 0.'],
            ]);
        }

        $balance = (float) $layaway->balance;
        if ($amount - 1e-9 > $balance) {
            throw ValidationException::withMessages([
                'amount' => ['El abono no puede exceder el saldo.'],
            ]);
        }

        $payment = LayawayPayment::create([
            'layaway_id' => $layaway->id,
            'created_by' => isset($payload['created_by']) ? (int) $payload['created_by'] : null,
            'method' => $method,
            'amount' => number_format($amount, 2, '.', ''),
            'reference' => $payload['reference'] ?? null,
            'observacion' => $payload['observacion'] ?? null,
            'paid_at' => $payload['paid_at'] ?? now(),
        ]);

        $layaway->update([
            'paid_total' => bcadd((string) $layaway->paid_total, number_format($amount, 2, '.', ''), 2),
        ]);

        return $payment;
    }

    private function normalizeDiscountType(string $type): ?string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            '%', 'percent', 'percentage' => 'percent',
            '$', 'amount' => 'amount',
            default => null,
        };
    }

    private function computeDiscountAmount(float $baseAmount, string $type, float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            $percent = min(100.0, max(0.0, $value));
            return $baseAmount * ($percent / 100.0);
        }

        return min($baseAmount, max(0.0, $value));
    }
}
