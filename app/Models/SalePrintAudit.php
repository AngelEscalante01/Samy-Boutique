<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePrintAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'user_id',
        'ticket_type',
        'print_attempted',
        'print_success',
        'error_message',
        'connection_method',
        'printed_at',
        'meta',
    ];

    protected $casts = [
        'print_attempted' => 'boolean',
        'print_success' => 'boolean',
        'printed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
