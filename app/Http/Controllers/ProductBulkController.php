<?php

namespace App\Http\Controllers;

use App\Actions\Products\StoreProductImagesAction;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreProductPhotosRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductBulkController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'import_batch' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:180'],
            'recent_days' => ['nullable', Rule::in(['7', '30', '90'])],
        ]);
        $ids = $data['ids'] ?? null;
        if (($data['import_batch'] ?? null) !== null) {
            $ids = DB::table('catalog_import_groups')
                ->join('catalog_import_batches', 'catalog_import_batches.id', '=', 'catalog_import_groups.batch_id')
                ->where('catalog_import_batches.id', $data['import_batch'])
                ->where('catalog_import_batches.user_id', $request->user()->id)
                ->whereNotNull('catalog_import_groups.product_id')
                ->limit(100)->pluck('catalog_import_groups.product_id')->all();
        }
        $query = Product::query()
            ->with(['category', 'images.derivatives', 'variants' => fn ($variants) => $variants->where('active', true)->with(['size', 'color'])])
            ->orderByDesc('created_at')->orderByDesc('id');
        if ($ids !== null) {
            $query->whereKey($ids);
        } else {
            if (($data['q'] ?? '') !== '') {
                $term = $data['q'];
                $query->where(fn ($products) => $products->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%'));
            }
            if (($data['recent_days'] ?? '') !== '') {
                $query->where('created_at', '>=', now()->subDays((int) $data['recent_days']));
            }
        }
        $page = $ids === null ? $query->paginate(50)->withQueryString() : null;
        $products = $page ? $page->getCollection() : $query->get();

        return Inertia::render('Products/Bulk', [
            'products' => ProductResource::collection($products)->resolve(),
            'pagination' => $page ? [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'previous' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ] : null,
            'filters' => ['q' => $data['q'] ?? '', 'recent_days' => $data['recent_days'] ?? ''],
        ]);
    }

    public function storeImages(StoreProductPhotosRequest $request, Product $product, StoreProductImagesAction $storeProductImages): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($product, $data, $request, $storeProductImages) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $locked->updated_at->equalTo(\Illuminate\Support\Carbon::parse($data['expected_updated_at']))) {
                throw ValidationException::withMessages(['expected_updated_at' => 'Este producto cambió desde que lo abriste. Recarga antes de agregar fotos.']);
            }
            $variantMap = $locked->variants()->pluck('id')->mapWithKeys(fn ($id) => ['v_'.$id => $id])->all();
            $assignments = app(\App\Services\ProductImageAssignments::class)->uploads($data, $variantMap);
            $storeProductImages->execute($locked, $request->file('images'), $assignments);
        });

        $product = $product->fresh()->load(['category', 'images.derivatives', 'variants' => fn ($variants) => $variants->where('active', true)->with(['size', 'color'])]);

        return response()->json(['data' => (new ProductResource($product))->resolve()]);
    }
}
