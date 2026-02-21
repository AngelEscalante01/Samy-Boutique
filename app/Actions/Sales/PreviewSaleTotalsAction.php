<?php

namespace App\Actions\Sales;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PreviewSaleTotalsAction
{
    /**
     * Calcula totales de una venta sin persistir.
     * Devuelve: subtotal, discount_total, coupon_discount_total, total.
     */
    public function execute(array $payload, User $user): array
    {
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

        $products = Product::query()
            ->whereIn('id', $productIds)
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
                    'items' => ["El producto {$productId} no está disponible."],
                ]);
            }
        }

        $this->assertDiscountPermissionsForPayload($user, $payload);

        $subtotal = '0.00';
        $discountTotal = '0.00';

        foreach ($itemsPayload as $item) {
            $productId = (int) $item['product_id'];
            $product = $products->get($productId);

            $unitPrice = (string) $product->sale_price;
            $lineBase = (float) $unitPrice;

            [$itemDiscountAmount] = $this->computeDiscount(
                $lineBase,
                Arr::get($item, 'discount_type'),
                Arr::get($item, 'discount_value'),
            );

            $subtotal = bcadd($subtotal, (string) $lineBase, 2);
            $discountTotal = bcadd($discountTotal, number_format($itemDiscountAmount, 2, '.', ''), 2);
        }

        $afterItemDiscounts = max(0, (float) bcsub($subtotal, $discountTotal, 2));
        [$globalDiscountAmount] = $this->computeDiscount(
            $afterItemDiscounts,
            Arr::get($payload, 'global_discount_type'),
            Arr::get($payload, 'global_discount_value'),
        );

        $discountTotal = bcadd($discountTotal, number_format($globalDiscountAmount, 2, '.', ''), 2);

        $afterManualDiscounts = max(0, $afterItemDiscounts - $globalDiscountAmount);

        $couponDiscountTotal = '0.00';
        $couponCode = trim((string) Arr::get($payload, 'coupon_code', ''));

        if ($couponCode !== '') {
            $this->assertCouponPermission($user);

            $coupon = Coupon::query()->where('code', $couponCode)->first();

            if (! $coupon || ! $coupon->active) {
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

            if ($coupon->max_redemptions !== null) {
                $used = CouponRedemption::where('coupon_id', $coupon->id)->count();
                if ($used >= $coupon->max_redemptions) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['El cupón alcanzó su límite de uso.'],
                    ]);
                }
            }

            $customerId = Arr::get($payload, 'customer_id');
            $customerId = $customerId !== null ? (int) $customerId : null;
            if ($customerId && $coupon->max_redemptions_per_customer !== null) {
                $usedByCustomer = CouponRedemption::where('coupon_id', $coupon->id)
                    ->where('customer_id', $customerId)
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

        // Fidelidad (solo si hay customer_id)
        $loyaltyApplied = false;
        $loyaltyDiscountTotal = '0.00';

        $customerId = Arr::get($payload, 'customer_id');
        $customerId = $customerId !== null ? (int) $customerId : null;

        if ($customerId) {
            $customer = Customer::query()->find($customerId);
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
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'coupon_discount_total' => $couponDiscountTotal,
            'loyalty_discount_total' => $loyaltyDiscountTotal,
            'loyalty_applied' => $loyaltyApplied,
            'total' => number_format($total, 2, '.', ''),
        ];
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

    private function payloadHasAnyManualDiscount(array $payload): bool
    {
        $globalType = Arr::get($payload, 'global_discount_type');
        $globalValue = Arr::get($payload, 'global_discount_value');

        if (! empty($globalType) && (float) $globalValue > 0) {
            return true;
        }

        foreach (Arr::get($payload, 'items', []) as $item) {
            if (! empty($item['discount_type']) && (float) ($item['discount_value'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

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
}
