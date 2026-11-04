<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDispatch extends Model
{
    protected $fillable = ['notification_id', 'status', 'processed_at'];

    protected $casts = ['processed_at' => 'datetime'];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(SystemNotification::class);
    }
}
