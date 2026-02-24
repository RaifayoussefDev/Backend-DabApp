<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PointOfInterest extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'points_of_interest';
    protected $fillable = [
        'name',
        'description',
        'type_id',
        'owner_id',
        'latitude',
        'longitude',
        'address',
        'city_id',
        'country_id',
        'phone',
        'email',
        'website',
        'opening_hours',
        'is_verified',
        'is_active',
        'rating_average',
        'reviews_count',
        'views_count',
        'google_place_id',
        'google_rating',
        'google_reviews_count',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'rating_average' => 'decimal:2',
        'google_rating' => 'decimal:1',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'opening_hours' => 'array',
    ];

    /**
     * Get the type of this POI.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PoiType::class, 'type_id');
    }

    /**
     * Get the seller/owner of this POI.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }


    /**
     * Get the city of this POI.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the country of this POI.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the images for this POI.
     */
    public function images(): HasMany
    {
        return $this->hasMany(PoiImage::class, 'poi_id');
    }

    /**
     * Get the main image for this POI.
     */
    public function mainImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PoiImage::class, 'poi_id')->where('is_main', true);
    }

    /**
     * Get the reviews for this POI.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PoiReview::class, 'poi_id');
    }

    /**
     * Get the approved reviews for this POI.
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(PoiReview::class, 'poi_id')->where('is_approved', true);
    }

    /**
     * Get the services offered by this POI.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(PoiService::class, 'poi_service_relations', 'poi_id', 'service_id')
            ->withPivot('price');
    }

    /**
     * Get the brands sold/repaired by this POI.
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(MotorcycleBrand::class, 'poi_brands', 'poi_id', 'brand_id');
    }

    /**
     * Get the users who favorited this POI.
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'poi_favorites', 'poi_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the reports for this POI.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(PoiReport::class, 'poi_id');
    }

    /**
     * Get the route waypoints linked to this POI.
     */
    public function routeWaypoints(): HasMany
    {
        return $this->hasMany(RouteWaypoint::class, 'poi_id');
    }

    /**
     * Get the tags associated with this POI.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PoiTag::class, 'poi_tag_relations', 'poi_id', 'tag_id')
            ->withTimestamps();
    }

    /**
     * Scope to get only verified POIs.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get only active POIs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, $typeId)
    {
        return $query->where('type_id', $typeId);
    }

    /**
     * Scope to search by distance from a location.
     */
    public function scopeNearby($query, $latitude, $longitude, $radiusKm = 10)
    {
        $haversine = sprintf(
            '(6371 * acos(cos(radians(%s)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%s)) + sin(radians(%s)) * sin(radians(latitude))))',
            $latitude,
            $longitude,
            $latitude
        );

        return $query
            ->selectRaw("{$haversine} AS distance")
            ->whereRaw("{$haversine} < ?", [$radiusKm])
            ->orderBy('distance');
    }
}
