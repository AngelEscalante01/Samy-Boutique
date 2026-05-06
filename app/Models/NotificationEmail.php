<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEmail extends Model
{
    protected $fillable = ['email', 'label', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
