<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteWarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'waypoint_id',
        'warning_type',
        'description',
        'reported_by',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the route this warning belongs to.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * Get the waypoint this warning is about.
     */
    public function waypoint(): BelongsTo
    {
        return $this->belongsTo(RouteWaypoint::class, 'waypoint_id');
    }

    /**
     * Get the user who reported this warning.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Scope to get only active warnings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get current warnings (within date range).
     */
    public function scopeCurrent($query)
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->where(function ($subQ) use ($today) {
                    $subQ->whereNull('start_date')
                        ->orWhere('start_date', '<=', $today);
                })
                ->where(function ($subQ) use ($today) {
                    $subQ->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                });
            });
    }
}
