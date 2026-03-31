<?php

namespace App\Services;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesAnalyticsService
{
    public function buildRangeReport(Carbon $start, Carbon $end): array
    {
        $completedSales = $this->loadCompletedSales($start, $end);

        [$summary, $dailyMap] = $this->aggregateCompletedSales($completedSales);

        $cancelledByDate = Sale::query()
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as report_date')
            ->selectRaw('COUNT(*) as cancelled_count')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->report_date => (int) $row->cancelled_count,
            ]);

        $summary['canceled_count'] = (int) $cancelledByDate->sum();

        $dailyRows = [];
        $cursor = $start->copy()->startOfDay();
        $limit = $end->copy()->startOfDay();

        while ($cursor->lte($limit)) {
            $dateKey = $cursor->toDateString();

            $baseRow = $dailyMap->get($dateKey, [
                'date' => $dateKey,
                'sales_count' => 0,
                'total_sold' => 0.0,
                'profit_total' => 0.0,
                'discount_total' => 0.0,
                'manual_discount_total' => 0.0,
                'coupon_discount_total' => 0.0,
                'loyalty_discount_total' => 0.0,
                'canceled_count' => 0,
            ]);

            $baseRow['canceled_count'] = (int) ($cancelledByDate->get($dateKey, 0));

            $dailyRows[] = $baseRow;
            $cursor->addDay();
        }

        return [
            'summary' => $summary,
            'daily' => $dailyRows,
        ];
    }

    public function buildDailyCutMetrics(string $date): array
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'date' => ['Fecha invalida.'],
            ]);
        }

        $start = $day->copy();
        $end = $day->copy()->endOfDay();

        $completedSales = $this->loadCompletedSales($start, $end);
        [$summary] = $this->aggregateCompletedSales($completedSales);

        $payments = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method')
            ->selectRaw('COALESCE(SUM(sale_payments.amount),0) as amount_sum')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->method => $this->toMoney((float) $row->amount_sum),
            ])
            ->all();

        foreach (['cash', 'card', 'transfer', 'other'] as $method) {
            $payments[$method] = $payments[$method] ?? 0.0;
        }

        $cancelledSales = Sale::query()
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->select(['id', 'total', 'created_at', 'cancel_reason', 'cancellation_reason'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => (int) $sale->id,
                'total' => $this->toMoney((float) $sale->total),
                'created_at' => $sale->created_at?->toDateTimeString(),
                'cancel_reason' => $sale->cancel_reason ?: $sale->cancellation_reason,
            ])
            ->values()
            ->all();

        $sales = Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->with(['payments', 'customer:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Sale $sale) {
                $profit = $sale->status === 'completed'
                    ? $this->calculateSaleProfit($sale)
                    : 0.0;

                return [
                    'id' => (int) $sale->id,
                    'status' => (string) $sale->status,
                    'total' => $this->toMoney((float) $sale->total),
                    'profit' => $this->toMoney($profit),
                    'created_at' => $sale->created_at?->toDateTimeString(),
                    'customer' => $sale->customer?->name,
                    'methods' => $sale->payments->pluck('method')->filter()->unique()->values(),
                ];
            })
            ->values()
            ->all();

        $summary = array_merge($summary, [
            'date' => $date,
            'canceled_count' => count($cancelledSales),
            'payments' => $payments,
            'payments_by_method' => $payments,
            'cancelled_sales' => $cancelledSales,
        ]);

        return [
            'summary' => $summary,
            'sales' => $sales,
        ];
    }

    private function loadCompletedSales(Carbon $start, Carbon $end): Collection
    {
        return Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'items:id,sale_id,product_id,product_variant_id,qty,quantity,unit_price,line_total,final_price,discount_amount,discount',
                'items.product:id,purchase_price',
                'items.variant:id,purchase_price',
            ])
            ->get();
    }

    private function aggregateCompletedSales(Collection $sales): array
    {
        $summary = [
            'total_sold' => 0.0,
            'total_sales' => 0.0,
            'profit_total' => 0.0,
            'sales_count' => 0,
            'avg_ticket' => 0.0,
            'manual_discount_total' => 0.0,
            'discount_manual_total' => 0.0,
            'coupon_discount_total' => 0.0,
            'discount_coupon_total' => 0.0,
            'loyalty_discount_total' => 0.0,
            'discount_loyalty_total' => 0.0,
            'discount_total' => 0.0,
            'subtotal_sum' => 0.0,
            'canceled_count' => 0,
        ];

        $dailyMap = collect();

        foreach ($sales as $sale) {
            $dateKey = $sale->created_at?->toDateString();
            if (! $dateKey) {
                continue;
            }

            $total = (float) $sale->total;
            $profit = $this->calculateSaleProfit($sale);
            $manualDiscount = $this->calculateManualDiscount($sale);
            $couponDiscount = max(0.0, (float) ($sale->coupon_discount_total ?? 0));
            $loyaltyDiscount = max(0.0, (float) ($sale->loyalty_discount_total ?? 0));
            $discountTotal = $manualDiscount + $couponDiscount + $loyaltyDiscount;

            $summary['sales_count']++;
            $summary['total_sold'] += $total;
            $summary['total_sales'] += $total;
            $summary['profit_total'] += $profit;
            $summary['manual_discount_total'] += $manualDiscount;
            $summary['discount_manual_total'] += $manualDiscount;
            $summary['coupon_discount_total'] += $couponDiscount;
            $summary['discount_coupon_total'] += $couponDiscount;
            $summary['loyalty_discount_total'] += $loyaltyDiscount;
            $summary['discount_loyalty_total'] += $loyaltyDiscount;
            $summary['discount_total'] += $discountTotal;
            $summary['subtotal_sum'] += (float) ($sale->subtotal ?? 0);

            $row = $dailyMap->get($dateKey, [
                'date' => $dateKey,
                'sales_count' => 0,
                'total_sold' => 0.0,
                'profit_total' => 0.0,
                'discount_total' => 0.0,
                'manual_discount_total' => 0.0,
                'coupon_discount_total' => 0.0,
                'loyalty_discount_total' => 0.0,
                'canceled_count' => 0,
            ]);

            $row['sales_count']++;
            $row['total_sold'] += $total;
            $row['profit_total'] += $profit;
            $row['discount_total'] += $discountTotal;
            $row['manual_discount_total'] += $manualDiscount;
            $row['coupon_discount_total'] += $couponDiscount;
            $row['loyalty_discount_total'] += $loyaltyDiscount;

            $dailyMap->put($dateKey, $row);
        }

        $summary['avg_ticket'] = $summary['sales_count'] > 0
            ? $summary['total_sold'] / $summary['sales_count']
            : 0.0;

        $summary['total_sales'] = $summary['total_sold'];
        $summary['discount_manual_total'] = $summary['manual_discount_total'];
        $summary['discount_coupon_total'] = $summary['coupon_discount_total'];
        $summary['discount_loyalty_total'] = $summary['loyalty_discount_total'];

        foreach (array_keys($summary) as $key) {
            if ($key === 'sales_count' || $key === 'canceled_count') {
                continue;
            }

            $summary[$key] = $this->toMoney($summary[$key]);
        }

        $dailyMap = $dailyMap
            ->sortKeys()
            ->map(function ($row) {
                $row['total_sold'] = $this->toMoney($row['total_sold']);
                $row['profit_total'] = $this->toMoney($row['profit_total']);
                $row['discount_total'] = $this->toMoney($row['discount_total']);
                $row['manual_discount_total'] = $this->toMoney($row['manual_discount_total']);
                $row['coupon_discount_total'] = $this->toMoney($row['coupon_discount_total']);
                $row['loyalty_discount_total'] = $this->toMoney($row['loyalty_discount_total']);

                return $row;
            });

        return [$summary, $dailyMap];
    }

    private function calculateManualDiscount(Sale $sale): float
    {
        $subtotal = (float) ($sale->subtotal ?? 0);
        $total = (float) ($sale->total ?? 0);
        $coupon = max(0.0, (float) ($sale->coupon_discount_total ?? 0));
        $loyalty = max(0.0, (float) ($sale->loyalty_discount_total ?? 0));

        $manual = $subtotal - $total - $coupon - $loyalty;

        return $this->toMoney(max(0.0, $manual));
    }

    private function calculateSaleProfit(Sale $sale): float
    {
        $lines = [];
        $lineRevenueTotal = 0.0;

        foreach ($sale->items as $item) {
            $qty = max(1, (int) ($item->qty ?? $item->quantity ?? 1));

            $lineRevenue = (float) ($item->line_total
                ?? $item->final_price
                ?? ((float) ($item->unit_price ?? 0) * $qty));

            $unitCost = null;
            if ($item->variant && $item->variant->purchase_price !== null) {
                $unitCost = (float) $item->variant->purchase_price;
            } elseif ($item->product && $item->product->purchase_price !== null) {
                $unitCost = (float) $item->product->purchase_price;
            }

            $lineCost = max(0.0, (float) ($unitCost ?? 0)) * $qty;

            $lines[] = [
                'revenue' => max(0.0, $lineRevenue),
                'cost' => $lineCost,
            ];

            $lineRevenueTotal += max(0.0, $lineRevenue);
        }

        if (count($lines) === 0) {
            return 0.0;
        }

        $saleTotal = max(0.0, (float) ($sale->total ?? 0));
        $discountPool = max(0.0, $lineRevenueTotal - $saleTotal);

        $allocated = 0.0;
        $profit = 0.0;

        foreach ($lines as $index => $line) {
            $lineRevenue = (float) $line['revenue'];
            $lineCost = (float) $line['cost'];

            if ($lineRevenueTotal <= 0) {
                $lineAllocatedDiscount = 0.0;
            } elseif ($index === count($lines) - 1) {
                $lineAllocatedDiscount = max(0.0, $discountPool - $allocated);
            } else {
                $share = $lineRevenue / $lineRevenueTotal;
                $lineAllocatedDiscount = round($discountPool * $share, 2);
                $allocated += $lineAllocatedDiscount;
            }

            $lineNetRevenue = max(0.0, $lineRevenue - $lineAllocatedDiscount);
            $profit += ($lineNetRevenue - $lineCost);
        }

        return $this->toMoney($profit);
    }

    private function toMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
