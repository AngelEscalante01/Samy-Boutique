<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SalePrintAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesPrintController extends Controller
{
    public function show(Sale $sale, Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('sales.view'), 403);

        $sale->loadMissing([
            'customer:id,name,phone,email',
            'payments:id,sale_id,method,amount,reference',
            'items:id,sale_id,product_id,product_variant_id,qty,quantity,name,sku,unit_price,line_total,final_price',
            'items.variant:id,product_id,size_id,color_id,sku,sale_price',
            'items.variant.size:id,name',
            'items.variant.color:id,name',
            'items.product:id,name',
        ]);

        $paymentMethods = $sale->payments
            ->pluck('method')
            ->filter()
            ->map(fn ($method) => strtolower((string) $method))
            ->unique()
            ->values();

        $rawPaymentMethod = $paymentMethods->count() > 1
            ? 'mixed'
            : (string) ($paymentMethods->first() ?? 'other');

        $paymentMethod = $this->normalizePaymentMethodLabel($rawPaymentMethod);

        $subtotal = (float) $sale->subtotal;
        $total = (float) $sale->total;
        $discount = max(0, $subtotal - $total);

        $storedCashReceived = $sale->cash_received;
        $derivedCashReceived = (float) $sale->payments
            ->where('method', 'cash')
            ->sum('amount');

        $fallbackCashReceived = $derivedCashReceived > 0
            ? $derivedCashReceived
            : $total;

        $cashReceived = ($storedCashReceived !== null && (float) $storedCashReceived > 0)
            ? (float) $storedCashReceived
            : $fallbackCashReceived;

        $cashReceived = max(0, $this->money($cashReceived));

        $storedChange = $sale->change;
        $change = ($storedChange !== null && (float) $storedChange > 0)
            ? (float) $storedChange
            : max(0, round($cashReceived - $total, 2));

        if ($cashReceived > $total && $change <= 0) {
            $change = $cashReceived - $total;
        }

        $change = max(0, $this->money($change));

        $items = $sale->items->map(function ($item) {
            $qty = (int) ($item->qty ?? $item->quantity ?? 1);
            $unitPrice = (float) $item->unit_price;
            $lineTotal = (float) ($item->line_total ?? $item->final_price ?? ($unitPrice * $qty));

            return [
                'name' => (string) ($item->name ?: ($item->product?->name ?? 'Producto')),
                'color' => (string) ($item->variant?->color?->name ?? 'N/A'),
                'size' => (string) ($item->variant?->size?->name ?? 'N/A'),
                'qty' => $qty,
                'price' => $this->money($unitPrice),
                'total' => $this->money($lineTotal),
            ];
        })->values();

        return response()->json([
            'store' => 'SAMY BOUTIQUE',
            'folio' => (string) $sale->id,
            'date' => $this->formatTicketDate($sale),
            'customer' => (string) ($sale->customer?->name ?? 'Mostrador'),
            'items' => $items,
            'subtotal' => $this->money($subtotal),
            'discount' => $this->money($discount),
            'total' => $this->money($total),
            'payment_method' => $paymentMethod,
            'cash_received' => $cashReceived,
            'change' => $change,
            'meta' => [
                'sale_id' => $sale->id,
                'ticket_type' => 'sale',
            ],
        ]);
    }

    private function normalizePaymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'mixed' => 'Mixto',
            default => 'Otro',
        };
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function formatTicketDate(Sale $sale): string
    {
        $timezone = (string) config('app.timezone', 'America/Mexico_City');

        $sourceDate = $sale->created_at
            ? Carbon::parse($sale->created_at)
            : now($timezone);

        return $sourceDate
            ->setTimezone($timezone)
            ->format('d/m/Y H:i');
    }

    public function storeAudit(Sale $sale, Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('sales.view'), 403);

        $validated = $request->validate([
            'ticket_type' => ['nullable', 'string', 'max:40'],
            'print_attempted' => ['required', 'boolean'],
            'print_success' => ['required', 'boolean'],
            'error_message' => ['nullable', 'string', 'max:2000'],
            'connection_method' => ['nullable', 'string', 'max:40'],
            'printed_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ]);

        $audit = SalePrintAudit::query()->create([
            'sale_id' => $sale->id,
            'user_id' => $request->user()?->id,
            'ticket_type' => (string) ($validated['ticket_type'] ?? 'sale'),
            'print_attempted' => (bool) $validated['print_attempted'],
            'print_success' => (bool) $validated['print_success'],
            'error_message' => $validated['error_message'] ?? null,
            'connection_method' => $validated['connection_method'] ?? null,
            'printed_at' => $validated['printed_at'] ?? now(),
            'meta' => $validated['meta'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'audit_id' => $audit->id,
        ], 201);
    }
}
