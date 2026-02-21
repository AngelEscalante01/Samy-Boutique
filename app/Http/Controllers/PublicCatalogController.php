<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $gender = trim((string) $request->query('gender', ''));
        $categoryId = (int) $request->query('category_id', 0);
        $sizeId = (int) $request->query('size_id', 0);
        $colorId = (int) $request->query('color_id', 0);
        $sort = (string) $request->query('sort', 'newest');
        $perPage = 12;

        $productsQuery = Product::query()
            ->where('status', 'disponible')
            ->with(['images', 'category', 'size', 'color'])
            ->when($q !== '', fn ($query) => $query->search($q))
            ->when(in_array($gender, ['dama', 'caballero', 'unisex'], true), fn ($query) => $query->byGender($gender))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($sizeId > 0, fn ($query) => $query->where('size_id', $sizeId))
            ->when($colorId > 0, fn ($query) => $query->where('color_id', $colorId));

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
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'sizes' => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors' => Color::query()->orderBy('name')->get(['id', 'name']),
            'whatsappNumber' => preg_replace('/\D+/', '', (string) config('samy.catalog_whatsapp_number', '')),
        ]);
    }

    public function show(Product $product): Response
    {
        abort_if($product->status !== 'disponible', 404);

        $product->load(['images', 'category', 'size', 'color']);

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
