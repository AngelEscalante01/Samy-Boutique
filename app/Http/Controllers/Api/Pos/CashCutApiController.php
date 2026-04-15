<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Models\CashCut;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashCutApiController extends ApiController
{
    public function __construct(private readonly SalesAnalyticsService $salesAnalytics)
    {
    }

    /**
     * GET /api/cash-cuts?per_page=20&page=1
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = CashCut::query()
            ->with('creator:id,name')
            ->orderByDesc('cut_date')
            ->paginate((int) $request->query('per_page', 20));

        $items = $paginator->getCollection()->map(fn (CashCut $cut) => [
            'id'         => $cut->id,
            'cut_date'   => $cut->cut_date?->toDateString(),
            'created_by' => $cut->creator?->name,
            'totals'     => $cut->totals_json,
            'created_at' => $cut->created_at?->toISOString(),
        ])->values()->all();

        return $this->paginatedResponse($paginator, $items);
    }

    /**
     * POST /api/cash-cuts/preview
     * Body: { date: 'Y-m-d' }
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $totals = $this->computeTotals($data['date']);

        return $this->successResponse(['totals' => $totals], 'Cash cut preview');
    }

    /**
     * POST /api/cash-cuts
     * Body: { date: 'Y-m-d' }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $totals = $this->computeTotals($data['date']);

        $cut = CashCut::query()->updateOrCreate(
            ['cut_date' => $data['date']],
            [
                'created_by'  => (int) $request->user()->id,
                'totals_json' => $totals,
            ],
        );

        return $this->successResponse([
            'id'         => $cut->id,
            'cut_date'   => $cut->cut_date?->toDateString(),
            'created_by' => $request->user()?->name,
            'totals'     => $cut->totals_json,
            'created_at' => $cut->created_at?->toISOString(),
        ], 'Cash cut saved', 201);
    }

    private function computeTotals(string $date): array
    {
        $dailyMetrics = $this->salesAnalytics->buildDailyCutMetrics($date);
        $summary      = $dailyMetrics['summary'];

        try {
            $day   = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => ['Fecha inválida.']]);
        }

        $start = $day->copy();
        $end   = $day->copy()->endOfDay();

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
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'sku'   => $row->sku,
                'name'  => (string) $row->name,
                'qty'   => (int) $row->qty,
                'total' => round((float) $row->total, 2),
            ]);

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

        return [
            'date'                    => $date,
            'sales_count'             => (int) ($summary['sales_count'] ?? 0),
            'total_sold'              => (float) ($summary['total_sold'] ?? 0),
            'profit_total'            => (float) ($summary['profit_total'] ?? 0),
            'manual_discount_total'   => (float) ($summary['manual_discount_total'] ?? 0),
            'coupon_discount_total'   => (float) ($summary['coupon_discount_total'] ?? 0),
            'loyalty_discount_total'  => (float) ($summary['loyalty_discount_total'] ?? 0),
            'payments_by_method'      => $summary['payments_by_method'] ?? [
                'cash' => 0, 'card' => 0, 'transfer' => 0, 'other' => 0,
            ],
            'top_products'   => $topProducts,
            'top_categories' => $topCategories,
            'cancelled_count' => (int) ($summary['canceled_count'] ?? 0),
        ];
    }
}
