<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'service_id',
        'provider_id',
        'user_id',
        'rating',
        'comment',
        'comment_ar',
        'is_approved',
        'provider_response',
        'provider_response_ar',
        'provider_response_at'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
        'provider_response_at' => 'datetime'
    ];

    // Relations
    public function booking()
    {
        return $this->belongsTo(ServiceBooking::class, 'booking_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeForService($query, $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeWithResponse($query)
    {
        return $query->whereNotNull('provider_response');
    }

    // Accessors
    public function getLocalizedCommentAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->comment_ar : $this->comment;
    }

    public function getLocalizedProviderResponseAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->provider_response_ar : $this->provider_response;
    }

    public function getRatingStarsAttribute()
    {
        return str_repeat('⭐', $this->rating);
    }

    public function hasProviderResponse()
    {
        return !empty($this->provider_response);
    }
}