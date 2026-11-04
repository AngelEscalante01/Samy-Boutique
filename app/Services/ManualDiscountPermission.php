<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class ManualDiscountPermission
{
    public static function assertAllowed(User $user, ?string $type, float $value): void
    {
        if ($type === null || $type === '' || $value <= 0) {
            return;
        }

        if (! $user->can('sales.apply_discount_basic') && ! $user->can('sales.apply_discount_high')) {
            throw ValidationException::withMessages([
                'global_discount_value' => 'No tienes permiso para aplicar descuentos.',
            ]);
        }

        $high = $type === 'percent'
            ? $value > (float) config('samy.discount_basic_max_percent', 10)
            : $value > (float) config('samy.discount_basic_max_amount', 100);
        if ($high && ! $user->can('sales.apply_discount_high')) {
            throw ValidationException::withMessages([
                'global_discount_value' => 'Este descuento requiere permiso de descuento alto.',
            ]);
        }
    }
}
