<?php

namespace App\Http\Controllers;

use App\Actions\Products\StoreProductImagesAction;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
            ->with(['category', 'size', 'color', 'images'])
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
            'products'   => ProductResource::collection($products),
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'sizes'      => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors'     => Color::query()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'create'           => $user->can('products.create'),
                'update'           => $user->can('products.update'),
                'deleteImages'     => $user->can('products.delete_images'),
                'viewPurchasePrice'=> $user->can('products.view_purchase_price'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Products/Create', [
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'sizes'      => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors'     => Color::query()->orderBy('name')->get(['id', 'name', 'hex']),
            'can' => [
                'viewPurchasePrice' => $user->can('products.view_purchase_price'),
            ],
        ]);
    }

    public function edit(Product $product, Request $request): Response
    {
        $product->load(['category', 'size', 'color', 'images', 'variants.size', 'variants.color']);
        $user = $request->user();

        return Inertia::render('Products/Edit', [
            'product'    => (new ProductResource($product))->resolve(),
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'sizes'      => Size::query()->orderBy('name')->get(['id', 'name']),
            'colors'     => Color::query()->orderBy('name')->get(['id', 'name', 'hex']),
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
        $modelSku = $validated['sku'] ?? $this->generateSku((string) $validated['name']);
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
            $modelSku,
            $modelPurchasePrice,
            $modelSalePrice,
            $status
        ) {
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
            ]);

            foreach ($variantsPayload as $variantData) {
                ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'size_id' => $variantData['size_id'],
                    'color_id' => $variantData['color_id'],
                    'stock' => (int) $variantData['stock'],
                    'purchase_price' => $variantData['purchase_price'] ?? null,
                    'sale_price' => $variantData['sale_price'] ?? null,
                    'active' => true,
                ]);
            }

            $inventoryService->updateSoldOutAt($product->id);

            /** @var array<int, \Illuminate\Http\UploadedFile> $images */
            $images = $request->file('images', []);
            $storeProductImages->execute($product, $images);

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
    ): RedirectResponse
    {
        $validated = $request->validated();
        $variantsPayload = collect($validated['variants'] ?? []);

        $this->assertUniqueVariantCombinations($variantsPayload);

        $primaryVariant = $variantsPayload->first();
        $modelSku = $validated['sku'] ?: $product->sku;
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

            $existingVariants = $product->variants()->get()->keyBy('id');
            $incomingIds = [];

            foreach ($variantsPayload as $variantData) {
                $variantId = isset($variantData['id']) ? (int) $variantData['id'] : null;

                if ($variantId !== null && $existingVariants->has($variantId)) {
                    $incomingIds[] = $variantId;

                    $existingVariants->get($variantId)?->update([
                        'size_id' => $variantData['size_id'],
                        'color_id' => $variantData['color_id'],
                        'stock' => (int) $variantData['stock'],
                        'purchase_price' => $variantData['purchase_price'] ?? null,
                        'sale_price' => $variantData['sale_price'] ?? null,
                        'active' => true,
                    ]);

                    continue;
                }

                $created = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'size_id' => $variantData['size_id'],
                    'color_id' => $variantData['color_id'],
                    'stock' => (int) $variantData['stock'],
                    'purchase_price' => $variantData['purchase_price'] ?? null,
                    'sale_price' => $variantData['sale_price'] ?? null,
                    'active' => true,
                ]);

                $incomingIds[] = $created->id;
            }

            $product->variants()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            $inventoryService->updateSoldOutAt($product->id);

            /** @var array<int, \Illuminate\Http\UploadedFile> $images */
            $images = $request->file('images', []);
            $storeProductImages->execute($product, $images);
        });

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
            Storage::disk('public')->delete($productImage->path);
            $productImage->delete();
        });

        return back()->with('success', 'Imagen eliminada.');
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

    private function generateSku(string $name): string
    {
        $base = preg_replace('/[^A-Z0-9]+/', '-', strtoupper(trim($name))) ?: 'MODEL';
        $base = trim($base, '-');
        $attempt = 1;

        do {
            $candidate = sprintf('%s-%04d', $base, $attempt);
            $exists = Product::query()->where('sku', $candidate)->exists();
            $attempt++;
        } while ($exists);

        return $candidate;
    }
}
