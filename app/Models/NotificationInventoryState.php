<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationInventoryState extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_variant_id', 'state', 'transition_count', 'updated_at'];
}
