<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAnnouncement extends Model
{
    protected $fillable = [
        'sender_user_id', 'title', 'message', 'category', 'priority', 'audience',
        'recipient_count', 'idempotency_key',
    ];

    protected $casts = ['audience' => 'array'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
