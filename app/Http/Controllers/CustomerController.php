<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\ProductImageReferenceGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly ProductImageReferenceGuard $referenceGuard) {}

    public function photo(Customer $customer)
    {
        abort_unless($customer->photo_path && in_array($customer->photo_disk, ['local', 'r2_private'], true), 404);
        $disk = Storage::disk($customer->photo_disk);
        abort_unless($disk->exists($customer->photo_path), 404);

        return response()->stream(function () use ($disk, $customer) {
            $stream = $disk->readStream($customer->photo_path);
            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $disk->mimeType($customer->photo_path) ?: 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function uploadPhoto(Request $request, Customer $customer): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096']]);
        $diskName = config('media.customer_photo_disk', 'local');
        abort_unless(in_array($diskName, ['local', 'r2_private'], true), 500);
        $file = $request->file('photo');
        $generation = (string) Str::uuid();
        $path = $file->storeAs("samy/customers/{$customer->id}", $generation.'.'.$file->extension(), $diskName);
        if (! $path || ! Storage::disk($diskName)->exists($path)) {
            abort(500, 'No se pudo guardar la fotografía privada.');
        }

        try {
            $old = DB::transaction(function () use ($customer, $diskName, $path, $generation) {
                $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
                $old = [$locked->photo_disk, $locked->photo_path];
                $locked->update(['photo_disk' => $diskName, 'photo_path' => $path, 'photo_generation' => $generation]);

                return $old;
            });
        } catch (\Throwable $exception) {
            Storage::disk($diskName)->delete($path);
            throw $exception;
        }
        $this->deleteOldPhoto($old);

        return back()->with('success', 'Fotografía actualizada.');
    }

    public function deletePhoto(Customer $customer): RedirectResponse
    {
        $old = DB::transaction(function () use ($customer) {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $old = [$locked->photo_disk, $locked->photo_path];
            $locked->update(['photo_disk' => null, 'photo_path' => null, 'photo_generation' => null]);

            return $old;
        });
        $this->deleteOldPhoto($old);

        return back()->with('success', 'Fotografía eliminada.');
    }

    private function deleteOldPhoto(array $old): void
    {
        [$disk, $path] = $old;
        if ($path && in_array($disk, ['local', 'r2_private'], true)
            && ! $this->referenceGuard->reasonForObject($disk, $path)) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

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
            ->get(['id', 'status', 'subtotal', 'paid_total', 'manual_discount_total', 'loyalty_discount_total', 'refunded_total']);

        $totalSpent = $customer->sales()->where('status', 'completed')->sum('total');
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
