<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SalePrintController extends Controller
{
    public function show(Sale $sale, Request $request): JsonResponse
    {
        $this->authorizePrint($request);
        $this->loadPrintRelations($sale);

        $paymentSummary = $this->buildPaymentSummary($sale);

        return response()->json([
            'store' => [
                'name' => (string) config('app.name'),
            ],
            'folio' => (string) $sale->id,
            'date' => optional($sale->created_at)?->toIso8601String(),
            'customer' => [
                'id' => $sale->customer?->id,
                'name' => (string) ($sale->customer?->name ?? 'Mostrador'),
                'phone' => $sale->customer?->phone,
                'email' => $sale->customer?->email,
            ],
            'items' => $sale->items
                ->map(fn (SaleItem $item) => $this->transformItem($item))
                ->values(),
            'subtotal' => $this->money($sale->subtotal),
            'discount' => $this->money($sale->discount_total),
            'total' => $this->money($sale->total),
            'payment_method' => $paymentSummary['payment_method'],
            'cash_received' => $paymentSummary['cash_received'],
            'change' => $paymentSummary['change'],
        ]);
    }

    private function authorizePrint(Request $request): void
    {
        abort_unless($request->user()?->can('sales.view'), 403);
    }

    private function loadPrintRelations(Sale $sale): void
    {
        $sale->loadMissing([
            'customer:id,name,phone,email',
            'payments:id,sale_id,method,amount,reference',
            'items:id,sale_id,product_id,product_variant_id,qty,quantity,name,sku,unit_price,line_total,final_price',
            'items.product:id,name',
            'items.variant:id,product_id,size_id,color_id,sku,sale_price',
            'items.variant.size:id,name',
            'items.variant.color:id,name',
        ]);
    }

    /**
     * @return array{payment_method:string,cash_received:float,change:float}
     */
    private function buildPaymentSummary(Sale $sale): array
    {
        /** @var Collection<int, string> $paymentMethods */
        $paymentMethods = $sale->payments
            ->pluck('method')
            ->filter(fn ($method) => is_string($method) && $method !== '')
            ->unique()
            ->values();

        $paymentMethod = $paymentMethods->count() > 1
            ? 'mixed'
            : (string) ($paymentMethods->first() ?? 'unknown');

        $cashReceived = (float) $sale->payments
            ->where('method', 'cash')
            ->sum('amount');

        $total = (float) $sale->total;
        $change = max(0, $cashReceived - $total);

        return [
            'payment_method' => $paymentMethod,
            'cash_received' => $this->money($cashReceived),
            'change' => $this->money($change),
        ];
    }

    /**
     * @return array{name:string,color:string,size:string,qty:int,price:float,total:float}
     */
    private function transformItem(SaleItem $item): array
    {
        $qty = (int) ($item->qty ?? $item->quantity ?? 1);
        $unitPrice = (float) $item->unit_price;
        $lineTotal = (float) ($item->line_total ?? $item->final_price ?? ($unitPrice * $qty));

        return [
            'name' => (string) ($item->name ?: ($item->product?->name ?? 'Producto')),
            'color' => (string) ($item->variant?->color?->name ?? 'N/A'),
            'size' => (string) ($item->variant?->size?->name ?? 'N/A'),
            'qty' => max(1, $qty),
            'price' => $this->money($unitPrice),
            'total' => $this->money($lineTotal),
        ];
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
