<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'type',
        'status',
        'response_json',
    ];

    protected $casts = [
        'response_json' => 'array',
    ];
}
