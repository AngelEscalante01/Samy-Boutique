<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function resolveCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:80']]);
        $code = trim($data['code']);
        if (! preg_match('/^[\x20-\x7E]+$/D', $code)) {
            throw ValidationException::withMessages(['code' => 'El código debe contener caracteres imprimibles.']);
        }
        $normalized = mb_strtoupper($code, 'UTF-8');
        $owners = collect();
        foreach (Product::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])->pluck('id') as $id) {
            $owners->push(['type' => 'product', 'id' => (int) $id]);
        }
        foreach (ProductVariant::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])->pluck('id') as $id) {
            $owners->push(['type' => 'variant', 'id' => (int) $id]);
        }
        foreach (DB::table('catalog_codes')->where('code_key', hash('sha256', $normalized))->whereNotNull('owner_id')->get(['owner_type', 'owner_id']) as $alias) {
            $owners->push(['type' => $alias->owner_type, 'id' => (int) $alias->owner_id]);
        }
        $owners = $owners->filter(fn ($owner) => in_array($owner['type'], ['product', 'variant'], true))
            ->unique(fn ($owner) => $owner['type'].':'.$owner['id'])->values();
        if ($owners->count() !== 1) {
            return response()->json(['status' => $owners->isEmpty() ? 'unknown' : 'ambiguous']);
        }

        $owner = $owners->first();
        $variant = $owner['type'] === 'variant' ? ProductVariant::query()->find($owner['id']) : null;
        $product = Product::query()->with(['category', 'size', 'color', 'images', 'variants.size', 'variants.color'])
            ->find($variant?->product_id ?? ($owner['type'] === 'product' ? $owner['id'] : 0));
        if (! $product || ($variant && ! $variant->active)) {
            return response()->json(['status' => 'inactive']);
        }
        if ($variant && (int) $variant->stock <= 0) {
            return response()->json(['status' => 'out_of_stock']);
        }
        $sellable = $product->variants->filter(fn ($item) => $item->active && (int) $item->stock > 0);
        if ($sellable->isEmpty()) {
            return response()->json(['status' => $product->variants->isEmpty() ? 'inactive' : 'out_of_stock']);
        }

        return response()->json([
            'status' => $variant ? 'matched_variant' : 'select_variant',
            'variant_id' => $variant?->id,
            'product' => (new ProductResource($product))->resolve($request),
        ]);
    }

    public function index(Request $request, InventoryService $inventoryService): Response
    {
        $q          = (string) $request->query('q', '');
        $gender     = (string) $request->query('gender', '');
        $categoryId = (int)    $request->query('category_id', 0);

        $products = Product::query()
            ->search($q)
            ->when($gender !== '', fn ($query) => $query->byGender($gender))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->with([
                'category',
                'images',
                'variants' => fn ($query) => $query
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->with(['size', 'color']),
            ])
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
            ->get(['id', 'name', 'phone', 'photo_path', 'photo_generation'])
            ->map(fn ($customer) => [
                'id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone,
                'photo_url' => $request->user()->can('customers.view') && $customer->photo_path
                    ? route('customers.photo', ['customer' => $customer->id, 'generation' => $customer->photo_generation]) : null,
            ]);

        return response()->json($customers);
    }
}

