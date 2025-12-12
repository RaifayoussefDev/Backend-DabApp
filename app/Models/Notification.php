<?php
// app/Models/Notification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'related_entity_type',
        'related_entity_id',
        'action_url',
        'image_url',
        'icon',
        'priority',
        'color',
        'sound',
        'is_read',
        'read_at',
        'is_deleted',
        'deleted_at',
        'push_sent',
        'push_sent_at',
        'push_delivered',
        'push_delivered_at',
        'sent_by_admin',
        'is_custom',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_deleted' => 'boolean',
        'push_sent' => 'boolean',
        'push_delivered' => 'boolean',
        'is_custom' => 'boolean',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
        'push_sent_at' => 'datetime',
        'push_delivered_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_admin');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(NotificationAction::class)->orderBy('order_position');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false)->where('is_deleted', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopePushSent($query)
    {
        return $query->where('push_sent', true);
    }

    public function scopePushNotSent($query)
    {
        return $query->where('push_sent', false);
    }

    // Methods
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function markAsDeleted(): void
    {
        $this->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);
    }

    public function markPushSent(): void
    {
        $this->update([
            'push_sent' => true,
            'push_sent_at' => now(),
        ]);
    }

    public function markPushDelivered(): void
    {
        $this->update([
            'push_delivered' => true,
            'push_delivered_at' => now(),
        ]);
    }

    // Accessors
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getIsHighPriorityAttribute(): bool
    {
        return in_array($this->priority, ['high', 'urgent']);
    }
}
