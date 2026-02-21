<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCut extends Model
{
    use HasFactory;

    protected $fillable = [
        'cut_date',
        'created_by',
        'totals_json',
    ];

    protected $casts = [
        'cut_date' => 'date',
        'totals_json' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
