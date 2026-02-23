<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'sort',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'sort' => 'integer',
        'deleted_at' => 'datetime',
        'deleted_by' => 'integer',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        $path = self::normalizePath((string) $this->path);

        if ($path === '') {
            return '';
        }

        return asset('storage/'.$path);
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
}
