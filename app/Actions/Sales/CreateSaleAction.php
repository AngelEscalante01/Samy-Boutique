<?php

namespace App\Actions\Sales;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateSaleAction
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Crea una venta completa.
     *
     * Reglas clave:
     * - Solo products.status = disponible
     * - Transacción DB
     * - Al finalizar: marcar products como vendido y sold_at
     * - Si samy.delete_images_on_sale=true: borrar archivos y registros de product_images
     */
    public function execute(array $payload, User $user): Sale
    {
        return DB::transaction(function () use ($payload, $user) {
            $itemsPayload = Arr::get($payload, 'items', []);
            $paymentsPayload = Arr::get($payload, 'payments', []);

            $createdByUserId = (int) $user->id;
            $customerId = Arr::get($payload, 'customer_id');
            $customerId = $customerId !== null ? (int) $customerId : null;

            $customer = null;
            if ($customerId) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            }

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

            // Bloqueo para evitar doble venta concurrente
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->with(['product.images'])
                ->get()
                ->keyBy('id');

            if ($variants->count() !== $variantIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Una o más variantes no existen.'],
                ]);
            }

            foreach ($itemsPayload as $item) {
                $variantId = (int) $item['variant_id'];
                $qty = (int) ($item['qty'] ?? 1);
                $variant = $variants->get($variantId);

                if (! $variant || ! $variant->active || (int) $variant->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["La variante {$variantId} no tiene stock suficiente."],
                    ]);
                }
            }

            // Descuentos manuales: validar permisos
            $this->assertDiscountPermissionsForPayload($user, $payload);

            // Cálculo por item
            $subtotal = '0.00';
            $discountTotal = '0.00';

            $computedItems = [];

            foreach ($itemsPayload as $item) {
                $variantId = (int) $item['variant_id'];
                $qty = max(1, (int) ($item['qty'] ?? 1));

                /** @var ProductVariant $variant */
                $variant = $variants->get($variantId);
                /** @var Product $product */
                $product = $variant->product;

                $unitPriceFloat = $this->inventoryService->effectiveSalePrice($variant);
                $unitPrice = number_format($unitPriceFloat, 2, '.', '');
                $lineBase = $unitPriceFloat * $qty;

                [$itemDiscountAmount, $itemDiscountType, $itemDiscountValue] = $this->computeDiscount(
                    $lineBase,
                    Arr::get($item, 'discount_type'),
                    Arr::get($item, 'discount_value'),
                );

                $lineTotal = max(0, $lineBase - $itemDiscountAmount);

                $subtotal = bcadd($subtotal, (string) $lineBase, 2);
                $discountTotal = bcadd($discountTotal, number_format($itemDiscountAmount, 2, '.', ''), 2);

                $computedItems[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_type' => $itemDiscountType,
                    'discount_value' => $itemDiscountValue,
                    'discount_amount' => number_format($itemDiscountAmount, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ];
            }

            // Descuento global
            $afterItemDiscounts = max(0, (float) bcsub($subtotal, $discountTotal, 2));
            [$globalDiscountAmount, $globalDiscountType, $globalDiscountValue] = $this->computeDiscount(
                $afterItemDiscounts,
                Arr::get($payload, 'global_discount_type'),
                Arr::get($payload, 'global_discount_value'),
            );

            $discountTotal = bcadd($discountTotal, number_format($globalDiscountAmount, 2, '.', ''), 2);

            $afterManualDiscounts = max(0, $afterItemDiscounts - $globalDiscountAmount);

            // Cupón
            $couponCode = trim((string) Arr::get($payload, 'coupon_code', ''));
            $coupon = null;
            $couponDiscountTotal = '0.00';

            if ($couponCode !== '') {
                $this->assertCouponPermission($user);

                $coupon = Coupon::query()
                    ->where('code', $couponCode)
                    ->lockForUpdate()
                    ->first();

                if (!$coupon || !$coupon->active) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['Cupón inválido o inactivo.'],
                    ]);
                }

                $now = now();
                if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['El cupón aún no es válido.'],
                    ]);
                }
                if ($coupon->ends_at && $now->gt($coupon->ends_at)) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['El cupón ya expiró.'],
                    ]);
                }

                // Mínimo de compra (subtotal antes de descuentos)
                if ($coupon->min_total !== null && (float) $subtotal < (float) $coupon->min_total) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['El cupón requiere un mínimo de compra.'],
                    ]);
                }

                // Límites globales
                if ($coupon->max_redemptions !== null) {
                    $used = CouponRedemption::where('coupon_id', $coupon->id)->lockForUpdate()->count();
                    if ($used >= $coupon->max_redemptions) {
                        throw ValidationException::withMessages([
                            'coupon_code' => ['El cupón alcanzó su límite de uso.'],
                        ]);
                    }
                }

                // Límite por cliente
                if ($customerId && $coupon->max_redemptions_per_customer !== null) {
                    $usedByCustomer = CouponRedemption::where('coupon_id', $coupon->id)
                        ->where('customer_id', $customerId)
                        ->lockForUpdate()
                        ->count();

                    if ($usedByCustomer >= $coupon->max_redemptions_per_customer) {
                        throw ValidationException::withMessages([
                            'coupon_code' => ['El cupón alcanzó su límite para este cliente.'],
                        ]);
                    }
                }

                [$couponDiscountAmount] = $this->computeDiscount(
                    $afterManualDiscounts,
                    $coupon->discount_type,
                    $coupon->discount_value,
                );

                $couponDiscountTotal = number_format(min($afterManualDiscounts, $couponDiscountAmount), 2, '.', '');
            }

            $total = (float) bcsub((string) $afterManualDiscounts, $couponDiscountTotal, 2);
            $total = max(0, $total);

            // Fidelidad (5ta compra): aplica descuento automático si purchases_count==4 antes de vender.
            $loyaltyApplied = false;
            $loyaltyDiscountTotal = '0.00';

            if ($customer && (int) $customer->purchases_count === 4) {
                $enabled = (bool) Setting::get('loyalty.enabled', false);
                if ($enabled) {
                    $type = (string) Setting::get('loyalty.type', 'percent');
                    $value = (float) Setting::get('loyalty.value', 0);

                    $type = $this->normalizeDiscountType($type);

                    if ($type !== null && $value > 0) {
                        [$loyaltyAmount] = $this->computeDiscount($total, $type, $value);
                        $loyaltyAmount = min($total, round($loyaltyAmount, 2));

                        if ($loyaltyAmount > 0) {
                            $loyaltyApplied = true;
                            $loyaltyDiscountTotal = number_format($loyaltyAmount, 2, '.', '');
                            $discountTotal = bcadd($discountTotal, $loyaltyDiscountTotal, 2);
                            $total = max(0, (float) bcsub(number_format($total, 2, '.', ''), $loyaltyDiscountTotal, 2));
                        }
                    }
                }
            }

            // Pagos
            $paymentsSum = 0.0;
            foreach ($paymentsPayload as $p) {
                $paymentsSum += (float) $p['amount'];
            }
            if (round($paymentsSum, 2) !== round($total, 2)) {
                throw ValidationException::withMessages([
                    'payments' => ['El total de pagos debe coincidir exactamente con el total de la venta.'],
                ]);
            }

            $sale = Sale::create([
                'customer_id' => $customerId,
                'created_by' => $createdByUserId,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'coupon_discount_total' => $couponDiscountTotal,
                'loyalty_discount_total' => $loyaltyDiscountTotal,
                'loyalty_applied' => $loyaltyApplied,
                'total' => number_format($total, 2, '.', ''),
                'global_discount_type' => $globalDiscountType,
                'global_discount_value' => $globalDiscountValue,
                'coupon_code' => $coupon ? $coupon->code : null,
                'coupon_id' => $coupon?->id,
                'status' => 'completed',
            ]);

            foreach ($computedItems as $computed) {
                /** @var Product $product */
                $product = $computed['product'];
                /** @var ProductVariant $variant */
                $variant = $computed['variant'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $computed['qty'],
                    'qty' => $computed['qty'],
                    'sku' => $variant->sku ?: $product->sku,
                    'name' => $product->name,
                    'unit_price' => $computed['unit_price'],
                    'discount_type' => $computed['discount_type'],
                    'discount_value' => $computed['discount_value'],
                    'discount_amount' => $computed['discount_amount'],
                    'discount' => $computed['discount_amount'],
                    'line_total' => $computed['line_total'],
                    'final_price' => $computed['line_total'],
                ]);
            }

            foreach ($paymentsPayload as $payment) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            if ($coupon) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'sale_id' => $sale->id,
                    'customer_id' => $customerId,
                    'redeemed_at' => now(),
                ]);
            }

            // Finalizar: descontar stock por variante
            foreach ($computedItems as $computed) {
                /** @var ProductVariant $variant */
                $variant = $computed['variant'];
                $this->inventoryService->decrementVariantStock($variant, (int) $computed['qty']);
            }

            $this->inventoryService->updateManySoldOutAt(
                collect($computedItems)->pluck('product.id')->map(fn ($id) => (int) $id)
            );

            if (config('samy.delete_images_on_sale', false)) {
                $products = collect($computedItems)
                    ->pluck('product')
                    ->unique('id');

                foreach ($products as $product) {
                    if ($product->fresh()?->sold_out_at) {
                        foreach ($product->images as $image) {
                            Storage::disk('public')->delete($image->path);
                            $image->delete();
                        }
                    }
                }
            }

            // Fidelidad: purchases_count cuenta ventas pagadas (esta venta es completed + pagos exactos)
            if ($customer) {
                if ($loyaltyApplied) {
                    $customer->forceFill(['purchases_count' => 0])->save();
                } else {
                    $customer->increment('purchases_count');
                }
            }

            return $sale;
        });
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

    private function assertCouponPermission(User $user): void
    {
        if (! $user->can('sales.apply_coupon')) {
            throw ValidationException::withMessages([
                'coupon_code' => ['No tienes permiso para aplicar cupones.'],
            ]);
        }
    }

    /**
     * @return array{0: float, 1: string|null, 2: string|null}
     */
    private function computeDiscount(float $baseAmount, mixed $type, mixed $value): array
    {
        $type = $type ? (string) $type : null;
        if ($type === null) {
            return [0.0, null, null];
        }

        $valueFloat = (float) $value;
        if ($valueFloat <= 0) {
            return [0.0, null, null];
        }

        if ($type === 'percent') {
            $percent = min(100.0, max(0.0, $valueFloat));
            $amount = $baseAmount * ($percent / 100.0);
            return [$amount, 'percent', number_format($percent, 2, '.', '')];
        }

        if ($type === 'amount') {
            $amount = min($baseAmount, max(0.0, $valueFloat));
            return [$amount, 'amount', number_format($amount, 2, '.', '')];
        }

        return [0.0, null, null];
    }

    private function payloadHasAnyManualDiscount(array $payload): bool
    {
        $globalType = Arr::get($payload, 'global_discount_type');
        $globalValue = Arr::get($payload, 'global_discount_value');

        if (!empty($globalType) && (float) $globalValue > 0) {
            return true;
        }

        foreach (Arr::get($payload, 'items', []) as $item) {
            if (!empty($item['discount_type']) && (float) ($item['discount_value'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida permisos de descuento.
     * Regla:
     * - Si hay cualquier descuento: requiere sales.apply_discount_basic (o sales.apply_discount_high)
     * - Si cualquier descuento excede umbral (config): requiere sales.apply_discount_high
     */
    private function assertDiscountPermissionsForPayload(User $user, array $payload): void
    {
        if (! $this->payloadHasAnyManualDiscount($payload)) {
            return;
        }

        if (! $user->can('sales.apply_discount_basic') && ! $user->can('sales.apply_discount_high')) {
            throw ValidationException::withMessages([
                'discount' => ['No tienes permiso para aplicar descuentos.'],
            ]);
        }

        $requiresHigh = false;

        $globalType = (string) Arr::get($payload, 'global_discount_type', '');
        $globalValue = (float) Arr::get($payload, 'global_discount_value', 0);
        if ($globalType !== '' && $globalValue > 0) {
            $requiresHigh = $requiresHigh || $this->discountRequiresHighPermission($globalType, $globalValue);
        }

        foreach (Arr::get($payload, 'items', []) as $item) {
            $type = (string) Arr::get($item, 'discount_type', '');
            $value = (float) Arr::get($item, 'discount_value', 0);
            if ($type === '' || $value <= 0) {
                continue;
            }

            $requiresHigh = $requiresHigh || $this->discountRequiresHighPermission($type, $value);
        }

        if ($requiresHigh && ! $user->can('sales.apply_discount_high')) {
            throw ValidationException::withMessages([
                'discount' => ['El descuento solicitado requiere permiso de descuento alto.'],
            ]);
        }
    }

    private function discountRequiresHighPermission(string $discountType, float $discountValue): bool
    {
        $maxPercent = (float) config('samy.discount_basic_max_percent', 10);
        $maxAmount = (float) config('samy.discount_basic_max_amount', 100);

        if ($discountType === 'percent') {
            return $discountValue > $maxPercent;
        }

        return $discountValue > $maxAmount;
    }
}
