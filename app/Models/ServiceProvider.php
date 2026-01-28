<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProvider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_name_ar',
        'description',
        'description_ar',
        'logo',
        'cover_image',
        'phone',
        'email',
        'address',
        'address_ar',
        'city_id',
        'country_id',
        'latitude',
        'longitude',
        'is_verified',
        'is_active',
        'rating_average',
        'reviews_count',
        'services_count',
        'completed_orders'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'rating_average' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'reviews_count' => 'integer',
        'services_count' => 'integer',
        'completed_orders' => 'integer'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'provider_id');
    }

    public function bookings()
    {
        return $this->hasMany(ServiceBooking::class, 'provider_id');
    }

    public function reviews()
    {
        return $this->hasMany(ServiceReview::class, 'provider_id');
    }

    public function images()
    {
        return $this->hasMany(ServiceProviderImage::class, 'provider_id')->orderBy('order_position');
    }

    public function workingHours()
    {
        return $this->hasMany(ProviderWorkingHour::class, 'provider_id');
    }

    public function transportRoutes()
    {
        return $this->hasMany(TransportRoute::class, 'provider_id');
    }

    public function instructors()
    {
        return $this->hasMany(RidingInstructor::class, 'provider_id');
    }

    public function workshopNotes()
    {
        return $this->hasOne(WorkshopNote::class, 'provider_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeNearby($query, $lat, $lng, $radius = 10)
    {
        return $query->selectRaw("*, 
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
            cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
            sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
            ->having('distance', '<', $radius)
            ->orderBy('distance');
    }

    // Accessors
    public function getLocalizedBusinessNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->business_name_ar : $this->business_name;
    }

    public function getLocalizedDescriptionAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }

    public function getLocalizedAddressAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->address_ar : $this->address;
    }

    public function getLogoUrlAttribute()
    {
        return $this->logo ? asset('storage/' . $this->logo) : asset('images/default-provider-logo.png');
    }

    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : asset('images/default-cover.jpg');
    }
}