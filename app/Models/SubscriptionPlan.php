<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     title="SubscriptionPlan",
 *     description="Subscription Plan model",
 *     @OA\Xml(name="SubscriptionPlan")
 * )
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * @OA\Property(property="id", type="integer", example=1)
     * @OA\Property(property="name", type="string", example="Pro Plan")
     * @OA\Property(property="slug", type="string", example="pro-plan")
     * @OA\Property(property="price_monthly", type="number", format="float", example=49.00)
     * @OA\Property(property="price_yearly", type="number", format="float", example=490.00)
     * @OA\Property(property="features", type="array", @OA\Items(type="string"))
     * @OA\Property(property="max_services", type="integer", nullable=true)
     * @OA\Property(property="max_bookings_per_month", type="integer", nullable=true)
     * @OA\Property(property="is_active", type="boolean", example=true)
     * @OA\Property(property="is_featured", type="boolean", example=true)
     */
    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
        'price_monthly',
        'price_yearly',
        'features',
        'max_services',
        'max_bookings_per_month',
        'priority_support',
        'analytics_access',
        'is_featured',
        'is_active',
        'order_position',
    ];

    protected $casts = [
        'features' => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'priority_support' => 'boolean',
        'analytics_access' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all subscriptions for this plan
     */
    public function subscriptions()
    {
        return $this->hasMany(ServiceSubscription::class, 'plan_id');
    }

    /**
     * Get active subscriptions count
     */
    public function activeSubscriptionsCount()
    {
        return $this->subscriptions()->where('status', 'active')->count();
    }

    /**
     * Scope: Active plans only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Featured plans
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Order by position
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position', 'asc');
    }

    /**
     * Get yearly discount percentage
     */
    public function getYearlyDiscountPercentageAttribute()
    {
        $monthlyYearly = $this->price_monthly * 12;
        if ($monthlyYearly == 0)
            return 0;

        return round((($monthlyYearly - $this->price_yearly) / $monthlyYearly) * 100);
    }

    /**
     * Check if plan has unlimited bookings
     */
    public function hasUnlimitedBookings()
    {
        return is_null($this->max_bookings_per_month);
    }

    /**
     * Check if plan has unlimited services
     */
    public function hasUnlimitedServices()
    {
        return is_null($this->max_services);
    }
}