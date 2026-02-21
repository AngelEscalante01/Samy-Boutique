<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();
        $active = $request->string('active')->toString();

        $coupons = Coupon::query()
            ->withCount('redemptions')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', (int) $active === 1))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Coupons/Index', [
            'filters' => [
                'q' => $q,
                'active' => $active,
            ],
            'coupons' => CouponResource::collection($coupons),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Coupons/Create');
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $coupon = Coupon::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'] ?? null,
            'active' => (bool) $validated['active'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'min_total' => $validated['min_total'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_redemptions_per_customer' => $validated['max_redemptions_per_customer'] ?? null,
        ]);

        return redirect()->route('coupons.edit', $coupon)->with('success', 'Cupón creado.');
    }

    public function edit(Coupon $coupon): Response
    {
        $coupon->loadCount('redemptions');

        return Inertia::render('Coupons/Edit', [
            'coupon' => new CouponResource($coupon),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validated();

        $coupon->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'] ?? null,
            'active' => (bool) $validated['active'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'min_total' => $validated['min_total'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_redemptions_per_customer' => $validated['max_redemptions_per_customer'] ?? null,
        ]);

        return redirect()->route('coupons.edit', $coupon)->with('success', 'Cupón actualizado.');
    }
}
