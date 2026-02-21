<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $q      = $request->string('q')->toString();
        $active = $request->string('active')->toString();

        $customers = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', (int) $active === 1))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'filters' => [
                'q'      => $q,
                'active' => $active,
            ],
            'customers' => CustomerResource::collection($customers),
            'can' => [
                'create' => $user->can('customers.create'),
                'update' => $user->can('customers.update'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $customer = Customer::create([
            'name'   => $validated['name'],
            'email'  => $validated['email'] ?? null,
            'phone'  => $validated['phone'] ?? null,
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente creado.');
    }

    public function show(Customer $customer): Response
    {
        $user = request()->user();

        $recentSales = $customer->sales()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'total', 'status', 'created_at']);

        $recentLayaways = $customer->layaways()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'status', 'subtotal', 'paid_total']);

        $totalSpent = $customer->sales()->sum('total');
        $lastSale   = $customer->sales()->latest()->value('created_at');

        return Inertia::render('Customers/Show', [
            'customer'       => (new CustomerResource($customer))->resolve(),
            'recentSales'    => $recentSales,
            'recentLayaways' => $recentLayaways,
            'stats' => [
                'total_spent' => number_format((float) $totalSpent, 2, '.', ''),
                'last_sale'   => $lastSale?->toISOString(),
            ],
            'can' => [
                'update' => $user->can('customers.update'),
            ],
        ]);
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => (new CustomerResource($customer))->resolve(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validated();

        $customer->update([
            'name'   => $validated['name'],
            'email'  => $validated['email'] ?? null,
            'phone'  => $validated['phone'] ?? null,
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente actualizado.');
    }
}