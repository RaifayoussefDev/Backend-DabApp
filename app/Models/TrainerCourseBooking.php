<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerCourseBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'trainer_id',
        'user_id',
        'status',
        'total_price',
        'payment_id',
        'payment_status',
        'notes',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'total_price'   => 'decimal:2',
        'confirmed_at'  => 'datetime',
        'completed_at'  => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(TrainerCourse::class, 'course_id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(TrainerPayment::class, 'payment_id');
    }

    public function sessions()
    {
        return $this->hasMany(TrainerCourseBookingSession::class, 'course_booking_id')->orderBy('session_number');
    }

    public function paymentSplit()
    {
        return $this->hasOne(PaymentSplit::class, 'course_booking_id');
    }

    public function review()
    {
        return $this->hasOne(TrainerReview::class, 'course_booking_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public function canBeReviewed(): bool
    {
        return $this->status === 'completed' && !$this->review()->exists();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'])
            && !$this->sessions()->whereIn('status', ['in_progress', 'completed'])->exists();
    }
}
