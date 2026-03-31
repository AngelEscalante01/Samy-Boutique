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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LayawayController extends Controller
{
    public function index(Request $request): Response
    {
        $user   = $request->user();
        $status = (string) $request->query('status', 'open');
        $q      = (string) $request->query('q', '');
        $vigencia = (string) $request->query('vigencia', 'all');
        $upcomingDays = max(1, (int) $request->query('upcoming_days', 7));

        $query = Layaway::query()->with(['customer', 'creator']);

        if (in_array($status, ['open', 'liquidated', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($sub) use ($q, $like) {
                $sub->where('id', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        $today = Carbon::today();
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
                'q'      => $q,
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
        $products  = Product::query()
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
            ->get(['id', 'name', 'phone']);

        return Inertia::render('Layaways/Create', [
            'products'  => ProductResource::collection($products)->resolve(),
            'customers' => $customers,
        ]);
    }

    public function show(Layaway $layaway): Response
    {
        $user = request()->user();
        $layaway->load([
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
            'payments',
            'customer',
            'creator',
            'sale',
        ]);

        return Inertia::render('Layaways/Show', [
            'layaway' => $layaway,
            'can' => [
                'cancel' => $user->can('layaways.cancel'),
            ],
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