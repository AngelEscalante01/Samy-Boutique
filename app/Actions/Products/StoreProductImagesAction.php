<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\ProductImageStorage;
use App\Services\ProductImageDerivatives;
use Illuminate\Support\Facades\Validator;

class StoreProductImagesAction
{
    /**
     * @param  array<int, UploadedFile>  $images
     */
    public function execute(Product $product, array $images, array $variantIds = []): void
    {
        if (empty($images)) {
            return;
        }

        $diskName = config('media.product_disk', 'public');
        if (! in_array($diskName, ['public', 'r2_public'], true)) {
            throw new \RuntimeException('Invalid product image disk.');
        }
        $disk = Storage::disk($diskName);
        $directory = "samy/products/{$product->id}";

        $currentMaxSort = (int) ProductImage::where('product_id', $product->id)->max('sort');
        $sort = $currentMaxSort;

        $written = [];

        try {
        foreach ($images as $index => $image) {
            $sort++;

            Validator::make(['image' => $image], ['image' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240']])->validate();
            $assignedIds = array_values(array_unique(array_map('intval', array_filter((array) ($variantIds[$index] ?? [])))));
            if ($assignedIds !== [] && $product->variants()->whereIn('id', $assignedIds)->count() !== count($assignedIds)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['image_variant_ids' => 'La variante de la foto no pertenece al producto.']);
            }
            $generation = (string) Str::uuid();
            $filename = $generation.'.'.$image->extension();

            // Guardamos una ruta relativa (ej: products/1/ABC123.webp)
            $path = $image->storeAs($directory, $filename, $diskName);
            if (! $path) {
                throw new \RuntimeException('Image upload failed.');
            }
            $written[] = $path;
            $sha = hash_file('sha256', $image->getRealPath());
            app(ProductImageStorage::class)->verify($diskName, $path, $sha, $image->getSize());

            $record = ProductImage::create([
                'product_id' => $product->id,
                'product_variant_id' => $assignedIds[0] ?? null,
                'variant_ids' => $assignedIds,
                'path' => $path,
                'sort' => $sort,
                'disk' => $diskName,
                'generation' => $generation,
                'sha256' => $sha,
                'bytes' => $image->getSize(),
                'content_type' => $image->getMimeType(),
                'storage_verified_at' => now(),
            ]);
            $written = array_merge($written, app(ProductImageDerivatives::class)->create($record, $image));
        }
        } catch (\Throwable $error) {
            foreach ($written as $path) {
                try { $disk->delete($path); } catch (\Throwable $cleanupError) { report($cleanupError); }
            }
            throw $error;
        }
    }
}
