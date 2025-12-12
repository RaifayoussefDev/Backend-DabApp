<?php
// app/Models/NotificationToken.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_type',
        'fcm_token',
        'device_name',
        'device_id',
        'device_model',
        'os_version',
        'app_version',
        'is_active',
        'last_used_at',
        'failed_attempts',
        'last_failed_at',
        'subscribed_topics',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'subscribed_topics' => 'array',
        'last_used_at' => 'datetime',
        'last_failed_at' => 'datetime',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDeviceType($query, string $type)
    {
        return $query->where('device_type', $type);
    }

    public function scopeIos($query)
    {
        return $query->where('device_type', 'ios');
    }

    public function scopeAndroid($query)
    {
        return $query->where('device_type', 'android');
    }

    public function scopeWeb($query)
    {
        return $query->where('device_type', 'web');
    }

    // Methods
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_attempts');
        $this->update(['last_failed_at' => now()]);

        // Désactiver si trop d'échecs
        if ($this->failed_attempts >= 5) {
            $this->deactivate();
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_attempts' => 0,
            'last_failed_at' => null,
        ]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function subscribeToTopic(string $topic): void
    {
        $topics = $this->subscribed_topics ?? [];
        if (!in_array($topic, $topics)) {
            $topics[] = $topic;
            $this->update(['subscribed_topics' => $topics]);
        }
    }

    public function unsubscribeFromTopic(string $topic): void
    {
        $topics = $this->subscribed_topics ?? [];
        $topics = array_filter($topics, fn($t) => $t !== $topic);
        $this->update(['subscribed_topics' => array_values($topics)]);
    }
}
