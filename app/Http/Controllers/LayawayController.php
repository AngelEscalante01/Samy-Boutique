<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddLayawayPaymentRequest;
use App\Http\Requests\LiquidateLayawayRequest;
use App\Http\Requests\StoreLayawayRequest;
use App\Http\Requests\UpdateLayawayVigenciaRequest;
use App\Http\Resources\ProductResource;
use App\Models\Customer;
use App\Models\Layaway;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\LayawayService;
use App\Services\LayawayAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LayawayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = (string) $request->query('status', 'open');
        $q = (string) $request->query('q', '');
        $vigencia = (string) $request->query('vigencia', 'all');
        $upcomingDays = max(1, (int) $request->query('upcoming_days', 7));

        $query = Layaway::query()->with(['customer', 'creator']);

        if (in_array($status, ['open', 'liquidated', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($sub) use ($like) {
                $sub->where('id', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        $today = Carbon::today((string) config('firebase.layaway_timezone', 'America/Mexico_City'));
        if ($vigencia === 'expired') {
            $query->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<', $today);
        } elseif ($vigencia === 'upcoming') {
            $query->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '>=', $today)
                ->whereDate('fecha_vencimiento', '<=', $today->copy()->addDays($upcomingDays));
        }

        $layaways = $query
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Layaways/Index', [
            'filters' => [
                'status' => $status,
                'q' => $q,
                'vigencia' => $vigencia,
                'upcoming_days' => $upcomingDays,
            ],
            'layaways' => $layaways,
            'can' => [
                'create' => $user->can('layaways.create'),
            ],
        ]);
    }

    public function create(InventoryService $inventoryService): Response
    {
        $products = Product::query()
            ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->with([
                'images',
                'category',
                'variants' => fn ($query) => $query
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->with(['size', 'color']),
            ])
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'photo_path', 'photo_generation'])
            ->map(fn ($customer) => [
                'id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone,
                'photo_url' => request()->user()->can('customers.view') && $customer->photo_path
                    ? route('customers.photo', ['customer' => $customer->id, 'generation' => $customer->photo_generation]) : null,
            ]);

        return Inertia::render('Layaways/Create', [
            'products' => ProductResource::collection($products)->resolve(),
            'customers' => $customers,
            'discountPolicy' => $this->discountPolicy(request()->user()),
        ]);
    }

    public function show(Layaway $layaway): Response
    {
        $user = request()->user();
        $layaway->load([
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
            'payments.creator:id,name',
            'refunds',
            'adjustments',
            'customer',
            'creator',
            'sale',
        ]);

        return Inertia::render('Layaways/Show', [
            'layaway' => $layaway,
            'customerPhotoUrl' => $user->can('customers.view') && $layaway->customer?->photo_path
                ? route('customers.photo', ['customer' => $layaway->customer_id, 'generation' => $layaway->customer->photo_generation]) : null,
            'can' => [
                'cancel' => $user->can('layaways.cancel'),
                'edit' => $user->can('layaways.create'),
                'refund' => $user->hasRole('gerente'),
                'deliver' => $user->hasRole('gerente'),
            ],
        ]);
    }

    public function edit(Layaway $layaway, InventoryService $inventoryService): Response
    {
        abort_unless($layaway->status === 'open' || ($layaway->status === 'liquidated' && $layaway->delivery_tracking_enabled && ! $layaway->delivered_at && $layaway->sale?->status === 'completed'), 409);
        $layaway->load(['items.product.images', 'items.variant.size', 'items.variant.color', 'customer']);
        $products = Product::query()->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->with(['images', 'category', 'variants' => fn ($query) => $query->where('active', true)->where('stock', '>', 0)->with(['size', 'color'])])
            ->orderBy('name')->get();

        return Inertia::render('Layaways/Edit', [
            'layaway' => $layaway,
            'products' => ProductResource::collection($products)->resolve(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'discountPolicy' => $this->discountPolicy(request()->user()),
        ]);
    }

    private function discountPolicy(\App\Models\User $user): array
    {
        return [
            'canApply' => $user->can('sales.apply_discount_basic') || $user->can('sales.apply_discount_high'),
            'canHigh' => $user->can('sales.apply_discount_high'),
            'basicMaxPercent' => (float) config('samy.discount_basic_max_percent', 10),
            'basicMaxAmount' => (float) config('samy.discount_basic_max_amount', 100),
        ];
    }

    public function previewAdjustment(Layaway $layaway, Request $request, LayawayAdjustmentService $service)
    {
        return response()->json($service->preview($layaway, $this->adjustmentPayload($request), $request->user()));
    }

    public function updateAdjustment(Layaway $layaway, Request $request, LayawayAdjustmentService $service)
    {
        $payload = $this->adjustmentPayload($request);
        $service->update($layaway, $payload, $request->user());

        return redirect()->route('layaways.show', $layaway)->with('success', 'Apartado actualizado. Revisa el saldo y cualquier excedente.');
    }

    public function refund(Layaway $layaway, Request $request, LayawayAdjustmentService $service)
    {
        $payload = $request->validate([
            'operation_key' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9._:-]{16,100}\z/'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,transfer,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $service->refund($layaway, $payload, $request->user());

        return redirect()->route('layaways.show', $layaway)->with('success', 'Devolución registrada como movimiento separado.');
    }

    public function deliver(Layaway $layaway, Request $request)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($layaway) {
            $locked = Layaway::query()->lockForUpdate()->findOrFail($layaway->id);
            if ($locked->status !== 'liquidated' || ! $locked->delivery_tracking_enabled || $locked->sale?->status !== 'completed' || $locked->delivered_at || bccomp($locked->balance, '0.00', 2) !== 0 || bccomp($locked->excess, '0.00', 2) !== 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['layaway' => 'La entrega requiere un apartado liquidado, saldado y sin excedente.']);
            }
            $locked->update(['delivered_at' => now()]);
        });

        return redirect()->route('layaways.show', $layaway)->with('success', 'Entrega confirmada.');
    }

    private function adjustmentPayload(Request $request): array
    {
        return $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999999'],
            'global_discount_type' => ['nullable', 'in:percent,amount'],
            'global_discount_value' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
    }

    public function store(StoreLayawayRequest $request, LayawayService $service)
    {
        $layaway = $service->create($request->validated(), $request->user());

        if ($request->header('X-Inertia')) {
            return redirect()
                ->route('layaways.show', $layaway->id)
                ->with('success', 'Apartado creado.')
                ->with('print_layaway_id', $layaway->id);
        }

        return response()->json(['layaway' => $layaway], 201);
    }

    public function addPayment(Layaway $layaway, AddLayawayPaymentRequest $request, LayawayService $service)
    {
        $payment = $service->addPayment($layaway, $request->validated(), $request->user());

        if (! $request->header('X-Inertia') || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Abono registrado correctamente',
                'layaway_id' => (int) $layaway->id,
                'payment_id' => (int) $payment->id,
            ], 201);
        }

        return redirect()
            ->route('layaways.show', $layaway->id)
            ->with('success', 'Abono registrado.')
            ->with('print_layaway_payment_id', $payment->id);
    }

    public function liquidate(Layaway $layaway, LiquidateLayawayRequest $request, LayawayService $service)
    {
        $sale = $service->liquidate($layaway, $request->validated(), $request->user());

        return redirect()
            ->route('layaways.show', $layaway->id)
            ->with('success', 'Apartado liquidado.')
            ->with('print_layaway_closed_id', $layaway->id)
            ->with('print_sale_id', $sale->id);
    }

    public function cancel(Layaway $layaway, LayawayService $service)
    {
        $service->cancel($layaway);

        return redirect()->route('layaways.show', $layaway->id)->with('success', 'Apartado cancelado.');
    }

    public function updateVigencia(Layaway $layaway, UpdateLayawayVigenciaRequest $request, LayawayService $service)
    {
        $validated = $request->validated();
        $updatedLayaway = $service->updateVigencia($layaway, (int) $validated['vigencia_dias']);

        if (! $request->header('X-Inertia') || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Vigencia actualizada correctamente.',
                'layaway_id' => (int) $updatedLayaway->id,
                'vigencia_dias' => (int) $updatedLayaway->vigencia_dias,
                'fecha_vencimiento' => $updatedLayaway->fecha_vencimiento?->toDateString(),
            ]);
        }

        return redirect()
            ->route('layaways.show', $layaway->id)
            ->with('success', 'Vigencia actualizada.');
    }
}
