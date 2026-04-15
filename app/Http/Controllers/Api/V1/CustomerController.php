<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'customers.view');

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'in:0,1'],
            'segment' => ['nullable', 'in:frequent,new'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $active = (string) ($validated['active'] ?? '');
        $segment = (string) ($validated['segment'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 20);

        $customers = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', (int) $active === 1))
            ->when($segment === 'frequent', fn ($query) => $query->where('purchases_count', '>=', 5))
            ->when($segment === 'new', fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $items = CustomerResource::collection($customers->getCollection())->resolve($request);

        return $this->paginatedResponse($customers, $items, 'Clientes obtenidos correctamente.');
    }

    public function show(Customer $customer, Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'customers.view');

        $recentSales = $customer->sales()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'total', 'status', 'created_at']);

        $recentLayaways = $customer->layaways()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'status', 'subtotal', 'paid_total', 'created_at']);

        $totalSpent = (float) $customer->sales()->sum('total');
        $lastSale = $customer->sales()->latest()->value('created_at');

        return $this->successResponse([
            'customer' => (new CustomerResource($customer))->resolve(),
            'recent_sales' => $recentSales,
            'recent_layaways' => $recentLayaways,
            'stats' => [
                'total_spent' => number_format($totalSpent, 2, '.', ''),
                'last_sale' => $lastSale?->toISOString(),
            ],
        ], 'Cliente obtenido correctamente.');
    }
}
