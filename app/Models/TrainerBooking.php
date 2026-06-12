<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'user_id',
        'location_id',
        'booking_date',
        'start_time',
        'end_time',
        'duration_hours',
        'session_type',
        'status',
        'price',
        'payment_id',
        'payment_status',
        'notes',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'booking_date'   => 'date',
        'duration_hours' => 'integer',
        'price'          => 'decimal:2',
        'confirmed_at'   => 'datetime',
        'completed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(TrainerLocation::class, 'location_id');
    }

    public function payment()
    {
        return $this->belongsTo(TrainerPayment::class, 'payment_id');
    }

    public function paymentSplit()
    {
        return $this->hasOne(PaymentSplit::class, 'booking_id');
    }

    public function review()
    {
        return $this->hasOne(TrainerReview::class, 'booking_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_date', '>=', now()->toDateString())
                     ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public function hasConflict(string $date, string $start, string $end): bool
    {
        return static::where('trainer_id', $this->trainer_id)
            ->where('location_id', $this->location_id)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where(fn ($q) => $q
                ->whereBetween('start_time', [$start, $end])
                ->orWhereBetween('end_time', [$start, $end])
            )->exists();
    }

    public function canBeReviewed(): bool
    {
        return $this->status === 'completed' && !$this->review()->exists();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }
}
