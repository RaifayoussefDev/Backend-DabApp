<?php
// app/Models/NotificationLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'campaign_id',
        'user_id',
        'channel',
        'status',
        'fcm_token',
        'fcm_message_id',
        'fcm_response',
        'error_message',
        'error_code',
        'retry_count',
        'device_type',
        'device_id',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'clicked_at',
        'failed_at',
    ];

    protected $casts = [
        'fcm_response' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'clicked_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // Relations
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    // Methods
    public function markAsSent(string $fcmMessageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'fcm_message_id' => $fcmMessageId,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsClicked(): void
    {
        $this->update([
            'status' => 'clicked',
            'clicked_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage, string $errorCode = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
        ]);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }
}
