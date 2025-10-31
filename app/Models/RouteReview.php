<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'user_id',
        'rating',
        'comment',
        'completed_date',
        'weather_condition',
        'traffic_level',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'completed_date' => 'date',
    ];

    /**
     * Get the route that owns this review.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * Get the user who wrote this review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only approved reviews.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }
}
