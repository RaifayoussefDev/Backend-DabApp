<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'waypoint_id',
        'image_url',
        'caption',
        'order_position',
    ];

    /**
     * Get the route that owns this image.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * Get the waypoint that owns this image.
     */
    public function waypoint(): BelongsTo
    {
        return $this->belongsTo(RouteWaypoint::class, 'waypoint_id');
    }
}
