<?php
// app/Models/NotificationAction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'action_type',
        'action_label',
        'action_url',
        'icon',
        'color',
        'order_position',
    ];

    // Relations
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position');
    }
}
