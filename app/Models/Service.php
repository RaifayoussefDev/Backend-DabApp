<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'category_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'price_type',
        'currency',
        'duration_minutes',
        'is_available',
        'requires_booking',
        'max_capacity',
        'image'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'requires_booking' => 'boolean',
        'duration_minutes' => 'integer',
        'max_capacity' => 'integer'
    ];

    // Relations
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function schedules()
    {
        return $this->hasMany(ServiceSchedule::class, 'service_id');
    }

    public function bookings()
    {
        return $this->hasMany(ServiceBooking::class, 'service_id');
    }

    public function reviews()
    {
        return $this->hasMany(ServiceReview::class, 'service_id');
    }

    public function requiredDocuments()
    {
        return $this->hasMany(ServiceRequiredDocument::class, 'service_id')->orderBy('order_position');
    }

    public function favorites()
    {
        return $this->hasMany(ServiceFavorite::class, 'service_id');
    }

    // Helper Methods
    public function isFavoritedBy($userId)
    {
        return $this->favorites()->where('user_id', $userId)->exists();
    }

    public function getAverageRating()
    {
        return $this->reviews()->where('is_approved', true)->avg('rating') ?? 0;
    }

    public function getReviewsCount()
    {
        return $this->reviews()->where('is_approved', true)->count();
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeWithActiveProvider($query)
    {
        return $query->whereHas('provider', function($q) {
            $q->where('is_active', true);
        });
    }

    // Accessors
    public function getLocalizedNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name;
    }

    public function getLocalizedDescriptionAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : asset('images/default-service.png');
    }

    public function getFormattedPriceAttribute()
    {
        $priceText = number_format($this->price, 2) . ' ' . $this->currency;
        
        switch ($this->price_type) {
            case 'per_hour':
                return $priceText . ' / ' . __('hour');
            case 'per_km':
                return $priceText . ' / ' . __('km');
            case 'custom':
                return __('Custom pricing');
            default:
                return $priceText;
        }
    }
}