<?php
// app/Models/NotificationCampaign.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'title',
        'message',
        'image_url',
        'action_url',
        'icon',
        'color',
        'priority',
        'target_user_id',
        'target_group_id',
        'custom_filters',
        'schedule_type',
        'scheduled_at',
        'status',
        'total_recipients',
        'push_sent_count',
        'push_delivered_count',
        'push_failed_count',
        'read_count',
        'clicked_count',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'custom_filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relations
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetGroup(): BelongsTo
    {
        return $this->belongsTo(NotificationGroup::class, 'target_group_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'campaign_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
                     ->where('scheduled_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'sent');
    }

    // Methods
    public function getRecipients()
    {
        if ($this->type === 'individual') {
            return User::where('id', $this->target_user_id)->get();
        }

        if ($this->type === 'group') {
            return $this->targetGroup->getMembers();
        }

        if ($this->type === 'broadcast') {
            $query = User::where('is_active', 1);

            if ($this->custom_filters) {
                foreach ($this->custom_filters as $key => $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
            }

            return $query->get();
        }

        return collect();
    }

    public function start(): void
    {
        $this->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'sent',
            'completed_at' => now(),
        ]);
    }

    public function fail(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function incrementSentCount(): void
    {
        $this->increment('push_sent_count');
    }

    public function incrementDeliveredCount(): void
    {
        $this->increment('push_delivered_count');
    }

    public function incrementFailedCount(): void
    {
        $this->increment('push_failed_count');
    }

    public function incrementReadCount(): void
    {
        $this->increment('read_count');
    }

    public function incrementClickedCount(): void
    {
        $this->increment('clicked_count');
    }

    // Accessors
    public function getSuccessRateAttribute(): float
    {
        if ($this->push_sent_count == 0) {
            return 0;
        }

        return round(($this->push_delivered_count / $this->push_sent_count) * 100, 2);
    }

    public function getReadRateAttribute(): float
    {
        if ($this->push_delivered_count == 0) {
            return 0;
        }

        return round(($this->read_count / $this->push_delivered_count) * 100, 2);
    }

    public function getClickRateAttribute(): float
    {
        if ($this->read_count == 0) {
            return 0;
        }

        return round(($this->clicked_count / $this->read_count) * 100, 2);
    }
}
