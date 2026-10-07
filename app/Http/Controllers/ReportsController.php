<?php

namespace App\Http\Controllers;

use App\Models\CashCut;
use App\Models\Sale;
use App\Services\DailyCutService;
use App\Services\ReportAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportsController extends Controller
{
    /**
     * Página principal de reportes con filtros de rango de fechas.
     */
    public function index(Request $request, ReportAnalyticsService $analytics)
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

        $analyticsData = $analytics->forRange($start, $end);
        $topCategories = $analyticsData['top_categories'];
        $topProducts = $analyticsData['top_products'];

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

        $topCustomers = $analyticsData['top_customers'];
        $nearLoyalty = $analyticsData['near_loyalty_customers'];

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

    public function previewDailyCut(Request $request, DailyCutService $dailyCut)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'totals' => $dailyCut->totals($data['date']),
        ]);
    }

    public function saveDailyCut(Request $request, DailyCutService $dailyCut)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $totals = $dailyCut->totals($data['date']);

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

}
