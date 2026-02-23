<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $q          = (string) $request->query('q', '');
        $gender     = (string) $request->query('gender', '');
        $categoryId = (int)    $request->query('category_id', 0);

        $products = Product::query()
            ->available()
            ->search($q)
            ->when($gender !== '', fn ($query) => $query->byGender($gender))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->with(['size', 'color', 'category', 'images'])
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        $categories = Category::where('active', true)->orderBy('name')->get(['id', 'name']);

        $user = $request->user();

        return Inertia::render('POS/Index', [
            'filters' => [
                'q'           => $q,
                'gender'      => $gender,
                'category_id' => $categoryId,
            ],
            'products'   => ProductResource::collection($products),
            'categories' => $categories,
            'can' => [
                'createSale'         => $user->can('sales.create'),
                'applyDiscountBasic' => $user->can('sales.apply_discount_basic'),
                'applyDiscountHigh'  => $user->can('sales.apply_discount_high'),
                'applyCoupon'        => $user->can('sales.apply_coupon'),
            ],
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        $customers = Customer::query()
            ->when($q !== '', fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"))
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers);
    }
}

