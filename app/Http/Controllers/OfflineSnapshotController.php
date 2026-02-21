<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class OfflineSnapshotController extends Controller
{
    public function meta(): JsonResponse
    {
        $available = Product::query()->where('status', 'disponible');

        $productsCount = (int) $available->count();
        $latestUpdatedAt = Product::query()
            ->where('status', 'disponible')
            ->max('updated_at');

        $latest = $latestUpdatedAt ? Carbon::parse($latestUpdatedAt) : null;

        $version = sha1(($latest?->timestamp ?? 0) . '|' . $productsCount);

        return response()->json([
            'version' => $version,
            'generated_at' => now()->toIso8601String(),
            'products_count' => $productsCount,
        ]);
    }

    public function snapshot(): JsonResponse
    {
        $products = Product::query()
            ->where('status', 'disponible')
            ->with([
                'category:id,name',
                'size:id,name',
                'color:id,name,hex',
                'images:id,product_id,path,sort',
            ])
            ->orderByDesc('id')
            ->get();

        $latestUpdatedAt = $products->max('updated_at');
        $version = sha1((optional($latestUpdatedAt)->timestamp ?? 0) . '|' . $products->count());

        $payloadProducts = $products->map(function (Product $product) {
            $image = $product->images->sortBy('sort')->first();
            $imageUrl = $image?->url;

            if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
                $imageUrl = url($imageUrl);
            }

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'sale_price' => (float) $product->sale_price,
                'status' => $product->status,
                'gender' => $product->gender,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'size' => $product->size ? [
                    'id' => $product->size->id,
                    'name' => $product->size->name,
                ] : null,
                'color' => $product->color ? [
                    'id' => $product->color->id,
                    'name' => $product->color->name,
                    'hex' => $product->color->hex,
                ] : null,
                'image_url' => $imageUrl,
                'updated_at' => optional($product->updated_at)->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'version' => $version,
            'generated_at' => now()->toIso8601String(),
            'products' => $payloadProducts,
            'catalogs' => [
                'categories' => Category::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->values(),
                'sizes' => Size::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->values(),
                'colors' => Color::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'hex'])
                    ->values(),
            ],
        ]);
    }
}
