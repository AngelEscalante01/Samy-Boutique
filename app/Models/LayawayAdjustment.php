<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayawayAdjustment extends Model
{
    protected $fillable = ['layaway_id', 'created_by', 'version_before', 'version_after', 'before_snapshot', 'after_snapshot', 'reason'];

    protected $casts = ['before_snapshot' => 'array', 'after_snapshot' => 'array'];
}
