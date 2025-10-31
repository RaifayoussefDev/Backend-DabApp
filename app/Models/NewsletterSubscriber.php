<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'user_id',
        'is_active',
        'subscribed_at',
        'unsubscribed_at',
        'verification_token',
        'verified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the user associated with this subscriber.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the preferences for this subscriber.
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(NewsletterPreference::class, 'subscriber_id');
    }

    /**
     * Get the newsletter sends for this subscriber.
     */
    public function sends(): HasMany
    {
        return $this->hasMany(NewsletterSend::class, 'subscriber_id');
    }

    /**
     * Scope to get only active subscribers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only verified subscribers.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Check if subscriber is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }
}
