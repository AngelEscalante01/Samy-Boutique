<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'products.view');

        $validated = $request->validate([
            'status' => ['nullable', 'in:disponible,apartado,vendido'],
            'q' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'max:30'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = (string) ($validated['status'] ?? 'disponible');
        $query = trim((string) ($validated['q'] ?? ''));
        $gender = trim((string) ($validated['gender'] ?? ''));
        $categoryId = (int) ($validated['category_id'] ?? 0);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $products = Product::query()
            ->with([
                'category',
                'size',
                'color',
                'images',
                'variants.size',
                'variants.color',
            ])
            ->when(in_array($status, ['disponible', 'apartado', 'vendido'], true), fn ($q) => $q->where('status', $status))
            ->when($gender !== '', fn ($q) => $q->byGender($gender))
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->search($query)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = ProductResource::collection($products->getCollection())->resolve($request);

        return $this->paginatedResponse($products, $items, 'Productos obtenidos correctamente.');
    }

    public function show(Product $product, Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'products.view');

        $product->load([
            'category',
            'size',
            'color',
            'images',
            'variants.size',
            'variants.color',
        ]);

        return $this->successResponse(
            (new ProductResource($product))->resolve($request),
            'Producto obtenido correctamente.'
        );
    }
}
