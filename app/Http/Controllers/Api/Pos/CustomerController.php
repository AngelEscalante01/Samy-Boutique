<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreApiCustomerRequest;
use App\Http\Requests\UpdateApiCustomerRequest;
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $customers = Customer::query()
            ->when($query !== '', function ($builder) use ($query) {
                $like = "%{$query}%";

                $builder->where(function ($subQuery) use ($like) {
                    $subQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
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

        return $this->successResponse([
            'customer' => (new CustomerResource($customer))->resolve($request),
            'recent_sales' => $recentSales,
        ], 'Cliente obtenido correctamente.');
    }

    public function store(StoreApiCustomerRequest $request): JsonResponse
    {
        $this->ensurePermission($request, 'customers.create');

        $data = $request->validated();

        $customer = Customer::query()->create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
        ]);

        return $this->successResponse([
            'customer' => (new CustomerResource($customer))->resolve($request),
        ], 'Cliente creado correctamente.', 201);
    }

    public function update(UpdateApiCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->ensurePermission($request, 'customers.update');

        $data = $request->validated();

        $customer->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
        ]);

        return $this->successResponse([
            'customer' => (new CustomerResource($customer->fresh()))->resolve($request),
        ], 'Cliente actualizado correctamente.');
    }
}
