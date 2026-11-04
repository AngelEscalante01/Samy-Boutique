<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImage extends Model
{
    use HasFactory;

    // Incremental API synchronization is based on the owner's updated_at.
    protected $touches = ['product'];

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'variant_ids',
        'path',
        'sort',
        'deleted_at',
        'deleted_by',
        'disk',
        'generation',
        'sha256',
        'bytes',
        'content_type',
        'source_disk',
        'source_path',
        'storage_verified_at',
    ];

    protected $casts = [
        'variant_ids' => 'array',
        'sort' => 'integer',
        'deleted_at' => 'datetime',
        'deleted_by' => 'integer',
        'bytes' => 'integer',
        'storage_verified_at' => 'datetime',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        $path = self::normalizePath((string) $this->path);

        if ($path === '' || $this->deleted_at !== null) {
            return '';
        }

        $disk = $this->disk ?: 'public';
        if (! in_array($disk, ['public', 'r2_public'], true)) {
            return '';
        }

        return $disk === 'public' ? asset('storage/'.$path)
            : \Illuminate\Support\Facades\Storage::disk($disk)->url($path);
    }

    public function setPathAttribute(?string $value): void
    {
        $this->attributes['path'] = self::normalizePath($value);
    }

    public static function normalizePath(?string $path): string
    {
        $normalized = trim((string) $path);

        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }

        if (! str_contains($normalized, '/')) {
            $normalized = 'products/'.$normalized;
        }

        return $normalized;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function assignedVariantIds(): array
    {
        return $this->variant_ids !== null
            ? array_values(array_unique(array_map('intval', $this->variant_ids)))
            : ($this->product_variant_id ? [(int) $this->product_variant_id] : []);
    }

    public function visibleForVariants(array $availableIds): bool
    {
        $ids = $this->assignedVariantIds();

        return $ids === [] || array_intersect($ids, $availableIds) !== [];
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(ProductImageDerivative::class);
    }
}
