<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\PosProductResource;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    public function index(Request $request, InventoryService $inventoryService): JsonResponse
    {
        $this->ensureAnyPermission($request, ['pos.view', 'products.view']);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 30);

        $products = Product::query()
            ->select([
                'id',
                'sku',
                'name',
                'category_id',
                'sale_price',
                'sale_price_base',
                'status',
            ])
            ->where('status', 'disponible')
            ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";

                $query->where(function ($subQuery) use ($search, $like) {
                    $subQuery
                        ->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('id', $search)
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('sku', 'like', $like));
                });
            })
            ->with([
                'category:id,name',
                'images:id,product_id,path,sort',
                'variants' => fn ($query) => $query
                    ->select('id', 'product_id', 'size_id', 'color_id', 'sku', 'sale_price', 'stock', 'active')
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->with(['size:id,name', 'color:id,name,hex'])
                    ->orderByDesc('stock')
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $items = PosProductResource::collection($products->getCollection())->resolve($request);

        return $this->paginatedResponse($products, $items, 'Productos POS obtenidos correctamente.');
    }

    public function show(int $id, Request $request, InventoryService $inventoryService): JsonResponse
    {
        $this->ensureAnyPermission($request, ['pos.view', 'products.view']);

        $product = Product::query()
            ->select([
                'id',
                'sku',
                'name',
                'category_id',
                'sale_price',
                'sale_price_base',
                'status',
            ])
            ->whereKey($id)
            ->where('status', 'disponible')
            ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->with([
                'category:id,name',
                'images:id,product_id,path,sort',
                'variants' => fn ($query) => $query
                    ->select('id', 'product_id', 'size_id', 'color_id', 'sku', 'sale_price', 'stock', 'active')
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->with(['size:id,name', 'color:id,name,hex'])
                    ->orderByDesc('stock')
                    ->orderBy('id'),
            ])
            ->firstOrFail();

        return $this->successResponse(
            (new PosProductResource($product))->resolve($request),
            'Producto POS obtenido correctamente.'
        );
    }
}
