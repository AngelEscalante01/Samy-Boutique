<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Models\Layaway;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends ApiController
{
    /**
     * GET /api/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $now = now();
        $today = $now->toDateString();

        $todaySales = Sale::query()
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled');

        $todayTotal = (float) (clone $todaySales)->sum('total');
        $todayCount = (int) (clone $todaySales)->count();

        $activeLayaways = Layaway::query()
            ->whereIn('status', ['active', 'partial']);

        $layawaysActiveCount = (int) (clone $activeLayaways)->count();
        $layawaysPendingTotal = (float) ((clone $activeLayaways)
            ->selectRaw('COALESCE(SUM(subtotal - paid_total), 0) as pending_total')
            ->value('pending_total') ?? 0);

        $productsAvailable = (int) Product::query()
            ->where('status', 'disponible')
            ->count();

        $data = [
            'date'                   => $today,
            'total'                  => round($todayTotal, 2),
            'count'                  => $todayCount,
            'average_ticket'         => $todayCount > 0 ? round($todayTotal / $todayCount, 2) : 0,
            'layaways_active_count'  => $layawaysActiveCount,
            'layaways_pending_total' => round($layawaysPendingTotal, 2),
            'products_available'     => $productsAvailable,
        ];

        return $this->successResponse($data, 'Dashboard summary');
    }
}
