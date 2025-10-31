<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteWaypoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'order_position',
        'name',
        'description',
        'latitude',
        'longitude',
        'waypoint_type',
        'poi_id',
        'stop_duration',
        'distance_from_previous',
        'elevation',
        'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'distance_from_previous' => 'decimal:2',
    ];

    /**
     * Get the route that owns this waypoint.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * Get the POI linked to this waypoint.
     */
    public function poi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Get the images for this waypoint.
     */
    public function images(): HasMany
    {
        return $this->hasMany(RouteImage::class, 'waypoint_id');
    }

    /**
     * Get the warnings for this waypoint.
     */
    public function warnings(): HasMany
    {
        return $this->hasMany(RouteWarning::class, 'waypoint_id');
    }
}
