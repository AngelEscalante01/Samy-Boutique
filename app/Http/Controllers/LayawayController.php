<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddLayawayPaymentRequest;
use App\Http\Requests\LiquidateLayawayRequest;
use App\Http\Requests\StoreLayawayRequest;
use App\Http\Resources\ProductResource;
use App\Models\Customer;
use App\Models\Layaway;
use App\Models\Product;
use App\Services\LayawayService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LayawayController extends Controller
{
    public function index(Request $request): Response
    {
        $user   = $request->user();
        $status = (string) $request->query('status', 'open');
        $q      = (string) $request->query('q', '');

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

        $layaways = $query
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Layaways/Index', [
            'filters' => [
                'status' => $status,
                'q'      => $q,
            ],
            'layaways' => $layaways,
            'can' => [
                'create' => $user->can('layaways.create'),
            ],
        ]);
    }

    public function create(): Response
    {
        $products  = Product::query()
            ->where('status', 'disponible')
            ->with(['size', 'color', 'images', 'category'])
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
        $layaway->load(['items.product.size', 'items.product.color', 'items.product.images', 'payments', 'customer', 'creator', 'sale']);

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
            return redirect()->route('layaways.show', $layaway->id)->with('success', 'Apartado creado.');
        }

        return response()->json(['layaway' => $layaway], 201);
    }

    public function addPayment(Layaway $layaway, AddLayawayPaymentRequest $request, LayawayService $service)
    {
        $service->addPayment($layaway, $request->validated());

        return redirect()->route('layaways.show', $layaway->id)->with('success', 'Abono registrado.');
    }

    public function liquidate(Layaway $layaway, LiquidateLayawayRequest $request, LayawayService $service)
    {
        $service->liquidate($layaway, $request->validated(), $request->user());

        return redirect()->route('layaways.show', $layaway->id)->with('success', 'Apartado liquidado.');
    }

    public function cancel(Layaway $layaway, LayawayService $service)
    {
        $service->cancel($layaway);

        return redirect()->route('layaways.show', $layaway->id)->with('success', 'Apartado cancelado.');
    }
}