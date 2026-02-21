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
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $product->load(['category', 'size', 'color', 'images']);
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

    public function store(StoreProductRequest $request, StoreProductImagesAction $storeProductImages): RedirectResponse
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($request, $validated, $storeProductImages) {
            $product = Product::create([
                'sku' => $validated['sku'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $validated['category_id'],
                'gender' => $validated['gender'],
                'size_id' => $validated['size_id'],
                'color_id' => $validated['color_id'],
                'purchase_price' => $validated['purchase_price'],
                'sale_price' => $validated['sale_price'],
                'status' => $validated['status'],
                'created_by' => $request->user()->id,
                'sold_at' => $validated['sold_at'] ?? null,
            ]);

            /** @var array<int, \Illuminate\Http\UploadedFile> $images */
            $images = $request->file('images', []);
            $storeProductImages->execute($product, $images);

            return $product;
        });

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Producto creado.');
    }

    public function update(UpdateProductRequest $request, Product $product, StoreProductImagesAction $storeProductImages): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $product, $validated, $storeProductImages) {
            $product->update([
                'sku' => $validated['sku'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $validated['category_id'],
                'gender' => $validated['gender'],
                'size_id' => $validated['size_id'],
                'color_id' => $validated['color_id'],
                'purchase_price' => $validated['purchase_price'],
                'sale_price' => $validated['sale_price'],
                'status' => $validated['status'],
                'sold_at' => $validated['sold_at'] ?? null,
            ]);

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
}
