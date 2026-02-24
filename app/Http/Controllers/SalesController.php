<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Actions\Sales\PreviewSaleTotalsAction;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\PreviewSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with([
            'customer:id,name,phone',
            'payments:id,sale_id,method,amount,reference',
            'creator:id,name',
        ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->q;
                $q->where(function ($q) use ($term) {
                    $q->where('id', 'like', "{$term}%")
                      ->orWhereHas('customer', fn ($c) =>
                          $c->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                      );
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('from'),   fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),     fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest();

        $sales = $query->paginate(20)->withQueryString();

        $today = now()->toDateString();
        $quickStats = [
            'today_count'     => Sale::whereDate('created_at', $today)->where('status', 'completed')->count(),
            'today_total'     => (float) Sale::whereDate('created_at', $today)->where('status', 'completed')->sum('total'),
            'today_cancelled' => Sale::whereDate('created_at', $today)->where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Sales/Index', [
            'sales'      => $sales,
            'filters'    => $request->only(['q', 'status', 'from', 'to']),
            'quickStats' => $quickStats,
            'can'        => [
                'cancel' => $request->user()->can('sales.cancel'),
            ],
        ]);
    }

    public function show(Sale $sale, Request $request)
    {
        $sale->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'payments',
            'coupon:id,code,discount_type,discount_value',
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
            'canceledBy:id,name',
        ]);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
            'can'  => [
                'cancel' => $request->user()->can('sales.cancel.sale', $sale) && $sale->status === 'completed',
            ],
        ]);
    }

    public function cancel(Sale $sale, CancelSaleRequest $request, InventoryService $inventoryService)
    {
        abort_unless($request->user()->can('sales.cancel.sale', $sale), 403);

        DB::transaction(function () use ($sale, $request, $inventoryService) {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->with('items:id,sale_id,product_id,product_variant_id,qty,quantity')
                ->firstOrFail();

            if ($lockedSale->status === 'cancelled' || $lockedSale->canceled_at !== null) {
                throw ValidationException::withMessages([
                    'sale' => ['La venta ya fue cancelada.'],
                ]);
            }

            if ($lockedSale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'sale' => ['Solo se pueden cancelar ventas pagadas.'],
                ]);
            }

            $data = $request->validated();

            $lockedSale->update([
                'status' => 'cancelled',
                'canceled_at' => now(),
                'canceled_by' => $request->user()->id,
                'cancel_reason' => $data['cancel_reason'],
                'cancellation_reason' => $data['cancel_reason'],
                'cancel_type' => $data['cancel_type'],
                'inventory_action' => $data['inventory_action'],
                'return_condition' => $data['cancel_type'] === 'devolucion' ? ($data['return_condition'] ?? null) : null,
            ]);

            if ($data['inventory_action'] === 'regresar_disponible') {
                $variantIds = $lockedSale->items
                    ->pluck('product_variant_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($variantIds->isNotEmpty()) {
                    $variants = ProductVariant::query()
                        ->whereIn('id', $variantIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($lockedSale->items as $item) {
                        $variantId = (int) ($item->product_variant_id ?? 0);
                        if ($variantId <= 0) {
                            continue;
                        }

                        $variant = $variants->get($variantId);
                        if (! $variant) {
                            continue;
                        }

                        $qty = (int) ($item->qty ?? $item->quantity ?? 1);
                        $inventoryService->incrementVariantStock($variant, max(1, $qty));
                    }

                    $inventoryService->updateManySoldOutAt(
                        $variants->pluck('product_id')->map(fn ($id) => (int) $id)
                    );
                } else {
                    Product::query()
                        ->whereIn('id', $lockedSale->items->pluck('product_id')->all())
                        ->where('status', 'vendido')
                        ->update([
                            'status' => 'disponible',
                            'sold_at' => null,
                            'sold_out_at' => null,
                        ]);
                }
            }

            if ($data['inventory_action'] === 'marcar_danado') {
                Product::query()
                    ->whereIn('id', $lockedSale->items->pluck('product_id')->all())
                    ->update([
                        'status' => 'cancelado',
                    ]);
            }
        });

        return back()->with('success', "Venta #{$sale->id} cancelada correctamente.");
    }

    public function store(StoreSaleRequest $request, CreateSaleAction $action)
    {
        $sale = $action->execute($request->validated(), $request->user());

        $sale->load(['items', 'payments', 'coupon', 'customer', 'creator']);

        if ($request->header('X-Inertia')) {
            return redirect()
                ->route('pos.index')
                ->with('success', 'Venta registrada.');
        }

        return response()->json(['sale' => $sale], 201);
    }

    public function preview(PreviewSaleRequest $request, PreviewSaleTotalsAction $action)
    {
        $totals = $action->execute($request->validated(), $request->user());

        return response()->json([
            'totals' => $totals,
        ]);
    }
}
