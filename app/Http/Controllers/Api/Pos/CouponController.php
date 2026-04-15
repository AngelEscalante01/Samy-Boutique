<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends ApiController
{
    /**
     * GET /api/coupons
     * Returns a paginated list of coupons.
     *
     * Query params:
     *  - q: search in code or name
     *  - active: 1 | 0 (optional filter)
     *  - per_page: default 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query()->withCount('redemptions');

        if ($q = $request->query('q')) {
            $query->where(function ($builder) use ($q) {
                $builder->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', (bool) $request->query('active'));
        }

        $paginator = $query->orderByDesc('created_at')
                           ->paginate((int) ($request->query('per_page', 20)));

        return $this->paginatedResponse(
            $paginator,
            CouponResource::collection($paginator->items())->toArray($request),
        );
    }
}
