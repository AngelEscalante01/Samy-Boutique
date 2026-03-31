<?php

namespace App\Http\Controllers;

use App\Models\Layaway;
use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TREND_DAYS = 7;

    public function index(Request $request): Response
    {
        $now = now();

        return Inertia::render('Dashboard', [
            'snapshot' => [
                'summary' => $this->buildSummary($now),
                'chart' => $this->buildChartData($now, '7d'),
                'recentSales' => $this->buildRecentSales(),
                'recentLayaways' => $this->buildRecentLayaways(),
                'paymentSummary' => $this->buildPaymentSummary($now),
            ],
            'endpoints' => [
                'summary' => route('dashboard.data.summary'),
                'chart' => route('dashboard.data.chart'),
                'recentSales' => route('dashboard.data.recent-sales'),
                'recentLayaways' => route('dashboard.data.recent-layaways'),
                'paymentSummary' => route('dashboard.data.payment-summary'),
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->buildSummary(now()));
    }

    public function chart(Request $request): JsonResponse
    {
        $mode = strtolower((string) $request->query('mode', '7d'));
        if (! in_array($mode, ['7d', 'hourly'], true)) {
            $mode = '7d';
        }

        return response()->json($this->buildChartData(now(), $mode));
    }

    public function recentSales(Request $request): JsonResponse
    {
        return response()->json($this->buildRecentSales());
    }

    public function recentLayaways(Request $request): JsonResponse
    {
        return response()->json($this->buildRecentLayaways());
    }

    public function paymentSummary(Request $request): JsonResponse
    {
        return response()->json($this->buildPaymentSummary(now()));
    }

    private function buildSummary(Carbon $now): array
    {
        $today = $now->toDateString();

        $todaySales = Sale::query()
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled');

        $todayTotal = (float) (clone $todaySales)->sum('total');
        $todayCount = (int) (clone $todaySales)->count();

        $activeLayaways = $this->activeLayawaysQuery();

        $layawaysActiveCount = (int) (clone $activeLayaways)->count();
        $layawaysPendingTotal = (float) ((clone $activeLayaways)
            ->selectRaw('COALESCE(SUM(subtotal - paid_total), 0) as pending_total')
            ->value('pending_total') ?? 0);

        $productsAvailable = (int) Product::query()
            ->where('status', 'disponible')
            ->count();

        return [
            'date' => $today,
            'total' => round($todayTotal, 2),
            'count' => $todayCount,
            'average_ticket' => $todayCount > 0 ? round($todayTotal / $todayCount, 2) : 0,
            'layaways_active_count' => $layawaysActiveCount,
            'layaways_pending_total' => round($layawaysPendingTotal, 2),
            'products_available' => $productsAvailable,
        ];
    }

    private function buildChartData(Carbon $now, string $mode = '7d'): array
    {
        if ($mode === 'hourly') {
            return $this->buildHourlyChart($now);
        }

        return $this->buildSevenDayChart($now);
    }

    private function buildSevenDayChart(Carbon $now): array
    {
        $start = $now->copy()->subDays(self::TREND_DAYS - 1)->startOfDay();
        $end = $now->copy()->endOfDay();

        $rows = Sale::query()
            ->selectRaw('DATE(created_at) as sale_date')
            ->selectRaw('COALESCE(SUM(total), 0) as total_amount')
            ->selectRaw('COUNT(*) as sales_count')
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => (string) $row->sale_date);

        $points = [];
        for ($dayOffset = 0; $dayOffset < self::TREND_DAYS; $dayOffset++) {
            $day = $start->copy()->addDays($dayOffset);
            $key = $day->toDateString();
            $point = $rows->get($key);

            $points[] = [
                'label' => ucfirst($day->translatedFormat('D d M')),
                'date' => $key,
                'total' => round((float) ($point->total_amount ?? 0), 2),
                'sales_count' => (int) ($point->sales_count ?? 0),
            ];
        }

        return [
            'mode' => '7d',
            'labels' => array_map(fn (array $point) => $point['label'], $points),
            'series' => $points,
            'max' => (float) max(array_map(fn (array $point) => $point['total'], $points) ?: [0]),
        ];
    }

    private function buildHourlyChart(Carbon $now): array
    {
        $rows = Sale::query()
            ->selectRaw('HOUR(created_at) as sale_hour')
            ->selectRaw('COALESCE(SUM(total), 0) as total_amount')
            ->selectRaw('COUNT(*) as sales_count')
            ->whereDate('created_at', $now->toDateString())
            ->where('status', '!=', 'cancelled')
            ->groupByRaw('HOUR(created_at)')
            ->orderByRaw('HOUR(created_at)')
            ->get()
            ->keyBy(fn ($row) => (int) $row->sale_hour);

        $points = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $point = $rows->get($hour);

            $points[] = [
                'hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'total' => round((float) ($point->total_amount ?? 0), 2),
                'sales_count' => (int) ($point->sales_count ?? 0),
            ];
        }

        return [
            'mode' => 'hourly',
            'labels' => array_map(fn (array $point) => $point['hour'], $points),
            'series' => $points,
            'max' => (float) max(array_map(fn (array $point) => $point['total'], $points) ?: [0]),
        ];
    }

    private function buildRecentSales(): array
    {
        return Sale::query()
            ->with([
                'customer:id,name',
                'payments:id,sale_id,method,amount',
            ])
            ->where('status', '!=', 'cancelled')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (Sale $sale) {
                $discountAmount = (float) $sale->discount_total
                    + (float) ($sale->coupon_discount_total ?? 0)
                    + (float) ($sale->loyalty_discount_total ?? 0);

                $methods = $sale->payments
                    ->pluck('method')
                    ->filter()
                    ->unique()
                    ->map(fn (string $method) => $this->paymentMethodLabel($method))
                    ->values()
                    ->all();

                return [
                    'id' => $sale->id,
                    'folio' => str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
                    'customer' => $sale->customer?->name ?? 'Publico general',
                    'time' => $sale->created_at?->format('H:i') ?? '--:--',
                    'date' => $sale->created_at?->toDateString(),
                    'total' => round((float) $sale->total, 2),
                    'payment_method' => count($methods) > 0 ? implode(', ', $methods) : 'No registrado',
                    'discount' => round($discountAmount, 2),
                    'has_discount' => $discountAmount > 0,
                ];
            })
            ->values()
            ->all();
    }

    private function buildRecentLayaways(): array
    {
        return $this->activeLayawaysQuery()
            ->with('customer:id,name')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (Layaway $layaway) {
                $subtotal = (float) $layaway->subtotal;
                $paid = (float) $layaway->paid_total;
                $balance = max(0, $subtotal - $paid);

                return [
                    'id' => $layaway->id,
                    'folio' => str_pad((string) $layaway->id, 6, '0', STR_PAD_LEFT),
                    'customer' => $layaway->customer?->name ?? 'Sin cliente',
                    'total' => round($subtotal, 2),
                    'paid' => round($paid, 2),
                    'balance' => round($balance, 2),
                    'date' => $layaway->created_at?->toDateString(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildPaymentSummary(Carbon $now): array
    {
        $rows = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereDate('sales.created_at', $now->toDateString())
            ->where('sales.status', '!=', 'cancelled')
            ->selectRaw('sale_payments.method as method')
            ->selectRaw('COALESCE(SUM(sale_payments.amount), 0) as total_amount')
            ->selectRaw('COUNT(DISTINCT sale_payments.sale_id) as sales_count')
            ->groupBy('sale_payments.method')
            ->orderByDesc('total_amount')
            ->get();

        $methods = $rows->map(fn ($row) => [
            'method' => (string) $row->method,
            'label' => $this->paymentMethodLabel((string) $row->method),
            'total' => round((float) $row->total_amount, 2),
            'sales_count' => (int) $row->sales_count,
        ])->values()->all();

        return [
            'date' => $now->toDateString(),
            'methods' => $methods,
            'total' => round((float) collect($methods)->sum('total'), 2),
        ];
    }

    private function activeLayawaysQuery()
    {
        return Layaway::query()->whereIn('status', ['open', 'active']);
    }

    private function paymentMethodLabel(string $method): string
    {
        return match (strtolower(trim($method))) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'other' => 'Otro',
            default => ucfirst($method),
        };
    }
}
