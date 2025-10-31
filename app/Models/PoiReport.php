<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoiReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'poi_id',
        'user_id',
        'reason',
        'description',
        'status',
    ];

    /**
     * Get the POI that this report is about.
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Get the user who filed this report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get pending reports.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get resolved reports.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }
}
