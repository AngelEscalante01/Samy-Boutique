<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\PosProductResource;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProductController extends ApiController
{
    public function index(Request $request, InventoryService $inventoryService): JsonResponse
    {
        $this->ensureAnyPermission($request, ['pos.view', 'products.view']);

        $validated = $request->validate([
            'q'           => ['nullable', 'string', 'max:120'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'gender'      => ['nullable', 'string', 'in:dama,caballero,unisex'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            // Nombre recomendado para sincronización incremental.
            'updated_after' => ['nullable', 'date'],
            // Alias legacy para clientes existentes.
            'updated_since' => ['nullable', 'date'],
            // Si true, incluye productos no vendibles (apartado/vendido/cancelado/sin stock).
            'include_inactive' => ['nullable', 'boolean'],
            // Reservado para compatibilidad futura (tombstones de eliminados).
            'include_deleted' => ['nullable', 'boolean'],
        ]);

        $search     = trim((string) ($validated['q'] ?? ''));
        $perPage    = (int) ($validated['per_page'] ?? 30);
        $gender     = $validated['gender'] ?? null;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $updatedAfterRaw = $validated['updated_after'] ?? $validated['updated_since'] ?? null;
        $updatedAfter = $updatedAfterRaw !== null
            ? Carbon::parse((string) $updatedAfterRaw)
            : null;
        $includeInactive = (bool) ($validated['include_inactive'] ?? false);
        $includeDeleted = (bool) ($validated['include_deleted'] ?? false);

        $productsQuery = Product::query()
            ->select([
                'id',
                'sku',
                'name',
                'gender',
                'category_id',
                'sale_price',
                'sale_price_base',
                'status',
                'created_at',
                'updated_at',
            ])
            ->when($gender !== null, fn ($q) => $q->where('gender', $gender))
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->when($updatedAfter !== null, function ($query) use ($updatedAfter) {
                $query->where(function ($syncQuery) use ($updatedAfter) {
                    $syncQuery
                        ->whereRaw('COALESCE(products.updated_at, products.created_at) > ?', [$updatedAfter])
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery
                            ->whereRaw('COALESCE(updated_at, created_at) > ?', [$updatedAfter]))
                        ->orWhereHas('images', fn ($imageQuery) => $imageQuery
                            ->whereRaw('COALESCE(updated_at, created_at, deleted_at) > ?', [$updatedAfter]));
                });
            })
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
                'category:id,name,active',
                'images:id,product_id,path,sort,created_at,updated_at,deleted_at',
                'variants' => function ($query) use ($includeInactive) {
                    $query
                        ->select('id', 'product_id', 'size_id', 'color_id', 'sku', 'sale_price', 'stock', 'active', 'created_at', 'updated_at')
                        ->with(['size:id,name', 'color:id,name,hex'])
                        ->orderByDesc('stock')
                        ->orderBy('id');

                    if (! $includeInactive) {
                        $query
                            ->where('active', true)
                            ->where('stock', '>', 0);
                    }
                },
            ]);

        if (! $includeInactive) {
            $productsQuery
                ->where('status', 'disponible')
                ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query));
        }

        if ($updatedAfter !== null) {
            $productsQuery
                ->orderByRaw('COALESCE(products.updated_at, products.created_at)')
                ->orderBy('products.id');
        } else {
            $productsQuery->orderBy('name');
        }

        $products = $productsQuery
            ->paginate($perPage)
            ->withQueryString();

        $items = PosProductResource::collection($products->getCollection())->resolve($request);

        $lastChangeAt = collect($items)
            ->pluck('sync_updated_at')
            ->filter()
            ->max();

        $data = [
            'items' => $items,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'has_more_pages' => $products->hasMorePages(),
            ],
            'sync' => [
                'requested_updated_after' => $updatedAfterRaw,
                'effective_updated_after' => $updatedAfter?->toISOString(),
                'last_change_at' => $lastChangeAt,
                'server_time' => now()->toISOString(),
                'includes_inactive' => $includeInactive,
                'includes_deleted' => $includeDeleted,
                'deleted_tracking' => false,
            ],
        ];

        return $this->successResponse($data, 'Productos POS obtenidos correctamente.');
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
                'created_at',
                'updated_at',
            ])
            ->whereKey($id)
            ->where('status', 'disponible')
            ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->with([
                'category:id,name',
                'images:id,product_id,path,sort,created_at,updated_at,deleted_at',
                'variants' => fn ($query) => $query
                    ->select('id', 'product_id', 'size_id', 'color_id', 'sku', 'sale_price', 'stock', 'active', 'created_at', 'updated_at')
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
