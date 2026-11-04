<?php

namespace App\Actions\Sales;

use Illuminate\Validation\ValidationException;

class AssertGlobalDiscountQuantity
{
    public static function validate(array $payload): void
    {
        if (empty($payload['global_discount_type']) || (float) ($payload['global_discount_value'] ?? 0) <= 0) {
            return;
        }

        $units = collect($payload['items'] ?? [])
            ->sum(fn (array $item): int => max(1, (int) ($item['qty'] ?? 1)));

        if ($units < 3) {
            throw ValidationException::withMessages([
                'global_discount_value' => ['El descuento global requiere al menos 3 prendas en el carrito. Para una o dos prendas, usa el descuento de cada prenda.'],
            ]);
        }
    }
}
