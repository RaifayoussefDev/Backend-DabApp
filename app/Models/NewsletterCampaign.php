<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subject',
        'content',
        'template_id',
        'status',
        'scheduled_at',
        'sent_at',
        'created_by',
        'recipients_count',
        'opened_count',
        'clicked_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the user who created this campaign.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the sends for this campaign.
     */
    public function sends(): HasMany
    {
        return $this->hasMany(NewsletterSend::class, 'campaign_id');
    }

    /**
     * Scope to get draft campaigns.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get scheduled campaigns.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope to get sent campaigns.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Calculate open rate.
     */
    public function getOpenRateAttribute(): float
    {
        if ($this->recipients_count === 0) {
            return 0;
        }
        return ($this->opened_count / $this->recipients_count) * 100;
    }

    /**
     * Calculate click rate.
     */
    public function getClickRateAttribute(): float
    {
        if ($this->recipients_count === 0) {
            return 0;
        }
        return ($this->clicked_count / $this->recipients_count) * 100;
    }
}
