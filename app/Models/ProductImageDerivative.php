<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImageDerivative extends Model
{
    protected $fillable = ['product_image_id', 'kind', 'disk', 'path', 'generation', 'sha256', 'bytes', 'content_type'];

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function getUrlAttribute(): string
    {
        if (! in_array($this->disk, ['public', 'r2_public'], true)) return '';

        return $this->disk === 'public' ? asset('storage/'.$this->path) : Storage::disk($this->disk)->url($this->path);
    }
}
