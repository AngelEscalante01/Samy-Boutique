<?php

namespace App\Http\Controllers;

use App\Models\CashCut;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReportsController extends Controller
{
    /**
     * Página principal de reportes con filtros de rango de fechas.
     */
    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        try {
            $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
            $end   = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
        } catch (\Throwable) {
            $start = now()->startOfMonth()->startOfDay();
            $end   = now()->endOfDay();
        }

        /* ── Sales summary ──────────────────────────────────────────────── */
        $salesBase = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end]);

        $salesCount = (int) (clone $salesBase)->count();

        $salesTotals = (clone $salesBase)
            ->selectRaw('COALESCE(SUM(subtotal),0)               as subtotal_sum')
            ->selectRaw('COALESCE(SUM(discount_total),0)         as discount_sum')
            ->selectRaw('COALESCE(SUM(coupon_discount_total),0)  as coupon_sum')
            ->selectRaw('COALESCE(SUM(loyalty_discount_total),0) as loyalty_sum')
            ->selectRaw('COALESCE(SUM(total),0)                  as total_sum')
            ->first();

        $canceledCount = (int) Sale::query()
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $total    = (float) $salesTotals->total_sum;
        $avgTicket = $salesCount > 0 ? round($total / $salesCount, 2) : 0.0;

        $salesSummary = [
            'total'           => $total,
            'count'           => $salesCount,
            'avg_ticket'      => $avgTicket,
            'discounts_total' => (float) $salesTotals->discount_sum,
            'coupons_total'   => (float) $salesTotals->coupon_sum,
            'loyalty_total'   => (float) $salesTotals->loyalty_sum,
            'canceled_count'  => $canceledCount,
        ];

        /* ── Ventas por día ─────────────────────────────────────────────── */
        $salesByDay = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COALESCE(SUM(total),0)  as total')
            ->selectRaw('COUNT(*)                as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->map(fn ($r) => [
                'date'  => $r->date,
                'total' => (float) $r->total,
                'count' => (int) $r->count,
            ]);

        /* ── Top categorías ─────────────────────────────────────────────── */
        $topCategories = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw("COALESCE(categories.name, 'Sin categoría') as name")
            ->selectRaw('SUM(sale_items.quantity) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'  => $r->name,
                'qty'   => (int) $r->qty,
                'total' => (float) $r->total,
            ]);

        /* ── Top productos ──────────────────────────────────────────────── */
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_items.product_id', 'sale_items.sku', 'sale_items.name')
            ->selectRaw('sale_items.sku  as sku')
            ->selectRaw('sale_items.name as name')
            ->selectRaw('SUM(sale_items.quantity)                 as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0)   as total')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'sku'   => $r->sku,
                'name'  => $r->name,
                'qty'   => (int) $r->qty,
                'total' => (float) $r->total,
            ]);

        /* ── Inventario ─────────────────────────────────────────────────── */
        $availableCount = (int) DB::table('products')->where('status', 'disponible')->count();
        $layawayCount   = (int) DB::table('layaway_items')
            ->join('layaways', 'layaway_items.layaway_id', '=', 'layaways.id')
            ->whereIn('layaways.status', ['pending', 'partial'])
            ->count();

        $inventoryCounts = [
            'available' => $availableCount,
            'layaway'   => $layawayCount,
        ];

        /* ── Top clientes ───────────────────────────────────────────────── */
        $topCustomers = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->selectRaw('customers.name  as name')
            ->selectRaw('customers.phone as phone')
            ->selectRaw('COUNT(*) as purchases_in_range')
            ->selectRaw('COALESCE(SUM(sales.total),0) as total_spent')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'               => $r->name,
                'phone'              => $r->phone,
                'purchases_in_range' => (int) $r->purchases_in_range,
                'total_spent'        => (float) $r->total_spent,
            ]);

        /* ── Near loyalty ───────────────────────────────────────────────── */
        $nearLoyalty = Customer::query()
            ->whereBetween('purchases_count', [4, 4])
            ->select(['id', 'name', 'phone', 'purchases_count'])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'name'            => $c->name,
                'phone'           => $c->phone,
                'purchases_count' => $c->purchases_count,
            ]);

        return Inertia::render('Reports/Index', [
            'filters'              => ['from' => $from, 'to' => $to],
            'salesSummary'         => $salesSummary,
            'salesByDay'           => $salesByDay,
            'topCategories'        => $topCategories,
            'topProducts'          => $topProducts,
            'inventoryCounts'      => $inventoryCounts,
            'topCustomers'         => $topCustomers,
            'nearLoyaltyCustomers' => $nearLoyalty,
        ]);
    }

    public function dailyCut(Request $request)
    {
        $date = (string) $request->query('date', now()->toDateString());

        $cut = CashCut::query()
            ->where('cut_date', $date)
            ->with('creator')
            ->first();

        return Inertia::render('Reports/DailyCut', [
            'filters' => [
                'date' => $date,
            ],
            'savedCut' => $cut,
        ]);
    }

    public function previewDailyCut(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'totals' => $this->computeDailyCutTotals($data['date']),
        ]);
    }

    public function saveDailyCut(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $totals = $this->computeDailyCutTotals($data['date']);

        $cut = CashCut::query()->updateOrCreate(
            ['cut_date' => $data['date']],
            [
                'created_by' => (int) $request->user()->id,
                'totals_json' => $totals,
            ],
        );

        if ($request->header('X-Inertia')) {
            return redirect()
                ->route('reports.dailyCut', ['date' => $data['date']])
                ->with('success', 'Corte guardado.');
        }

        return response()->json(['cash_cut' => $cut], 201);
    }

    /**
     * Consulta principal del corte diario.
     *
     * Incluye:
     * - total ventas pagadas (status=completed)
     * - descuentos (discount_total)
     * - cupones (coupon_discount_total)
     * - fidelidad (loyalty_discount_total)
     * - total por método (sale_payments)
     * - número de ventas
     * - top productos y categorías (opcional)
     */
    private function computeDailyCutTotals(string $date): array
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'date' => ['Fecha inválida.'],
            ]);
        }

        $start = $day->copy();
        $end = $day->copy()->endOfDay();

        $salesBase = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end]);

        $salesCount = (int) (clone $salesBase)->count();

        $salesTotals = (clone $salesBase)
            ->selectRaw('COALESCE(SUM(subtotal),0) as subtotal_sum')
            ->selectRaw('COALESCE(SUM(discount_total),0) as discount_sum')
            ->selectRaw('COALESCE(SUM(coupon_discount_total),0) as coupon_discount_sum')
            ->selectRaw('COALESCE(SUM(loyalty_discount_total),0) as loyalty_discount_sum')
            ->selectRaw('COALESCE(SUM(total),0) as total_sum')
            ->first();

        $paymentRows = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method')
            ->selectRaw('COALESCE(SUM(sale_payments.amount),0) as amount_sum')
            ->get();

        $paymentsByMethod = collect($paymentRows)
            ->mapWithKeys(fn ($r) => [(string) $r->method => number_format((float) $r->amount_sum, 2, '.', '')])
            ->all();

        // Completar métodos conocidos para UI consistente
        foreach (['cash', 'card', 'transfer', 'other'] as $method) {
            $paymentsByMethod[$method] = $paymentsByMethod[$method] ?? '0.00';
        }

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_items.product_id', 'sale_items.sku', 'sale_items.name')
            ->selectRaw('sale_items.product_id as product_id')
            ->selectRaw('sale_items.sku as sku')
            ->selectRaw('sale_items.name as name')
            ->selectRaw('COUNT(*) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topCategories = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id as category_id')
            ->selectRaw('COALESCE(categories.name, "Sin categoría") as name')
            ->selectRaw('COUNT(*) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $cancelledSales = Sale::query()
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->select(['id', 'total', 'created_at', 'cancel_reason'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'total' => (float) $sale->total,
                'created_at' => $sale->created_at?->toDateTimeString(),
                'cancel_reason' => $sale->cancel_reason,
            ])
            ->values();

        return [
            'date' => $date,
            'sales_count' => $salesCount,
            'subtotal_sum' => number_format((float) $salesTotals->subtotal_sum, 2, '.', ''),
            'discount_sum' => number_format((float) $salesTotals->discount_sum, 2, '.', ''),
            'coupon_discount_sum' => number_format((float) $salesTotals->coupon_discount_sum, 2, '.', ''),
            'loyalty_discount_sum' => number_format((float) $salesTotals->loyalty_discount_sum, 2, '.', ''),
            'total_sum' => number_format((float) $salesTotals->total_sum, 2, '.', ''),
            'payments_by_method' => $paymentsByMethod,
            'top_products' => $topProducts,
            'top_categories' => $topCategories,
            'cancelled_count' => $cancelledSales->count(),
            'cancelled_sales' => $cancelledSales,
        ];
    }
}
