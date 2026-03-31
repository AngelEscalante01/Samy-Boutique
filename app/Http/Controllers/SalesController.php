<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Actions\Sales\PreviewSaleTotalsAction;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\PreviewSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\LayawayPayment;
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
        $movementType = (string) $request->query('movement_type', '');
        $status = (string) $request->query('status', '');
        $paymentMethod = (string) $request->query('payment_method', '');
        $q = trim((string) $request->query('q', ''));
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        $salesMovements = DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->selectRaw('sales.id as movement_id')
            ->selectRaw("'venta' as movement_type")
            ->selectRaw("CONCAT('VE-', LPAD(sales.id, 5, '0')) as folio")
            ->selectRaw('sales.created_at as occurred_at')
            ->selectRaw("COALESCE(customers.name, 'Publico general') as customer_name")
            ->selectRaw('customers.phone as customer_phone')
            ->selectRaw('sales.total as amount')
            ->selectRaw('sales.status as movement_status')
            ->selectRaw("(
                SELECT CASE
                    WHEN COUNT(DISTINCT sp.method) = 0 THEN NULL
                    WHEN COUNT(DISTINCT sp.method) = 1 THEN MIN(sp.method)
                    ELSE 'mixed'
                END
                FROM sale_payments sp
                WHERE sp.sale_id = sales.id
            ) as payment_method")
            ->selectRaw("CONCAT('Venta VE-', LPAD(sales.id, 5, '0')) as reference")
            ->selectRaw('sales.id as sale_id')
            ->selectRaw('NULL as layaway_id')
            ->selectRaw('NULL as layaway_payment_id');

        $layawayPaymentMovements = DB::table('layaway_payments')
            ->join('layaways', 'layaways.id', '=', 'layaway_payments.layaway_id')
            ->leftJoin('customers', 'customers.id', '=', 'layaways.customer_id')
            ->selectRaw('layaway_payments.id as movement_id')
            ->selectRaw("'abono' as movement_type")
            ->selectRaw("CONCAT('AB-', LPAD(layaway_payments.id, 5, '0')) as folio")
            ->selectRaw('COALESCE(layaway_payments.paid_at, layaway_payments.created_at) as occurred_at')
            ->selectRaw("COALESCE(customers.name, 'Sin cliente') as customer_name")
            ->selectRaw('customers.phone as customer_phone')
            ->selectRaw('layaway_payments.amount as amount')
            ->selectRaw("'applied' as movement_status")
            ->selectRaw('layaway_payments.method as payment_method')
            ->selectRaw("CONCAT('Apartado AP-', LPAD(layaways.id, 5, '0')) as reference")
            ->selectRaw('NULL as sale_id')
            ->selectRaw('layaways.id as layaway_id')
            ->selectRaw('layaway_payments.id as layaway_payment_id');

        $movementsQuery = DB::query()
            ->fromSub($salesMovements->unionAll($layawayPaymentMovements), 'movements')
            ->when(in_array($movementType, ['venta', 'abono'], true), fn ($query) => $query->where('movement_type', $movementType))
            ->when(in_array($status, ['completed', 'cancelled', 'applied'], true), fn ($query) => $query->where('movement_status', $status))
            ->when(in_array($paymentMethod, ['cash', 'card', 'transfer', 'other', 'mixed'], true), fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($from !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $to))
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(function ($subQuery) use ($like) {
                    $subQuery->where('folio', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like)
                        ->orWhere('reference', 'like', $like);
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('movement_id');

        $movements = $movementsQuery->paginate(15)->withQueryString();

        $movementsPayload = [
            'data' => collect($movements->items())->map(function ($row) {
                $type = (string) $row->movement_type;
                $saleId = $row->sale_id !== null ? (int) $row->sale_id : null;
                $layawayId = $row->layaway_id !== null ? (int) $row->layaway_id : null;
                $layawayPaymentId = $row->layaway_payment_id !== null ? (int) $row->layaway_payment_id : null;

                return [
                    'id' => $type.'-'.(int) $row->movement_id,
                    'movement_id' => (int) $row->movement_id,
                    'movement_type' => $type,
                    'folio' => (string) $row->folio,
                    'created_at' => $row->occurred_at,
                    'customer' => [
                        'name' => $row->customer_name,
                        'phone' => $row->customer_phone,
                    ],
                    'total' => (float) $row->amount,
                    'payment_method' => $row->payment_method,
                    'status' => (string) $row->movement_status,
                    'reference' => $row->reference,
                    'sale_id' => $saleId,
                    'layaway_id' => $layawayId,
                    'layaway_payment_id' => $layawayPaymentId,
                    'detail_url' => $type === 'venta'
                        ? route('sales.show', ['sale' => $saleId])
                        : route('sales.movements.show', ['type' => 'abono', 'id' => $layawayPaymentId]),
                ];
            })->values()->all(),
            'links' => $movements->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'from' => $movements->firstItem(),
                'to' => $movements->lastItem(),
            ],
        ];

        $today = now()->toDateString();
        $todayCompleted = Sale::query()
            ->whereDate('created_at', $today)
            ->where('status', 'completed');

        $todayCount = (int) (clone $todayCompleted)->count();
        $todayTotal = (float) (clone $todayCompleted)->sum('total');
        $todayLayawayPayments = DB::table('layaway_payments')
            ->whereDate(DB::raw('COALESCE(paid_at, created_at)'), $today);

        $todayAbonosCount = (int) (clone $todayLayawayPayments)->count();
        $todayAbonosTotal = (float) ((clone $todayLayawayPayments)->sum('amount') ?? 0);

        $quickStats = [
            'today_count'     => $todayCount,
            'today_total'     => $todayTotal,
            'today_cancelled' => Sale::whereDate('created_at', $today)->where('status', 'cancelled')->count(),
            'today_avg_ticket' => $todayCount > 0 ? round($todayTotal / $todayCount, 2) : 0,
            'today_abonos_count' => $todayAbonosCount,
            'today_abonos_total' => round($todayAbonosTotal, 2),
        ];

        return Inertia::render('Sales/Index', [
            'sales'      => $movementsPayload,
            'filters'    => $request->only(['q', 'status', 'movement_type', 'payment_method', 'from', 'to']),
            'quickStats' => $quickStats,
            'can'        => [
                'cancel' => $request->user()->can('sales.cancel'),
            ],
        ]);
    }

    public function movementShow(string $type, int $id, Request $request)
    {
        if ($type !== 'abono') {
            abort(404);
        }

        $payment = LayawayPayment::query()
            ->with([
                'creator:id,name',
                'layaway:id,customer_id,created_by,subtotal,paid_total,status',
                'layaway.customer:id,name,phone,email',
                'layaway.creator:id,name',
            ])
            ->findOrFail($id);

        $payments = LayawayPayment::query()
            ->where('layaway_id', $payment->layaway_id)
            ->orderByRaw('COALESCE(paid_at, created_at)')
            ->orderBy('id')
            ->get(['id', 'amount']);

        $subtotal = (float) ($payment->layaway?->subtotal ?? 0);
        $balanceBefore = $subtotal;
        $balanceAfter = $subtotal;
        $runningPaid = 0.0;

        foreach ($payments as $row) {
            if ((int) $row->id === (int) $payment->id) {
                $balanceBefore = max(0.0, $subtotal - $runningPaid);
                $runningPaid += (float) $row->amount;
                $balanceAfter = max(0.0, $subtotal - $runningPaid);
                break;
            }

            $runningPaid += (float) $row->amount;
        }

        $movement = [
            'id' => (int) $payment->id,
            'folio' => 'AB-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT),
            'movement_type' => 'abono',
            'status' => 'applied',
            'created_at' => $payment->paid_at ?? $payment->created_at,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->method,
            'reference' => $payment->reference,
            'registered_by' => $payment->creator?->name ?? $payment->layaway?->creator?->name,
            'customer' => [
                'name' => $payment->layaway?->customer?->name,
                'phone' => $payment->layaway?->customer?->phone,
                'email' => $payment->layaway?->customer?->email,
            ],
            'layaway' => [
                'id' => (int) $payment->layaway_id,
                'folio' => 'AP-'.str_pad((string) $payment->layaway_id, 5, '0', STR_PAD_LEFT),
                'status' => $payment->layaway?->status,
                'url' => route('layaways.show', ['layaway' => $payment->layaway_id]),
            ],
            'balance_before' => round($balanceBefore, 2),
            'balance_after' => round($balanceAfter, 2),
        ];

        return Inertia::render('Sales/MovementShow', [
            'movement' => $movement,
            'can' => [
                'cancel' => false,
            ],
            'back_url' => route('sales.index', [
                'movement_type' => 'abono',
            ]),
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
                ->with('success', 'Venta registrada.')
                ->with('print_sale_id', $sale->id);
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
