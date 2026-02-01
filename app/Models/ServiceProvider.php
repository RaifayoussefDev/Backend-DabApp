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

    /**
     * Get the active subscription for this provider
     */
    public function activeSubscription()
    {
        return $this->hasOne(ServiceSubscription::class, 'provider_id')
            ->where('status', 'active')
            ->latest();
    }

    /**
     * Get all subscriptions for this provider
     */
    public function subscriptions()
    {
        return $this->hasMany(ServiceSubscription::class, 'provider_id');
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

    /**
     * Scope: Providers with active subscription
     */
    public function scopeWithActiveSubscription($query)
    {
        return $query->whereHas('activeSubscription');
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

    // ==================== SUBSCRIPTION HELPER METHODS ====================

    /**
     * Check if provider has active subscription
     */
    public function hasActiveSubscription()
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * Get current subscription plan
     */
    public function currentPlan()
    {
        return $this->activeSubscription?->plan;
    }

    /**
     * Get subscription status
     */
    public function getSubscriptionStatusAttribute()
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return 'no_subscription';
        }

        return $subscription->status;
    }

    /**
     * Check if provider can add more services
     */
    public function canAddService()
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return false;
        }

        $plan = $subscription->plan;
        
        // Unlimited services
        if ($plan->hasUnlimitedServices()) {
            return true;
        }

        // Check against limit
        return $this->services()->count() < $plan->max_services;
    }

    /**
     * Get remaining services quota
     */
    public function getRemainingServicesQuotaAttribute()
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return 0;
        }

        $plan = $subscription->plan;
        
        // Unlimited services
        if ($plan->hasUnlimitedServices()) {
            return null; // null means unlimited
        }

        $currentCount = $this->services()->count();
        return max(0, $plan->max_services - $currentCount);
    }

    /**
     * Check if provider can accept more bookings this month
     */
    public function canAcceptBooking()
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return false;
        }

        $plan = $subscription->plan;
        
        // Unlimited bookings
        if ($plan->hasUnlimitedBookings()) {
            return true;
        }

        // Count bookings this month
        $bookingsThisMonth = $this->bookings()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return $bookingsThisMonth < $plan->max_bookings_per_month;
    }

    /**
     * Get remaining bookings quota for this month
     */
    public function getRemainingBookingsQuotaAttribute()
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return 0;
        }

        $plan = $subscription->plan;
        
        // Unlimited bookings
        if ($plan->hasUnlimitedBookings()) {
            return null; // null means unlimited
        }

        $bookingsThisMonth = $this->bookings()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return max(0, $plan->max_bookings_per_month - $bookingsThisMonth);
    }

    /**
     * Check if provider has priority support
     */
    public function hasPrioritySupport()
    {
        $plan = $this->currentPlan();
        return $plan ? $plan->priority_support : false;
    }

    /**
     * Check if provider has analytics access
     */
    public function hasAnalyticsAccess()
    {
        $plan = $this->currentPlan();
        return $plan ? $plan->analytics_access : false;
    }

    /**
     * Get subscription details for display
     */
    public function getSubscriptionDetailsAttribute()
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return [
                'has_subscription' => false,
                'status' => 'no_subscription',
                'plan_name' => null,
                'next_billing_date' => null,
            ];
        }

        return [
            'has_subscription' => true,
            'status' => $subscription->status,
            'plan_name' => $subscription->plan->name,
            'billing_cycle' => $subscription->billing_cycle,
            'current_price' => $subscription->current_price,
            'next_billing_date' => $subscription->next_billing_date,
            'days_until_renewal' => $subscription->days_until_renewal,
            'auto_renew' => $subscription->auto_renew,
            'services_quota' => $this->remaining_services_quota,
            'bookings_quota' => $this->remaining_bookings_quota,
            'priority_support' => $this->hasPrioritySupport(),
            'analytics_access' => $this->hasAnalyticsAccess(),
        ];
    }
}