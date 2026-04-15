<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sales\CreateSaleAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'sales.view');
        
            $validated = $request->validate([
                'status' => ['nullable', 'in:completed,cancelled'],
                'q' => ['nullable', 'string', 'max:120'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d'],
                'customer_id' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $status = (string) ($validated['status'] ?? '');
            $q = trim((string) ($validated['q'] ?? ''));
            $from = (string) ($validated['from'] ?? '');
            $to = (string) ($validated['to'] ?? '');
            $customerId = (int) ($validated['customer_id'] ?? 0);
            $perPage = (int) ($validated['per_page'] ?? 20);

        $sales = Sale::query()
            ->with(['customer:id,name,phone,email', 'payments'])
            ->withCount('items')
            ->when(in_array($status, ['completed', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($customerId > 0, fn ($query) => $query->where('customer_id', $customerId))
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(function ($sub) use ($like) {
                    $sub->where('id', 'like', $like)
                        ->orWhere('coupon_code', 'like', $like)
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = $sales->getCollection()->map(fn (Sale $sale) => [
            'id' => (int) $sale->id,
            'folio' => 'VE-'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT),
            'status' => $sale->status,
            'total' => (float) $sale->total,
            'created_at' => $sale->created_at?->toISOString(),
            'customer' => $sale->customer ? [
                'id' => (int) $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'email' => $sale->customer->email,
            ] : null,
            'items_count' => (int) $sale->items_count,
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => (int) $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
            ])->values()->all(),
        ])->values()->all();

        return $this->paginatedResponse($sales, $items, 'Ventas obtenidas correctamente.');
    }

    public function show(Sale $sale, Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'sales.view');

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

        return $this->successResponse($this->serializeSale($sale), 'Venta obtenida correctamente.');
    }

    public function store(StoreSaleRequest $request, CreateSaleAction $action): JsonResponse
    {
        $this->ensurePermission($request, 'sales.create');

        $sale = $action->execute($request->validated(), $request->user());

        $sale->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'payments',
            'coupon:id,code,discount_type,discount_value',
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
        ]);

        return $this->successResponse($this->serializeSale($sale), 'Venta creada correctamente.', 201);
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
            'creator' => $sale->creator ? [
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
                        'images' => $item->product->images->map(fn ($image) => [
                            'id' => (int) $image->id,
                            'path' => $image->path,
                            'url' => asset('storage/'.$image->path),
                        ])->values()->all(),
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
}
