<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * Nota: NO escondas purchase_price aquí de forma dinámica.
     * Para ocultarlo por permiso, usa un Resource/Transformer (ver ProductResource).
     */
    protected $fillable = [
        'sku',
        'name',
        'description',
        'category_id',
        'gender',
        'size_id',
        'color_id',
        'purchase_price',
        'sale_price',
        'status',
        'created_by',
        'sold_at',
    ];

    protected $casts = [
        // Mantiene precisión consistente al serializar.
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    /**
     * Scopes de inventario
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'disponible');
    }

    public function scopeSold(Builder $query): Builder
    {
        return $query->where('status', 'vendido');
    }

    public function scopeLayaway(Builder $query): Builder
    {
        return $query->where('status', 'apartado');
    }

    /**
     * Filtra por género (dama|caballero|unisex). Acepta string o array.
     */
    public function scopeByGender(Builder $query, string|array|null $gender): Builder
    {
        if (blank($gender)) {
            return $query;
        }

        return is_array($gender)
            ? $query->whereIn('gender', $gender)
            : $query->where('gender', $gender);
    }

    /**
     * Búsqueda simple (SKU, nombre y descripción).
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term) {
            $subQuery
                ->where('sku', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
