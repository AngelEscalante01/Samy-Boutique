<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Layaway;
use App\Models\LayawayItem;
use App\Models\LayawayPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LayawayService
{
    public function create(array $payload, User $user): Layaway
    {
        return DB::transaction(function () use ($payload, $user) {
            $customerId = Arr::get($payload, 'customer_id');
            $customerId = $customerId !== null ? (int) $customerId : null;

            $itemsPayload = Arr::get($payload, 'items', []);
            $productIds = collect($itemsPayload)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($productIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['El carrito está vacío.'],
                ]);
            }

            $customer = null;
            if ($customerId) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            }

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->with('images')
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Uno o más productos no existen.'],
                ]);
            }

            foreach ($productIds as $productId) {
                $product = $products->get($productId);
                if (! $product || $product->status !== 'disponible') {
                    throw ValidationException::withMessages([
                        'items' => ["El producto {$productId} no está disponible para apartado."],
                    ]);
                }
            }

            $subtotal = '0.00';
            foreach ($productIds as $productId) {
                $product = $products->get($productId);
                $subtotal = bcadd($subtotal, (string) $product->sale_price, 2);
            }

            $layaway = Layaway::create([
                'customer_id' => $customer?->id,
                'created_by' => (int) $user->id,
                'status' => 'open',
                'subtotal' => $subtotal,
                'paid_total' => '0.00',
            ]);

            foreach ($productIds as $productId) {
                $product = $products->get($productId);

                LayawayItem::create([
                    'layaway_id' => $layaway->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit_price' => (string) $product->sale_price,
                ]);

                $product->update([
                    'status' => 'apartado',
                ]);
            }

            // Pago inicial opcional
            $paymentsPayload = Arr::get($payload, 'payments', []);
            foreach ($paymentsPayload as $payment) {
                $this->addPaymentInternal($layaway, $payment);
            }

            return $layaway->fresh(['items.product', 'payments', 'customer', 'creator']);
        });
    }

    public function addPayment(Layaway $layaway, array $payload): LayawayPayment
    {
        return DB::transaction(function () use ($layaway, $payload) {
            /** @var Layaway $locked */
            $locked = Layaway::query()->lockForUpdate()->findOrFail($layaway->id);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages([
                    'layaway' => ['Solo se puede abonar a un apartado abierto.'],
                ]);
            }

            $payment = $this->addPaymentInternal($locked, $payload);

            return $payment;
        });
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

            $productIds = $locked->items->pluck('product_id')->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($productIds as $productId) {
                $product = $products->get($productId);
                if ($product && $product->status === 'apartado') {
                    $product->update(['status' => 'disponible']);
                }
            }

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return $locked->fresh(['items.product', 'payments', 'customer', 'creator']);
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
        return DB::transaction(function () use ($layaway, $payload, $user) {
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

            $productIds = $locked->items->pluck('product_id')->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->with('images')
                ->get()
                ->keyBy('id');

            foreach ($productIds as $productId) {
                $product = $products->get($productId);
                if (! $product || $product->status !== 'apartado') {
                    throw ValidationException::withMessages([
                        'items' => ["El producto {$productId} no está en estado apartado."],
                    ]);
                }
            }

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
                $product = $products->get($item->product_id);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'unit_price' => (string) $item->unit_price,
                    'discount_type' => null,
                    'discount_value' => null,
                    'discount_amount' => '0.00',
                    'line_total' => (string) $item->unit_price,
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

            // Finalizar: marcar productos vendidos
            foreach ($productIds as $productId) {
                $product = $products->get($productId);
                $product->update([
                    'status' => 'vendido',
                    'sold_at' => now(),
                ]);

                if (config('samy.delete_images_on_sale', false)) {
                    foreach ($product->images as $image) {
                        Storage::disk('public')->delete($image->path);
                        $image->delete();
                    }
                }
            }

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
            'method' => $method,
            'amount' => number_format($amount, 2, '.', ''),
            'reference' => $payload['reference'] ?? null,
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
