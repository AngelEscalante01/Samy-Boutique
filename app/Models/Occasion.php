<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Occasion extends Model
{
    protected $fillable = ['name', 'normalized_name', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public static function normalizeName(string $name): string
    {
        return Str::of($name)->squish()->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();
    }
}
