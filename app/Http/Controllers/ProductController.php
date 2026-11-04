<?php

namespace App\Http\Controllers;

use App\Actions\Products\StoreProductImagesAction;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Color;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\InventoryService;
use App\Services\CatalogCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $status     = $request->string('status')->toString() ?: 'disponible';
        $query      = $request->string('q')->toString();
        $gender     = $request->string('gender')->toString();
        $categoryId = (int) $request->query('category_id', 0);

        $products = Product::query()
            ->with(['category', 'size', 'color', 'images', 'occasions', 'variants.size', 'variants.color'])
            ->when($request->filled('import_batch'), fn ($q) => $q->whereIn('id', DB::table('catalog_import_groups')->join('catalog_import_batches', 'catalog_import_batches.id', '=', 'catalog_import_groups.batch_id')->where('catalog_import_batches.id', $request->input('import_batch'))->where('catalog_import_batches.user_id', $request->user()->id)->whereNotNull('product_id')->select('product_id')))
            ->when(in_array($status, ['disponible', 'apartado', 'vendido'], true), fn ($q) => $q->where('status', $status))
            ->when($gender !== '', fn ($q) => $q->byGender($gender))
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->search($query)
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Products/Index', [
            'filters' => [
                'status'      => $status,
                'q'           => $query,
                'gender'      => $gender,
                'category_id' => $categoryId,
            ],
            'labelTable' => [
                'open' => $request->query('labels') === '1',
                'q' => mb_substr((string) $request->query('labels_q', ''), 0, 120),
                'recent_days' => in_array((string) $request->query('labels_recent_days', ''), ['7', '30', '90'], true)
                    ? (string) $request->query('labels_recent_days') : '',
            ],
            'products'   => ProductResource::collection($products),
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'sku_prefix']),
            'sizes'      => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors'     => Color::query()->orderBy('name')->get(['id', 'name']),
            'occasions' => Occasion::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'can' => [
                'create'           => $user->can('products.create'),
                'update'           => $user->can('products.update'),
                'deleteImages'     => $user->can('products.delete_images'),
                'viewPurchasePrice'=> $user->can('products.view_purchase_price'),
            ],
        ]);
    }

    public function show(Product $product, Request $request): Response
    {
        $product->load(['category', 'size', 'color', 'images.derivatives', 'occasions', 'variants.size', 'variants.color']);

        return Inertia::render('Products/Show', [
            'product' => (new ProductResource($product))->resolve(),
            'barcodes' => app(\App\Services\ProductLabelBarcode::class)->productPreviews($product),
            'can' => [
                'update' => $request->user()->can('products.update'),
                'viewPurchasePrice' => $request->user()->can('products.view_purchase_price'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Products/Create', [
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'sku_prefix']),
            'sizes'      => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors'     => Color::query()->orderBy('name')->get(['id', 'name', 'hex']),
            'occasions' => Occasion::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'can' => [
                'viewPurchasePrice' => $user->can('products.view_purchase_price'),
            ],
        ]);
    }

    public function edit(Product $product, Request $request): Response
    {
        $product->load(['category', 'size', 'color', 'images', 'occasions', 'variants' => fn ($query) => $query->where('active', true)->with(['size', 'color'])]);
        $user = $request->user();

        return Inertia::render('Products/Edit', [
            'product'    => (new ProductResource($product))->resolve(),
            'barcodes' => app(\App\Services\ProductLabelBarcode::class)->productPreviews($product),
            'photoVariants' => $product->variants()->with(['size', 'color'])->get()->map(fn ($variant) => [
                'id' => $variant->id, 'sku' => $variant->sku, 'active' => (bool) $variant->active,
                'size' => ['id' => $variant->size?->id, 'name' => $variant->size?->name],
                'color' => ['id' => $variant->color?->id, 'name' => $variant->color?->name],
            ]),
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'sku_prefix']),
            'sizes'      => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors'     => Color::query()->orderBy('name')->get(['id', 'name', 'hex']),
            'occasions' => Occasion::query()->where(fn ($query) => $query->where('active', true)->orWhereHas('products', fn ($products) => $products->whereKey($product->id)))->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'can' => [
                'viewPurchasePrice' => $user->can('products.view_purchase_price'),
                'deleteImages'      => $user->can('products.delete_images'),
            ],
        ]);
    }

    public function store(
        StoreProductRequest $request,
        StoreProductImagesAction $storeProductImages,
        InventoryService $inventoryService
    ): RedirectResponse
    {
        $validated = $request->validated();
        $variantsPayload = collect($validated['variants'] ?? []);

        $this->assertUniqueVariantCombinations($variantsPayload);

        $primaryVariant = $variantsPayload->first();
        $modelPurchasePrice = $primaryVariant['purchase_price'] ?? $validated['purchase_price'] ?? 0;
        $modelSalePrice = $primaryVariant['sale_price'] ?? $validated['sale_price_base'] ?? $validated['sale_price'] ?? 0;
        $status = $validated['status'] ?? 'disponible';

        $product = DB::transaction(function () use (
            $request,
            $validated,
            $variantsPayload,
            $storeProductImages,
            $inventoryService,
            $primaryVariant,
            $modelPurchasePrice,
            $modelSalePrice,
            $status
        ) {
            $codes = app(CatalogCodeService::class);
            $modelSku = $codes->next((int) $validated['category_id'], 'product');
            $product = Product::create([
                'sku' => $modelSku,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $validated['category_id'],
                'gender' => $validated['gender'],
                'size_id' => $primaryVariant['size_id'],
                'color_id' => $primaryVariant['color_id'],
                'purchase_price' => $modelPurchasePrice,
                'sale_price' => $modelSalePrice,
                'sale_price_base' => $validated['sale_price_base'] ?? null,
                'status' => $status,
                'created_by' => $request->user()->id,
                'sold_at' => $validated['sold_at'] ?? null,
                'catalog_published' => count($request->file('images', [])) > 0,
            ]);

            $codes->bind($modelSku, 'product', (int) $product->id);
            $product->occasions()->sync($validated['occasion_ids'] ?? []);

            $variantMap = [];
            foreach ($variantsPayload as $variantData) {
                $variantSku = $codes->next((int) $product->category_id, 'variant');
                $variant = ProductVariant::query()->create([
                    'sku' => $variantSku,
                    'product_id' => $product->id,
                    'size_id' => $variantData['size_id'],
                    'color_id' => $variantData['color_id'],
                    'stock' => (int) $variantData['stock'],
                    'purchase_price' => $variantData['purchase_price'] ?? null,
                    'sale_price' => $variantData['sale_price'] ?? null,
                    'active' => true,
                ]);
                $codes->bind($variantSku, 'variant', (int) $variant->id);
                if (! empty($variantData['client_key'])) $variantMap[$variantData['client_key']] = (int) $variant->id;
            }

            $inventoryService->updateSoldOutAt($product->id);

            /** @var array<int, \Illuminate\Http\UploadedFile> $images */
            $images = $request->file('images', []);
            $assignments = app(\App\Services\ProductImageAssignments::class);
            $storeProductImages->execute($product, $images, $assignments->uploads($validated, $variantMap));

            return $product;
        });

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Producto creado.');
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        StoreProductImagesAction $storeProductImages,
        InventoryService $inventoryService
    ): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $variantsPayload = collect($validated['variants'] ?? []);

        $this->assertUniqueVariantCombinations($variantsPayload);

        $primaryVariant = $variantsPayload->first();
        $modelSku = $product->sku;
        $modelPurchasePrice = $primaryVariant['purchase_price'] ?? $validated['purchase_price'] ?? $product->purchase_price;
        $modelSalePrice = $primaryVariant['sale_price'] ?? $validated['sale_price_base'] ?? $validated['sale_price'] ?? $product->sale_price;
        $status = $validated['status'] ?? $product->status;

        DB::transaction(function () use (
            $request,
            $product,
            $validated,
            $variantsPayload,
            $storeProductImages,
            $inventoryService,
            $primaryVariant,
            $modelSku,
            $modelPurchasePrice,
            $modelSalePrice,
            $status
        ) {
            Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->refresh();
            if (isset($validated['expected_updated_at'])
                && ! $product->updated_at->equalTo(\Illuminate\Support\Carbon::parse($validated['expected_updated_at']))) {
                throw ValidationException::withMessages(['expected_updated_at' => 'Este producto cambió desde que lo abriste. Recarga antes de guardarlo.']);
            }
            $product->update([
                'sku' => $modelSku,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $validated['category_id'],
                'gender' => $validated['gender'],
                'size_id' => $primaryVariant['size_id'],
                'color_id' => $primaryVariant['color_id'],
                'purchase_price' => $modelPurchasePrice,
                'sale_price' => $modelSalePrice,
                'sale_price_base' => $validated['sale_price_base'] ?? null,
                'status' => $status,
                'sold_at' => $validated['sold_at'] ?? null,
            ]);
            $product->occasions()->sync($validated['occasion_ids'] ?? []);

            $existingVariants = $product->variants()->lockForUpdate()->get()->keyBy('id');
            $variantMap = $existingVariants->mapWithKeys(fn ($variant) => ['v_'.$variant->id => (int) $variant->id])->all();
            $incomingIds = [];

            foreach ($variantsPayload as $variantData) {
                $variantId = isset($variantData['id']) ? (int) $variantData['id'] : null;

                if ($variantId !== null && $existingVariants->has($variantId)) {
                    if (isset($variantData['expected_stock']) && (int) $variantData['expected_stock'] !== (int) $existingVariants->get($variantId)->stock) {
                        throw ValidationException::withMessages(['variants' => 'Las existencias cambiaron desde que abriste el producto. Recarga antes de guardar.']);
                    }
                    $incomingIds[] = $variantId;
                    if (! empty($variantData['client_key'])) $variantMap[$variantData['client_key']] = $variantId;

                    $existingVariants->get($variantId)?->update([
                        'size_id' => $variantData['size_id'],
                        'color_id' => $variantData['color_id'],
                        'stock' => (int) $variantData['stock'],
                        'purchase_price' => $request->user()->can('products.view_purchase_price')
                            ? ($variantData['purchase_price'] ?? null)
                            : $existingVariants->get($variantId)?->purchase_price,
                        'sale_price' => $variantData['sale_price'] ?? null,
                        'active' => true,
                    ]);

                    continue;
                }

                $codes = app(CatalogCodeService::class);
                $variantSku = $codes->next((int) $product->category_id, 'variant');
                $created = ProductVariant::query()->create([
                    'sku' => $variantSku,
                    'product_id' => $product->id,
                    'size_id' => $variantData['size_id'],
                    'color_id' => $variantData['color_id'],
                    'stock' => (int) $variantData['stock'],
                    'purchase_price' => $variantData['purchase_price'] ?? null,
                    'sale_price' => $variantData['sale_price'] ?? null,
                    'active' => true,
                ]);

                $codes->bind($variantSku, 'variant', (int) $created->id);
                $incomingIds[] = $created->id;
                if (! empty($variantData['client_key'])) $variantMap[$variantData['client_key']] = (int) $created->id;
            }

            $product->variants()
                ->whereNotIn('id', $incomingIds)
                ->update(['active' => false]);

            $inventoryService->updateSoldOutAt($product->id);

            /** @var array<int, \Illuminate\Http\UploadedFile> $images */
            $images = $request->file('images', []);
            $assignments = app(\App\Services\ProductImageAssignments::class);
            $assignments->updateExisting($product, $validated, $variantMap);
            $storeProductImages->execute($product, $images, $assignments->uploads($validated, $variantMap));
            if (array_key_exists('catalog_published', $validated)) {
                if ($validated['catalog_published'] && ! $product->images()->exists()) {
                    throw ValidationException::withMessages(['catalog_published' => 'Agrega una foto antes de publicar en el catálogo.']);
                }
                $product->update(['catalog_published' => (bool) $validated['catalog_published']]);
            }
        });

        if ($request->expectsJson()) {
            $product = $product->fresh()->load(['category', 'size', 'color', 'images.derivatives', 'occasions', 'variants' => fn ($query) => $query->where('active', true)->with(['size', 'color'])]);

            return response()->json(['data' => (new ProductResource($product))->resolve()]);
        }

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Producto actualizado.');
    }

    /**
     * Borra una imagen de producto: elimina archivo físico + registro DB.
     * Requisito: solo gerente con permiso products.delete_images.
     */
    public function destroyImage(Product $product, ProductImage $productImage): RedirectResponse
    {
        if ((int) $productImage->product_id !== (int) $product->id) {
            abort(404);
        }

        DB::transaction(function () use ($productImage) {
            $image = ProductImage::query()->lockForUpdate()->findOrFail($productImage->id);
            if ($image->deleted_at !== null) {
                return;
            }
            // Persist the tombstone before touching remote storage. Scheduler
            // retries the durable intent even if queue dispatch is unavailable.
            $image->forceFill(['deleted_at' => now(), 'deleted_by' => auth()->id(), 'deletion_state' => 'pending'])->save();
            if (! $image->product->images()->exists()) {
                $image->product->update(['catalog_published' => false]);
            }
        });

        return back()->with('success', 'Imagen retirada; eliminación del archivo en cola.');
    }

    public function reorderImages(Product $product, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'image_ids' => ['required', 'array', 'min:1', 'max:100'],
            'image_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        DB::transaction(function () use ($product, $data) {
            $images = ProductImage::query()->where('product_id', $product->id)->whereNull('deleted_at')
                ->orderBy('id')->lockForUpdate()->get();
            if ($images->pluck('id')->sort()->values()->all() !== collect($data['image_ids'])->sort()->values()->all()) {
                throw ValidationException::withMessages(['image_ids' => 'La lista debe contener todas las imágenes activas del producto.']);
            }
            foreach ($data['image_ids'] as $sort => $id) {
                $images->firstWhere('id', (int) $id)->update(['sort' => $sort]);
            }
            $product->touch();
        });

        return back()->with('success', 'Orden de imágenes actualizado.');
    }

    public function assignImageVariant(Product $product, ProductImage $productImage, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expected_updated_at' => ['sometimes', 'required', 'date'],
            'variant_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
            'variant_ids' => ['sometimes', 'array', 'max:100'],
            'variant_ids.*' => ['required', 'integer', 'distinct', \Illuminate\Validation\Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ]);
        DB::transaction(function () use ($product, $productImage, $data) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (isset($data['expected_updated_at']) && ! $locked->updated_at->equalTo(\Illuminate\Support\Carbon::parse($data['expected_updated_at']))) {
                throw ValidationException::withMessages(['expected_updated_at' => 'Este producto cambió desde que lo abriste. Recarga antes de asignar fotos.']);
            }
            $image = ProductImage::query()->lockForUpdate()->findOrFail($productImage->id);
            abort_unless($image->product_id === $product->id && $image->deleted_at === null, 404);
            app(\App\Services\ProductImageAssignments::class)->assign($image, $data['variant_ids'] ?? array_filter([$data['variant_id'] ?? null]));
        });

        return back()->with('success', 'Asociación de la foto actualizada.');
    }

    private function assertUniqueVariantCombinations($variantsPayload): void
    {
        $combinations = $variantsPayload
            ->map(fn ($variant) => sprintf('%s-%s', $variant['size_id'], $variant['color_id']));

        if ($combinations->count() !== $combinations->unique()->count()) {
            throw ValidationException::withMessages([
                'variants' => ['No puedes repetir la misma combinación talla/color.'],
            ]);
        }
    }

}
