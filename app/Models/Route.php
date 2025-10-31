<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'featured_image',
        'created_by',
        'category_id',
        'difficulty',
        'total_distance',
        'estimated_duration',
        'best_season',
        'road_condition',
        'is_verified',
        'is_featured',
        'views_count',
        'likes_count',
        'completed_count',
        'rating_average',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'total_distance' => 'decimal:2',
        'rating_average' => 'decimal:2',
    ];

    /**
     * Get the user who created this route.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the category of this route.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RouteCategory::class, 'category_id');
    }

    /**
     * Get the waypoints for this route.
     */
    public function waypoints(): HasMany
    {
        return $this->hasMany(RouteWaypoint::class, 'route_id')->orderBy('order_position');
    }

    /**
     * Get the images for this route.
     */
    public function images(): HasMany
    {
        return $this->hasMany(RouteImage::class, 'route_id');
    }

    /**
     * Get the reviews for this route.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(RouteReview::class, 'route_id');
    }

    /**
     * Get the approved reviews for this route.
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(RouteReview::class, 'route_id')->where('is_approved', true);
    }

    /**
     * Get the users who liked this route.
     */
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'route_likes', 'route_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the users who favorited this route.
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'route_favorites', 'route_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the completions for this route.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(RouteCompletion::class, 'route_id');
    }

    /**
     * Get the warnings for this route.
     */
    public function warnings(): HasMany
    {
        return $this->hasMany(RouteWarning::class, 'route_id');
    }

    /**
     * Get the active warnings for this route.
     */
    public function activeWarnings(): HasMany
    {
        return $this->hasMany(RouteWarning::class, 'route_id')->where('is_active', true);
    }

    /**
     * Get the tags for this route.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RouteTag::class, 'route_tag_relations', 'route_id', 'tag_id');
    }

    /**
     * Scope to get only verified routes.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get only featured routes.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter by difficulty.
     */
    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
