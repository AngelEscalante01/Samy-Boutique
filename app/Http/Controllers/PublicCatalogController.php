<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCatalogController extends Controller
{
    public function index(Request $request, InventoryService $inventoryService): Response
    {
        $q = trim((string) $request->query('q', ''));
        $gender = trim((string) $request->query('gender', ''));
        $categoryId = (int) $request->query('category_id', 0);
        $sizeId = (int) $request->query('size_id', 0);
        $colorId = (int) $request->query('color_id', 0);
        $sort = (string) $request->query('sort', 'newest');
        $perPage = 12;

        $productsQuery = Product::query()
            ->with([
                'images',
                'category',
                'variants' => fn ($query) => $query
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->with(['size', 'color']),
            ])
            ->whereHas('variants', fn ($query) => $query
                ->where('active', true)
                ->where('stock', '>', 0))
            ->when($q !== '', fn ($query) => $query->search($q))
            ->when(in_array($gender, ['dama', 'caballero', 'unisex'], true), fn ($query) => $query->byGender($gender))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->when($sizeId > 0, function ($query) use ($sizeId) {
                $query->whereHas('variants', fn ($variantQuery) => $variantQuery
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->where('size_id', $sizeId));
            })
            ->when($colorId > 0, function ($query) use ($colorId) {
                $query->whereHas('variants', fn ($variantQuery) => $variantQuery
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->where('color_id', $colorId));
            });

        if ($sort === 'price_asc') {
            $productsQuery->orderBy('sale_price');
        } elseif ($sort === 'price_desc') {
            $productsQuery->orderByDesc('sale_price');
        } else {
            $productsQuery->latest('id');
            $sort = 'newest';
        }

        $products = $productsQuery->paginate($perPage)->withQueryString();

        return Inertia::render('Public/Catalog/Index', [
            'filters' => [
                'q' => $q,
                'gender' => $gender,
                'category_id' => $categoryId,
                'size_id' => $sizeId,
                'color_id' => $colorId,
                'sort' => $sort,
            ],
            'products' => ProductResource::collection($products),
            'pagination' => [
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
            ],
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'sizes' => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors' => Color::query()->orderBy('name')->get(['id', 'name']),
            'whatsappNumber' => preg_replace('/\D+/', '', (string) config('samy.catalog_whatsapp_number', '')),
        ]);
    }

    public function show(Product $product): Response
    {
        $hasSellableVariants = $product->variants()
            ->where('active', true)
            ->where('stock', '>', 0)
            ->exists();

        abort_unless($hasSellableVariants, 404);

        $product->load([
            'images',
            'category',
            'variants' => fn ($query) => $query
                ->where('active', true)
                ->where('stock', '>', 0)
                ->with(['size', 'color']),
        ]);

        return Inertia::render('Public/Catalog/Show', [
            'product' => (new ProductResource($product))->resolve(),
            'whatsappNumber' => preg_replace('/\D+/', '', (string) config('samy.catalog_whatsapp_number', '')),
        ]);
    }

    public function offline(): Response
    {
        return Inertia::render('Public/Offline');
    }
}
