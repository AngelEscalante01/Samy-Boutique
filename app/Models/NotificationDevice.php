<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDevice extends Model
{
    protected $fillable = [
        'user_id', 'installation_id', 'platform', 'name', 'token', 'token_hash',
        'permission_status', 'active', 'last_seen_at', 'token_updated_at',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'active' => 'boolean',
        'last_seen_at' => 'datetime',
        'token_updated_at' => 'datetime',
    ];

    protected $hidden = ['token', 'token_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
