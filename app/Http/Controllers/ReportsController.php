<?php

namespace App\Http\Controllers;

use App\Models\CashCut;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function __construct(private readonly SalesAnalyticsService $salesAnalytics)
    {
    }

    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        try {
            $start = Carbon::createFromFormat('Y-m-d', (string) $from)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', (string) $to)->endOfDay();
        } catch (\Throwable) {
            $start = now()->startOfMonth()->startOfDay();
            $end = now()->endOfDay();
            $from = $start->toDateString();
            $to = $end->toDateString();
        }

        $reportMetrics = $this->salesAnalytics->buildRangeReport($start, $end);

        $salesSummary = $reportMetrics['summary'];
        $dailySummary = $reportMetrics['daily'];

        $topCategories = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw("COALESCE(categories.name, 'Sin categoria') as name")
            ->selectRaw('SUM(COALESCE(sale_items.qty, sale_items.quantity, 1)) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'qty' => (int) $row->qty,
                'total' => round((float) $row->total, 2),
            ]);

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_items.product_id', 'sale_items.sku', 'sale_items.name')
            ->selectRaw('sale_items.sku as sku')
            ->selectRaw('sale_items.name as name')
            ->selectRaw('SUM(COALESCE(sale_items.qty, sale_items.quantity, 1)) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'name' => (string) $row->name,
                'qty' => (int) $row->qty,
                'total' => round((float) $row->total, 2),
            ]);

        $topCustomers = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->selectRaw('customers.name as name')
            ->selectRaw('customers.phone as phone')
            ->selectRaw('COUNT(*) as purchases_in_range')
            ->selectRaw('COALESCE(SUM(sales.total),0) as total_spent')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'phone' => $row->phone,
                'purchases_in_range' => (int) $row->purchases_in_range,
                'total_spent' => round((float) $row->total_spent, 2),
            ]);

        $nearLoyalty = Customer::query()
            ->whereBetween('purchases_count', [4, 4])
            ->select(['id', 'name', 'phone', 'purchases_count'])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($customer) => [
                'name' => (string) $customer->name,
                'phone' => $customer->phone,
                'purchases_count' => (int) $customer->purchases_count,
            ]);

        return Inertia::render('Reports/Index', [
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'salesSummary' => $salesSummary,
            'dailySummary' => $dailySummary,
            'topCategories' => $topCategories,
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
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

    private function computeDailyCutTotals(string $date): array
    {
        $dailyMetrics = $this->salesAnalytics->buildDailyCutMetrics($date);
        $summary = $dailyMetrics['summary'];

        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'date' => ['Fecha invalida.'],
            ]);
        }

        $start = $day->copy();
        $end = $day->copy()->endOfDay();

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_items.product_id', 'sale_items.sku', 'sale_items.name')
            ->selectRaw('sale_items.product_id as product_id')
            ->selectRaw('sale_items.sku as sku')
            ->selectRaw('sale_items.name as name')
            ->selectRaw('SUM(COALESCE(sale_items.qty, sale_items.quantity, 1)) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'sku' => $row->sku,
                'name' => (string) $row->name,
                'qty' => (int) $row->qty,
                'total' => round((float) $row->total, 2),
            ]);

        $topCategories = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id as category_id')
            ->selectRaw("COALESCE(categories.name, 'Sin categoria') as name")
            ->selectRaw('SUM(COALESCE(sale_items.qty, sale_items.quantity, 1)) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id ? (int) $row->category_id : null,
                'name' => (string) $row->name,
                'qty' => (int) $row->qty,
                'total' => round((float) $row->total, 2),
            ]);

        return [
            'date' => $summary['date'] ?? $date,
            'sales_count' => (int) ($summary['sales_count'] ?? 0),
            'subtotal_sum' => number_format((float) ($summary['subtotal_sum'] ?? 0), 2, '.', ''),
            'discount_sum' => number_format((float) ($summary['manual_discount_total'] ?? 0), 2, '.', ''),
            'coupon_discount_sum' => number_format((float) ($summary['coupon_discount_total'] ?? 0), 2, '.', ''),
            'loyalty_discount_sum' => number_format((float) ($summary['loyalty_discount_total'] ?? 0), 2, '.', ''),
            'total_sum' => number_format((float) ($summary['total_sold'] ?? 0), 2, '.', ''),
            'profit_sum' => number_format((float) ($summary['profit_total'] ?? 0), 2, '.', ''),
            'total_sold' => number_format((float) ($summary['total_sold'] ?? 0), 2, '.', ''),
            'total_sales' => number_format((float) ($summary['total_sold'] ?? 0), 2, '.', ''),
            'profit_total' => number_format((float) ($summary['profit_total'] ?? 0), 2, '.', ''),
            'manual_discount_total' => number_format((float) ($summary['manual_discount_total'] ?? 0), 2, '.', ''),
            'coupon_discount_total' => number_format((float) ($summary['coupon_discount_total'] ?? 0), 2, '.', ''),
            'loyalty_discount_total' => number_format((float) ($summary['loyalty_discount_total'] ?? 0), 2, '.', ''),
            'payments_by_method' => $summary['payments_by_method'] ?? [
                'cash' => 0,
                'card' => 0,
                'transfer' => 0,
                'other' => 0,
            ],
            'top_products' => $topProducts,
            'top_categories' => $topCategories,
            'cancelled_count' => (int) ($summary['canceled_count'] ?? 0),
            'cancelled_sales' => $summary['cancelled_sales'] ?? [],
        ];
    }
}
