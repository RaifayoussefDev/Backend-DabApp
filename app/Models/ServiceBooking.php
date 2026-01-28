<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'user_id',
        'provider_id',
        'booking_date',
        'start_time',
        'end_time',
        'status',
        'price',
        'payment_status',
        'payment_id',
        'pickup_location',
        'pickup_location_ar',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_location',
        'dropoff_location_ar',
        'dropoff_latitude',
        'dropoff_longitude',
        'distance_km',
        'notes',
        'notes_ar',
        'provider_notes',
        'provider_notes_ar',
        'cancellation_reason',
        'cancellation_reason_ar',
        'cancelled_by',
        'cancelled_at',
        'confirmed_at',
        'completed_at'
    ];

    protected $casts = [
        'booking_date' => 'date',
        'price' => 'decimal:2',
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'dropoff_latitude' => 'decimal:8',
        'dropoff_longitude' => 'decimal:8',
        'distance_km' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Relations
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function review()
    {
        return $this->hasOne(ServiceReview::class, 'booking_id');
    }

    public function chatSession()
    {
        return $this->hasOne(ChatSession::class, 'booking_id');
    }

    public function transportRoute()
    {
        return $this->hasOne(TransportRoute::class, 'booking_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_date', '>=', now()->toDateString())
                    ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopePast($query)
    {
        return $query->where('booking_date', '<', now()->toDateString())
                    ->orWhereIn('status', ['completed', 'cancelled']);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    // Helper Methods
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'confirmed']) 
               && $this->booking_date->gt(now()->addHours(24));
    }

    public function canBeReviewed()
    {
        return $this->status === 'completed' && !$this->review;
    }

    public function canStartChat()
    {
        return in_array($this->status, ['confirmed', 'in_progress']) 
               && $this->payment_status === 'completed';
    }

    public function isPaid()
    {
        return $this->payment_status === 'completed';
    }

    public function isActive()
    {
        return in_array($this->status, ['pending', 'confirmed', 'in_progress']);
    }

    // Accessors
    public function getLocalizedPickupLocationAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->pickup_location_ar : $this->pickup_location;
    }

    public function getLocalizedDropoffLocationAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->dropoff_location_ar : $this->dropoff_location;
    }

    public function getLocalizedNotesAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->notes_ar : $this->notes;
    }

    public function getLocalizedProviderNotesAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->provider_notes_ar : $this->provider_notes;
    }

    public function getLocalizedCancellationReasonAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->cancellation_reason_ar : $this->cancellation_reason;
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => ['en' => 'Pending', 'ar' => 'قيد الانتظار'],
            'confirmed' => ['en' => 'Confirmed', 'ar' => 'مؤكد'],
            'in_progress' => ['en' => 'In Progress', 'ar' => 'قيد التنفيذ'],
            'completed' => ['en' => 'Completed', 'ar' => 'مكتمل'],
            'cancelled' => ['en' => 'Cancelled', 'ar' => 'ملغي'],
            'rejected' => ['en' => 'Rejected', 'ar' => 'مرفوض']
        ];

        $locale = app()->getLocale();
        return $labels[$this->status][$locale] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'warning',
            'confirmed' => 'info',
            'in_progress' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'rejected' => 'danger'
        ];

        return $colors[$this->status] ?? 'secondary';
    }
}