<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ServicePromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'description_ar',
        'discount_type',
        'discount_value',
        'min_booking_price',
        'max_discount',
        'service_category_id',
        'usage_limit',
        'per_user_limit',
        'valid_from',
        'valid_until',
        'is_active'
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_booking_price' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean'
    ];

    // Relations
    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('valid_from')
                          ->orWhere('valid_from', '<=', now());
                    })
                    ->where(function($q) {
                        $q->whereNull('valid_until')
                          ->orWhere('valid_until', '>=', now());
                    });
    }

    public function scopeForCategory($query, $categoryId)
    {
        return $query->where(function($q) use ($categoryId) {
            $q->whereNull('service_category_id')
              ->orWhere('service_category_id', $categoryId);
        });
    }

    // Helper Methods
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $this->valid_from->gt($now)) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->lt($now)) {
            return false;
        }

        return true;
    }

    public function hasReachedUsageLimit()
    {
        if (!$this->usage_limit) {
            return false;
        }

        // TODO: Count actual usages from bookings
        return false;
    }

    public function canBeUsedBy($userId)
    {
        if (!$this->isValid()) {
            return false;
        }

        // TODO: Check per_user_limit
        return true;
    }

    public function calculateDiscount($bookingPrice)
    {
        if ($this->min_booking_price && $bookingPrice < $this->min_booking_price) {
            return 0;
        }

        if ($this->discount_type === 'percentage') {
            $discount = ($bookingPrice * $this->discount_value) / 100;
            
            if ($this->max_discount && $discount > $this->max_discount) {
                return $this->max_discount;
            }
            
            return $discount;
        }

        // Fixed discount
        return min($this->discount_value, $bookingPrice);
    }

    // Accessors
    public function getLocalizedDescriptionAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }

    public function getDiscountLabelAttribute()
    {
        if ($this->discount_type === 'percentage') {
            return $this->discount_value . '%';
        }
        return $this->discount_value . ' SAR';
    }

    public function getStatusLabelAttribute()
    {
        if (!$this->is_active) {
            return app()->getLocale() === 'ar' ? 'غير نشط' : 'Inactive';
        }

        $now = now();

        if ($this->valid_from && $this->valid_from->gt($now)) {
            return app()->getLocale() === 'ar' ? 'قريباً' : 'Upcoming';
        }

        if ($this->valid_until && $this->valid_until->lt($now)) {
            return app()->getLocale() === 'ar' ? 'منتهي' : 'Expired';
        }

        return app()->getLocale() === 'ar' ? 'نشط' : 'Active';
    }
}