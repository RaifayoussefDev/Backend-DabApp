<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'trainer_id',
        'user_id',
        'rating',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'rating'      => 'integer',
        'is_approved' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(TrainerBooking::class, 'booking_id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }
}
