<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayawayRefund extends Model
{
    protected $fillable = ['layaway_id', 'created_by', 'operation_key', 'amount', 'method', 'reference', 'reason', 'status', 'refunded_at'];

    protected $casts = ['amount' => 'decimal:2', 'refunded_at' => 'datetime'];
}
