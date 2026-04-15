<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Sales\CreateSaleAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreApiSaleRequest;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['sales.view', 'sales.create']);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'folio' => ['nullable', 'string', 'max:64'],
            'customer' => ['nullable', 'string', 'max:120'],
            'cashier' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $from = (string) ($validated['from'] ?? '');
        $to = (string) ($validated['to'] ?? '');
        $folio = trim((string) ($validated['folio'] ?? ''));
        $customer = trim((string) ($validated['customer'] ?? ''));
        $cashier = trim((string) ($validated['cashier'] ?? ''));
        $q = trim((string) ($validated['q'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 20);

        $sales = Sale::query()
            ->select([
                'id',
                'customer_id',
                'created_by',
                'status',
                'subtotal',
                'discount_total',
                'coupon_discount_total',
                'loyalty_discount_total',
                'total',
                'cash_received',
                'change',
                'created_at',
            ])
            ->with([
                'customer:id,name,phone,email',
                'creator:id,name',
                'payments:id,sale_id,method,amount',
            ])
            ->withCount('items')
            ->when(in_array($status, ['completed', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($folio !== '', function ($query) use ($folio) {
                $digits = preg_replace('/\D+/', '', $folio) ?? '';

                $query->where(function ($subQuery) use ($digits, $folio) {
                    if ($digits !== '') {
                        $subQuery->where('id', (int) $digits)
                            ->orWhereRaw('CAST(id AS CHAR) like ?', ["%{$digits}%"]);
                    } else {
                        $subQuery->whereRaw('CAST(id AS CHAR) like ?', ["%{$folio}%"]);
                    }
                });
            })
            ->when($customer !== '', function ($query) use ($customer) {
                $like = "%{$customer}%";

                $query->whereHas('customer', function ($customerQuery) use ($like) {
                    $customerQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($cashier !== '', function ($query) use ($cashier) {
                $like = "%{$cashier}%";

                $query->whereHas('creator', function ($creatorQuery) use ($like) {
                    $creatorQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $digits = preg_replace('/\D+/', '', $q) ?? '';

                $query->where(function ($subQuery) use ($like, $digits) {
                    if ($digits !== '') {
                        $subQuery->where('id', (int) $digits)
                            ->orWhereRaw('CAST(id AS CHAR) like ?', ["%{$digits}%"]);
                    }

                    $subQuery
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                            ->where('name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('email', 'like', $like))
                        ->orWhereHas('creator', fn ($creatorQuery) => $creatorQuery
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = $sales->getCollection()
            ->map(fn (Sale $sale) => $this->serializeSaleSummary($sale))
            ->values()
            ->all();

        return $this->paginatedResponse($sales, $items, 'Historial de ventas obtenido correctamente.');
    }

    public function show(Sale $sale, Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['sales.view', 'sales.create']);

        $sale->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'canceledBy:id,name',
            'coupon:id,code,discount_type,discount_value',
            'payments:id,sale_id,method,amount,reference,created_at',
            'items:id,sale_id,product_id,product_variant_id,qty,quantity,name,sku,unit_price,discount_type,discount_value,discount_amount,line_total,final_price',
            'items.variant:id,product_id,size_id,color_id,sku,sale_price,stock',
            'items.variant.size:id,name',
            'items.variant.color:id,name,hex',
            'items.product:id,name,sku,status',
        ]);

        return $this->successResponse(
            $this->serializeSale($sale),
            'Detalle de venta obtenido correctamente.'
        );
    }

    public function ticket(Sale $sale, Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['sales.view', 'sales.create']);

        $sale->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'payments:id,sale_id,method,amount,reference,created_at',
            'items:id,sale_id,product_id,product_variant_id,qty,quantity,name,sku,unit_price,discount_type,discount_value,discount_amount,line_total,final_price',
            'items.variant:id,product_id,size_id,color_id,sku,sale_price',
            'items.variant.size:id,name',
            'items.variant.color:id,name,hex',
            'items.product:id,name,sku',
        ]);

        return $this->successResponse(
            $this->serializeTicket($sale),
            'Ticket de venta obtenido correctamente.'
        );
    }

    public function store(StoreApiSaleRequest $request, CreateSaleAction $action): JsonResponse
    {
        $this->ensurePermission($request, 'sales.create');

        $validated = $request->validated();

        $payload = [
            'customer_id' => $validated['customer_id'] ?? null,
            'items' => $validated['items'],
            'global_discount_type' => $validated['global_discount_type'] ?? null,
            'global_discount_value' => $validated['global_discount_value'] ?? null,
            'coupon_code' => $validated['coupon_code'] ?? null,
            'payments' => $validated['payments'],
            'dinero_recibido' => $validated['dinero_recibido'],
        ];

        $sale = $action->execute($payload, $request->user());

        $sale->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'payments',
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
        ]);

        return $this->successResponse([
            'sale' => $this->serializeSale($sale),
            'ticket_context' => [
                'observaciones' => $validated['observaciones'] ?? null,
                'sucursal' => [
                    'id' => $validated['sucursal_id'] ?? null,
                    'nombre' => $validated['sucursal_nombre'] ?? null,
                ],
            ],
        ], 'Venta registrada correctamente.', 201);
    }

    private function serializeSale(Sale $sale): array
    {
        return [
            'id' => (int) $sale->id,
            'folio' => 'VE-'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT),
            'status' => $sale->status,
            'subtotal' => (float) $sale->subtotal,
            'discount_total' => (float) $sale->discount_total,
            'coupon_discount_total' => (float) $sale->coupon_discount_total,
            'loyalty_discount_total' => (float) $sale->loyalty_discount_total,
            'loyalty_applied' => (bool) $sale->loyalty_applied,
            'total' => (float) $sale->total,
            'cash_received' => (float) ($sale->cash_received ?? 0),
            'change' => (float) ($sale->change ?? 0),
            'coupon_code' => $sale->coupon_code,
            'global_discount_type' => $sale->global_discount_type,
            'global_discount_value' => $sale->global_discount_value !== null ? (float) $sale->global_discount_value : null,
            'created_at' => $sale->created_at?->toISOString(),
            'updated_at' => $sale->updated_at?->toISOString(),
            'canceled_at' => $sale->canceled_at?->toISOString(),
            'cancel_reason' => $sale->cancel_reason,
            'customer' => $sale->customer ? [
                'id' => (int) $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'email' => $sale->customer->email,
            ] : null,
            'cashier' => $sale->creator ? [
                'id' => (int) $sale->creator->id,
                'name' => $sale->creator->name,
            ] : null,
            'canceled_by' => $sale->canceledBy ? [
                'id' => (int) $sale->canceledBy->id,
                'name' => $sale->canceledBy->name,
            ] : null,
            'coupon' => $sale->coupon ? [
                'id' => (int) $sale->coupon->id,
                'code' => $sale->coupon->code,
                'discount_type' => $sale->coupon->discount_type,
                'discount_value' => (float) $sale->coupon->discount_value,
            ] : null,
            'items' => $sale->items->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => (int) ($item->product_variant_id ?? 0),
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'qty' => (int) ($item->qty ?? $item->quantity ?? 1),
                    'unit_price' => (float) $item->unit_price,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value !== null ? (float) $item->discount_value : null,
                    'discount_amount' => (float) $item->discount_amount,
                    'line_total' => (float) $item->line_total,
                    'final_price' => (float) $item->final_price,
                    'product' => $item->product ? [
                        'id' => (int) $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'status' => $item->product->status,
                    ] : null,
                    'variant' => $item->variant ? [
                        'id' => (int) $item->variant->id,
                        'sku' => $item->variant->sku,
                        'stock' => (int) $item->variant->stock,
                        'size' => $item->variant->size ? [
                            'id' => (int) $item->variant->size->id,
                            'name' => $item->variant->size->name,
                        ] : null,
                        'color' => $item->variant->color ? [
                            'id' => (int) $item->variant->color->id,
                            'name' => $item->variant->color->name,
                            'hex' => $item->variant->color->hex,
                        ] : null,
                    ] : null,
                ];
            })->values()->all(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => (int) $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
            ])->values()->all(),
        ];
    }

    private function serializeSaleSummary(Sale $sale): array
    {
        $paymentMethods = $sale->payments
            ->pluck('method')
            ->filter()
            ->map(fn ($method) => strtolower((string) $method))
            ->unique()
            ->values();

        return [
            'id' => (int) $sale->id,
            'folio' => 'VE-'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT),
            'status' => $sale->status,
            'created_at' => $sale->created_at?->toIso8601String(),
            'total' => round((float) $sale->total, 2),
            'cash_received' => round((float) ($sale->cash_received ?? 0), 2),
            'change' => round((float) ($sale->change ?? 0), 2),
            'items_count' => (int) ($sale->items_count ?? 0),
            'customer' => $sale->customer ? [
                'id' => (int) $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
            ] : null,
            'cashier' => $sale->creator ? [
                'id' => (int) $sale->creator->id,
                'name' => $sale->creator->name,
            ] : null,
            'payment' => [
                'primary_method' => (string) ($paymentMethods->first() ?? 'other'),
                'is_mixed' => $paymentMethods->count() > 1,
            ],
        ];
    }

    private function serializeTicket(Sale $sale): array
    {
        $items = $sale->items->map(function ($item) {
            $qty = (int) ($item->qty ?? $item->quantity ?? 1);
            $unitPrice = (float) $item->unit_price;
            $lineSubtotal = round($unitPrice * $qty, 2);
            $lineDiscount = (float) ($item->discount_amount ?? 0);
            $lineTotal = (float) ($item->line_total ?? $item->final_price ?? $lineSubtotal);

            return [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'variant_id' => (int) ($item->product_variant_id ?? 0),
                'name' => (string) ($item->name ?: ($item->product?->name ?? 'Producto')),
                'sku' => $item->sku,
                'qty' => $qty,
                'unit_price' => round($unitPrice, 2),
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value !== null ? (float) $item->discount_value : null,
                'discount_amount' => round($lineDiscount, 2),
                'line_subtotal' => round($lineSubtotal, 2),
                'line_total' => round($lineTotal, 2),
                'size' => $item->variant?->size ? [
                    'id' => (int) $item->variant->size->id,
                    'name' => $item->variant->size->name,
                ] : null,
                'color' => $item->variant?->color ? [
                    'id' => (int) $item->variant->color->id,
                    'name' => $item->variant->color->name,
                    'hex' => $item->variant->color->hex,
                ] : null,
            ];
        })->values();

        $payments = $sale->payments->map(fn ($payment) => [
            'id' => (int) $payment->id,
            'method' => $payment->method,
            'amount' => round((float) $payment->amount, 2),
            'reference' => $payment->reference,
            'created_at' => $payment->created_at?->toIso8601String(),
        ])->values();

        $paymentMethods = $payments
            ->pluck('method')
            ->filter()
            ->map(fn ($method) => strtolower((string) $method))
            ->unique()
            ->values();

        $observaciones = $sale->getAttributes()['observaciones'] ?? null;

        return [
            'business' => [
                'name' => (string) Setting::get('business.name', config('app.name', 'Samy Boutique')),
                'phone' => (string) Setting::get('business.phone', ''),
                'address' => (string) Setting::get('business.address', ''),
                'rfc' => (string) Setting::get('business.rfc', ''),
            ],
            'ticket' => [
                'sale_id' => (int) $sale->id,
                'folio' => 'VE-'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT),
                'date_iso' => $sale->created_at?->toIso8601String(),
                'date_print' => $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'status' => $sale->status,
            ],
            'cashier' => $sale->creator ? [
                'id' => (int) $sale->creator->id,
                'name' => $sale->creator->name,
            ] : null,
            'customer' => $sale->customer ? [
                'id' => (int) $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'email' => $sale->customer->email,
            ] : null,
            'items' => $items->all(),
            'totals' => [
                'subtotal' => round((float) $sale->subtotal, 2),
                'discount_total' => round((float) $sale->discount_total, 2),
                'coupon_discount_total' => round((float) $sale->coupon_discount_total, 2),
                'loyalty_discount_total' => round((float) $sale->loyalty_discount_total, 2),
                'total' => round((float) $sale->total, 2),
                'cash_received' => round((float) ($sale->cash_received ?? 0), 2),
                'change' => round((float) ($sale->change ?? 0), 2),
            ],
            'payments' => [
                'summary' => [
                    'primary_method' => (string) ($paymentMethods->first() ?? 'other'),
                    'is_mixed' => $paymentMethods->count() > 1,
                    'methods' => $paymentMethods->all(),
                    'received_total' => round((float) $payments->sum('amount'), 2),
                ],
                'details' => $payments->all(),
            ],
            'observaciones' => $observaciones,
            'escpos' => [
                'currency' => 'MXN',
                'paper_width_mm' => 58,
                'line_items' => $items->map(fn ($item) => [
                    'text' => $item['name'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['line_total'],
                ])->values()->all(),
            ],
        ];
    }
}
