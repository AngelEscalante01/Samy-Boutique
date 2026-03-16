<?php

namespace App\Http\Controllers;

use App\Models\Layaway;
use App\Models\LayawayPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LayawayPrintController extends Controller
{
    public function created(Layaway $layaway, Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.view'), 403);

        $layaway->loadMissing([
            'customer:id,name',
            'items:id,layaway_id,product_id,product_variant_id,qty,quantity,name,unit_price',
            'items.variant:id,size_id,color_id',
            'items.variant.size:id,name',
            'items.variant.color:id,name',
            'payments:id,layaway_id,method,amount,paid_at,created_at',
        ]);

        $firstPayment = $layaway->payments
            ->sortBy(fn ($payment) => $payment->paid_at ?? $payment->created_at)
            ->first();

        $initialPayment = (float) ($firstPayment?->amount ?? 0);
        $paidTotal = $this->sumLayawayPayments($layaway);
        $balanceDue = max(0, (float) $layaway->subtotal - $paidTotal);

        return response()->json([
            'type' => 'layaway_created',
            'folio' => (string) $layaway->id,
            'date' => optional($layaway->created_at)?->toIso8601String(),
            'customer' => (string) ($layaway->customer?->name ?? 'Mostrador'),
            'items' => $this->mapItems($layaway),
            'total' => $this->money($layaway->subtotal),
            'initial_payment' => $this->money($initialPayment),
            'paid_total' => $this->money($paidTotal),
            'balance_due' => $this->money($balanceDue),
            'payment_method' => $this->paymentMethodLabel($firstPayment?->method),
        ]);
    }

    public function payment(Layaway $layaway, LayawayPayment $payment, Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.view'), 403);
        abort_if((int) $payment->layaway_id !== (int) $layaway->id, 404);

        $layaway->loadMissing([
            'customer:id,name',
            'payments:id,layaway_id,method,amount,paid_at,created_at',
        ]);

        $paymentAmount = (float) $payment->amount;
        $paidTotal = $this->sumLayawayPayments($layaway);

        $previousPaidTotal = max(0, $paidTotal - $paymentAmount);
        $previousBalance = max(0, (float) $layaway->subtotal - $previousPaidTotal);
        $newBalance = max(0, (float) $layaway->subtotal - $paidTotal);

        return response()->json([
            'type' => 'layaway_payment',
            'store' => 'SAMY BOUTIQUE',
            'folio' => (string) $layaway->id,
            'date' => optional($payment->paid_at ?? $payment->created_at)?->toIso8601String(),
            'customer' => (string) ($layaway->customer?->name ?? 'Mostrador'),
            'payment_amount' => $this->money($paymentAmount),
            'payment_method' => $this->paymentMethodLabel($payment->method),
            'previous_balance' => $this->money($previousBalance),
            'paid_total' => $this->money($paidTotal),
            'balance_due' => $this->money($newBalance),
            'reference' => $payment->reference,
        ]);
    }

    public function closed(Layaway $layaway, Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.view'), 403);

        $layaway->loadMissing([
            'customer:id,name',
            'payments:id,layaway_id,method,amount,paid_at,created_at',
            'sale:id,total',
            'sale.payments:id,sale_id,method,amount,created_at',
        ]);

        $total = (float) ($layaway->sale?->total ?? $layaway->subtotal);
        $paidBeforeLiquidation = $this->sumLayawayPayments($layaway);

        $salePayments = $layaway->sale?->payments
            ? $layaway->sale->payments->sortBy('id')->values()
            : collect();

        $finalPayments = $salePayments->isNotEmpty()
            ? $salePayments->slice($layaway->payments->count())->values()
            : collect();

        if ($finalPayments->isEmpty() && $salePayments->isNotEmpty()) {
            $finalPayments = $salePayments;
        }

        $finalPayment = (float) ($finalPayments->last()?->amount ?? max(0, $total - $paidBeforeLiquidation));
        $paidTotal = (float) ($salePayments->isNotEmpty() ? $salePayments->sum('amount') : $paidBeforeLiquidation);

        $finalPaymentMethod = $this->resolveFinalPaymentMethod($layaway);

        return response()->json([
            'type' => 'layaway_closed',
            'folio' => (string) $layaway->id,
            'date' => optional($layaway->liquidated_at ?? $layaway->updated_at)?->toIso8601String(),
            'customer' => (string) ($layaway->customer?->name ?? 'Mostrador'),
            'total' => $this->money($total),
            'paid_total' => $this->money($paidTotal),
            'final_payment' => $this->money($finalPayment),
            'payment_method' => $finalPaymentMethod,
            'balance_due' => 0.0,
            'sale_id' => $layaway->sale?->id,
        ]);
    }

    private function mapItems(Layaway $layaway): array
    {
        return $layaway->items
            ->map(function ($item) {
                $qty = (int) ($item->qty ?? $item->quantity ?? 1);
                $unitPrice = (float) $item->unit_price;
                $lineTotal = $unitPrice * $qty;

                return [
                    'name' => (string) ($item->name ?? 'Producto'),
                    'color' => (string) ($item->variant?->color?->name ?? 'N/A'),
                    'size' => (string) ($item->variant?->size?->name ?? 'N/A'),
                    'qty' => $qty,
                    'price' => $this->money($unitPrice),
                    'total' => $this->money($lineTotal),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveFinalPaymentMethod(Layaway $layaway): string
    {
        if (! $layaway->relationLoaded('sale') || ! $layaway->sale) {
            $latestLayawayPayment = $layaway->payments
                ->sortByDesc(fn ($payment) => $payment->paid_at ?? $payment->created_at)
                ->first();

            return $this->paymentMethodLabel($latestLayawayPayment?->method);
        }

        $salePayments = $layaway->sale->payments
            ->sortBy('id')
            ->values();

        $alreadyCountedPayments = $layaway->payments->count();
        $finalPayments = $salePayments
            ->slice($alreadyCountedPayments)
            ->values();

        if ($finalPayments->isEmpty()) {
            $finalPayments = $salePayments;
        }

        $methods = $finalPayments
            ->pluck('method')
            ->filter()
            ->map(fn ($method) => strtolower((string) $method))
            ->unique()
            ->values();

        if ($methods->count() > 1) {
            return 'Mixto';
        }

        return $this->paymentMethodLabel($methods->first());
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'other' => 'Otro',
            default => 'Otro',
        };
    }

    private function sumLayawayPayments(Layaway $layaway): float
    {
        if (! $layaway->relationLoaded('payments')) {
            $layaway->load('payments:id,layaway_id,amount');
        }

        return (float) $layaway->payments->sum('amount');
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
