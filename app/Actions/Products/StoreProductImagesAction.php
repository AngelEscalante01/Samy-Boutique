<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreProductImagesAction
{
    /**
     * @param  array<int, UploadedFile>  $images
     */
    public function execute(Product $product, array $images): void
    {
        if (empty($images)) {
            return;
        }

        $disk = Storage::disk('public');
        $directory = "products/{$product->id}";

        $currentMaxSort = (int) ProductImage::where('product_id', $product->id)->max('sort');
        $sort = $currentMaxSort;

        foreach ($images as $image) {
            $sort++;

            $extension = $image->getClientOriginalExtension();
            $filename = Str::ulid()->toBase32().'.'.strtolower($extension);

            // Guardamos una ruta relativa (ej: products/1/ABC123.webp)
            $path = $image->storeAs($directory, $filename, 'public');

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'sort' => $sort,
            ]);
        }
    }
}
