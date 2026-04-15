<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Models\Customer;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportApiController extends ApiController
{
    public function __construct(private readonly SalesAnalyticsService $salesAnalytics)
    {
    }

    /**
     * GET /api/reports?from=Y-m-d&to=Y-m-d
     */
    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to', now()->toDateString());

        try {
            $start = Carbon::createFromFormat('Y-m-d', (string) $from)->startOfDay();
            $end   = Carbon::createFromFormat('Y-m-d', (string) $to)->endOfDay();
        } catch (\Throwable) {
            $start = now()->startOfMonth()->startOfDay();
            $end   = now()->endOfDay();
            $from  = $start->toDateString();
            $to    = $end->toDateString();
        }

        $reportMetrics = $this->salesAnalytics->buildRangeReport($start, $end);

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
                'name'  => (string) $row->name,
                'qty'   => (int) $row->qty,
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
                'sku'   => $row->sku,
                'name'  => (string) $row->name,
                'qty'   => (int) $row->qty,
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
                'name'              => (string) $row->name,
                'phone'             => $row->phone,
                'purchases_in_range' => (int) $row->purchases_in_range,
                'total_spent'       => round((float) $row->total_spent, 2),
            ]);

        $nearLoyalty = Customer::query()
            ->whereBetween('purchases_count', [4, 4])
            ->select(['id', 'name', 'phone', 'purchases_count'])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($customer) => [
                'name'            => (string) $customer->name,
                'phone'           => $customer->phone,
                'purchases_count' => (int) $customer->purchases_count,
            ]);

        return $this->successResponse([
            'filters'              => ['from' => $from, 'to' => $to],
            'summary'              => $reportMetrics['summary'],
            'daily'                => $reportMetrics['daily'],
            'top_categories'       => $topCategories,
            'top_products'         => $topProducts,
            'top_customers'        => $topCustomers,
            'near_loyalty_customers' => $nearLoyalty,
        ], 'Report data');
    }
}
