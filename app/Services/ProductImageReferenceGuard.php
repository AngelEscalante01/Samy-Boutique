<?php

namespace App\Services;

use App\Models\CatalogSlide;
use App\Models\Customer;
use App\Models\ProductImage;
use App\Models\ProductImageDerivative;

class ProductImageReferenceGuard
{
    public function reasonForImage(ProductImage $image): ?string
    {
        $disk = $image->disk ?: 'public';
        $reason = $this->reason($image, $disk, $image->path);
        foreach ($image->derivatives as $derivative) {
            $reason ??= $this->reason($image, $derivative->disk, $derivative->path);
        }

        return $reason;
    }

    /** Return the first active reference outside this product image, if any. */
    public function reason(ProductImage $owner, string $disk, string $path): ?string
    {
        return $this->reasonForObject($disk, $path, $owner);
    }

    /** Check every live owner after a slide or customer has detached an object. */
    public function reasonForObject(string $disk, string $path, ?ProductImage $excludedImage = null): ?string
    {
        if (ProductImage::query()->when($excludedImage, fn ($query) => $query->whereKeyNot($excludedImage->id))
            ->whereNull('deleted_at')->where('disk', $disk)->where('path', $path)->exists()) {
            return 'shared_product_image';
        }
        if (ProductImageDerivative::query()->when($excludedImage, fn ($query) => $query->where('product_image_id', '!=', $excludedImage->id))
            ->where('disk', $disk)->where('path', $path)
            ->whereHas('image', fn ($query) => $query->whereNull('deleted_at'))->exists()) {
            return 'shared_product_derivative';
        }
        if (CatalogSlide::query()->where(function ($query) use ($disk, $path) {
            $query->where(fn ($part) => $part->where('desktop_disk', $disk)->where('desktop_path', $path))
                ->orWhere(fn ($part) => $part->where('mobile_disk', $disk)->where('mobile_path', $path));
        })->exists()) {
            return 'catalog_slide_reference';
        }
        if (Customer::query()->where('photo_disk', $disk)->where('photo_path', $path)->exists()) {
            return 'customer_photo_reference';
        }
        if (config('media.brand_logo_disk') === $disk && config('media.brand_logo_path') === $path) {
            return 'brand_logo_reference';
        }

        return null;
    }
}
