<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'booking_id',
        'route_date',
        'departure_point',
        'departure_point_ar',
        'arrival_point',
        'arrival_point_ar',
        'departure_time',
        'arrival_time',
        'available_slots',
        'booked_slots',
        'price_per_slot',
        'is_active'
    ];

    protected $casts = [
        'route_date' => 'date',
        'available_slots' => 'integer',
        'booked_slots' => 'integer',
        'price_per_slot' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    // Relations
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function booking()
    {
        return $this->belongsTo(ServiceBooking::class, 'booking_id');
    }

    public function stops()
    {
        return $this->hasMany(TransportRouteStop::class, 'route_id')->orderBy('stop_order');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('route_date', '>=', now()->toDateString());
    }

    public function scopeAvailable($query)
    {
        return $query->whereColumn('booked_slots', '<', 'available_slots');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('route_date', $date);
    }

    // Helper Methods
    public function hasAvailableSlots()
    {
        return $this->booked_slots < $this->available_slots;
    }

    public function getRemainingSlots()
    {
        return max(0, $this->available_slots - $this->booked_slots);
    }

    public function canBeBooked()
    {
        return $this->is_active 
               && $this->route_date->isFuture() 
               && $this->hasAvailableSlots();
    }

    public function incrementBookedSlots($count = 1)
    {
        $this->increment('booked_slots', $count);
    }

    public function decrementBookedSlots($count = 1)
    {
        $this->decrement('booked_slots', $count);
    }

    // Accessors
    public function getLocalizedDeparturePointAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->departure_point_ar : $this->departure_point;
    }

    public function getLocalizedArrivalPointAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->arrival_point_ar : $this->arrival_point;
    }

    public function getRouteNameAttribute()
    {
        return $this->localized_departure_point . ' → ' . $this->localized_arrival_point;
    }

    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->departure_time);
        $end = \Carbon\Carbon::parse($this->arrival_time);
        
        return $start->diffForHumans($end, true);
    }

    public function getAvailabilityLabelAttribute()
    {
        $remaining = $this->getRemainingSlots();
        $locale = app()->getLocale();
        
        if ($remaining === 0) {
            return $locale === 'ar' ? 'مكتمل' : 'Full';
        }
        
        return $remaining . ' ' . ($locale === 'ar' ? 'مقاعد متاحة' : 'slots available');
    }
}