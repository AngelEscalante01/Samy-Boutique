<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'print_sale_id' => fn () => $request->session()->get('print_sale_id'),
                'print_layaway_id' => fn () => $request->session()->get('print_layaway_id'),
                'print_layaway_payment_id' => fn () => $request->session()->get('print_layaway_payment_id'),
                'print_layaway_closed_id' => fn () => $request->session()->get('print_layaway_closed_id'),
            ],
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->getRoleNames()->values()->all(),
                        'role' => $user->getRoleNames()->first(),
                        'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    ]
                    : null,
            ],
            // Ejemplo simple y explícito: permisos que usamos en el frontend.
            // (Evita exponer todos los permisos y mantiene bajo el costo.)
            'logoUrl' => asset('logo-source.png'),
            'permissions' => [
                'pos.view' => $user?->can('pos.view') ?? false,

                'sales.view' => $user?->can('sales.view') ?? false,
                'sales.cancel' => $user?->can('sales.cancel') ?? false,

                'reports.view' => $user?->can('reports.view') ?? false,
                'cash_cuts.create' => $user?->can('cash_cuts.create') ?? false,

                'sales.create' => $user?->can('sales.create') ?? false,
                'sales.apply_coupon' => $user?->can('sales.apply_coupon') ?? false,
                'sales.apply_discount_basic' => $user?->can('sales.apply_discount_basic') ?? false,
                'sales.apply_discount_high' => $user?->can('sales.apply_discount_high') ?? false,

                'customers.view' => $user?->can('customers.view') ?? false,
                'customers.create' => $user?->can('customers.create') ?? false,
                'customers.update' => $user?->can('customers.update') ?? false,

                'catalogs.manage' => $user?->can('catalogs.manage') ?? false,
                'coupons.manage' => $user?->can('coupons.manage') ?? false,
                'settings.manage' => $user?->can('settings.manage') ?? false,
                'users.manage' => $user?->can('users.manage') ?? false,

                'products.view' => $user?->can('products.view') ?? false,
                'products.create' => $user?->can('products.create') ?? false,
                'products.update' => $user?->can('products.update') ?? false,
                'products.delete_images' => $user?->can('products.delete_images') ?? false,
                'products.view_purchase_price' => $user?->can('products.view_purchase_price') ?? false,
            ],
        ];
    }
}
