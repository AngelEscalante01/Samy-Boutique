<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id', 'device_id', 'user_id', 'token_snapshot', 'status',
        'attempt_count', 'last_attempt_at', 'next_attempt_at', 'fcm_message_id', 'last_error_code',
    ];

    protected $casts = [
        'token_snapshot' => 'encrypted',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(SystemNotification::class, 'notification_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(NotificationDevice::class, 'device_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationDeliveryAttempt::class, 'delivery_id');
    }
}
