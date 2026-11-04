<?php

namespace App\Services;

use App\Models\Layaway;
use App\Models\Sale;
use Illuminate\Support\Collection;

class NotificationMessageFormatter
{
    public function sale(Sale $sale, string $label = 'Compra registrada'): array
    {
        $sale->loadMissing('items');
        $title = $label.' #'.$sale->id.' · '.$this->money($sale->total);
        $products = $this->productSummary($sale->items->map(fn ($item) => [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_amount' => (int) $item->quantity > 0 ? (float) $item->line_total / (int) $item->quantity : $item->line_total,
            'amount' => $item->line_total,
        ]));

        return [$title, 'Productos: '.$products.'. Total: '.$this->money($sale->total).'.'];
    }

    public function layaway(Layaway $layaway): array
    {
        $layaway->loadMissing('items');
        $title = 'Nuevo apartado #'.$layaway->id.' · '.$this->money($layaway->total);
        $products = $this->productSummary($layaway->items->map(fn ($item) => [
            'name' => $item->name,
            'quantity' => $item->qty ?? $item->quantity ?? 1,
            'unit_amount' => $item->unit_price,
            'amount' => (float) $item->unit_price * (int) ($item->qty ?? $item->quantity ?? 1),
        ]));
        $message = 'Productos: '.$products.'. Abonado: '.$this->money($layaway->applied_total).'; saldo: '.$this->money($layaway->balance).'.';

        return [$title, $message];
    }

    public function cancelledLayaway(Layaway $layaway): array
    {
        $layaway->loadMissing('items');
        $products = $this->productSummary($layaway->items->map(fn ($item) => [
            'name' => $item->name,
            'quantity' => $item->qty ?? $item->quantity ?? 1,
            'unit_amount' => $item->unit_price,
            'amount' => (float) $item->unit_price * (int) ($item->qty ?? $item->quantity ?? 1),
        ]));

        return [
            'Apartado #'.$layaway->id.' cancelado',
            'Se liberaron: '.$products.'. Abonado antes de cancelar: '.$this->money($layaway->paid_total).'.',
        ];
    }

    public function layawayPayment(Layaway $layaway, $payment): array
    {
        $title = 'Abono en apartado #'.$layaway->id.' · '.$this->money($payment->amount);
        $message = 'Se aplicó un abono de '.$this->money($payment->amount).'. Saldo pendiente: '.$this->money($layaway->balance).'.';

        return [$title, $message];
    }

    public function liquidatedLayaway(Layaway $layaway, Sale $sale): array
    {
        $sale->loadMissing('items');
        $title = 'Apartado #'.$layaway->id.' liquidado';
        $products = $this->productSummary($sale->items->map(fn ($item) => [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_amount' => (int) $item->quantity > 0 ? (float) $item->line_total / (int) $item->quantity : $item->line_total,
            'amount' => $item->line_total,
        ]));

        return [$title, 'Productos: '.$products.'. Total de compra: '.$this->money($sale->total).'.'];
    }

    public function cashCut($cut): array
    {
        $totals = $cut->totals_json ?? [];
        $salesCount = (int) ($totals['sales_count'] ?? 0);
        $total = $totals['total_sold'] ?? $totals['total_sum'] ?? 0;

        return [
            'Corte de caja · '.$cut->cut_date->format('d/m/Y'),
            $salesCount.' ventas registradas. Total vendido: '.$this->money($total).'.',
        ];
    }

    private function productSummary(Collection $items): string
    {
        $items = $items->values();
        if ($items->isEmpty()) {
            return 'sin detalle de productos';
        }

        $parts = $items->take(3)->map(function ($item): string {
            $quantity = max(1, (int) $item['quantity']);
            $price = isset($item['unit_amount'])
                ? $this->money($item['unit_amount']).' c/u; '.$this->money($item['amount']).' total'
                : $this->money($item['amount']);

            return $quantity.' × '.trim((string) $item['name']).' ('.$price.')';
        })->all();
        if ($items->count() > 3) {
            $parts[] = 'y '.($items->count() - 3).' producto(s) más';
        }

        return mb_substr(implode(', ', $parts), 0, 300);
    }

    private function money($amount): string
    {
        return '$'.number_format((float) $amount, 2, '.', ',');
    }
}
