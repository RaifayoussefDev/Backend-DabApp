<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per admin broadcast ("mass-send") — aggregate counters only.
 * Individual per-user Notification rows link back here via batch_id so
 * read/unread counts can be computed with a single COUNT query instead
 * of listing every recipient.
 */
class NotificationBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_en',
        'title_ar',
        'body_en',
        'body_ar',
        'type',
        'channels',
        'filters',
        'scheduled_at',
        'total_targeted',
        'sent_count',
        'failed_count',
        'status',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'channels' => 'array',
        'filters' => 'array',
        'scheduled_at' => 'datetime',
        'total_targeted' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Cheap aggregate read count — one indexed COUNT query, never a per-user list. */
    public function readCount(): int
    {
        return $this->notifications()->where('is_read', true)->count();
    }

    /** Scheduled broadcasts whose time has arrived — picked up by notifications:dispatch-scheduled. */
    public function scopeDue($query)
    {
        return $query->where('status', 'scheduled')->where('scheduled_at', '<=', now());
    }
}
