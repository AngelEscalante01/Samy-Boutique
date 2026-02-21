<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'active',
        'purchases_count',
    ];

    protected $casts = [
        'active' => 'boolean',
        'purchases_count' => 'integer',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function layaways(): HasMany
    {
        return $this->hasMany(Layaway::class);
    }
}
