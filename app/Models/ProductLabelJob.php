<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLabelJob extends Model
{
    protected $fillable = ['user_id', 'width_mm', 'height_mm', 'show_price', 'labels'];
    protected $casts = ['labels' => 'array', 'show_price' => 'boolean', 'width_mm' => 'integer', 'height_mm' => 'integer'];
}
