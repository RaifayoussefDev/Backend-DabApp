<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerCourseBookingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_booking_id',
        'trainer_id',
        'course_session_id',
        'session_number',
        'booking_date',
        'start_time',
        'end_time',
        'status',
        'start_otp',
        'completion_otp',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $hidden = [
        'start_otp',
        'completion_otp',
    ];

    protected $casts = [
        'booking_date'   => 'date',
        'session_number' => 'integer',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    public function courseBooking()
    {
        return $this->belongsTo(TrainerCourseBooking::class, 'course_booking_id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function courseSession()
    {
        return $this->belongsTo(TrainerCourseSession::class, 'course_session_id');
    }

    /** Generates and assigns fresh start/completion OTPs (does not persist — caller must save). */
    public function generateOtps(): void
    {
        $this->start_otp      = static::generateOtp();
        $this->completion_otp = static::generateOtp();
    }

    public static function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function verifyStartOtp(string $otp): bool
    {
        return $this->start_otp !== null && hash_equals($this->start_otp, $otp);
    }

    public function verifyCompletionOtp(string $otp): bool
    {
        return $this->completion_otp !== null && hash_equals($this->completion_otp, $otp);
    }
}
