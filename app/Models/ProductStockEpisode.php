<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockEpisode extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'source_sale_id', 'generation', 'started_at', 'cancelled_at', 'cancel_reason'];

    protected $casts = ['started_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class, 'source_sale_id'); }
}
