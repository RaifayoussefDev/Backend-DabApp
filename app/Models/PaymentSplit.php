<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'booking_id',
        'course_booking_id',
        'trainer_id',
        'payout_id',
        'commission_setting_id',
        'total_amount',
        'commission_percentage',
        'commission_amount',
        'trainer_amount',
        'currency',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'total_amount'          => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount'     => 'decimal:2',
        'trainer_amount'        => 'decimal:2',
        'settled_at'            => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(TrainerPayment::class, 'payment_id');
    }

    public function booking()
    {
        return $this->belongsTo(TrainerBooking::class, 'booking_id');
    }

    public function courseBooking()
    {
        return $this->belongsTo(TrainerCourseBooking::class, 'course_booking_id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function commissionSetting()
    {
        return $this->belongsTo(CommissionSetting::class);
    }

    /** The monthly consolidated payout this split has been assigned to (new model). */
    public function payout()
    {
        return $this->belongsTo(TrainerPayout::class, 'payout_id');
    }

    /** Legacy one-to-one link from the old "one payout per booking" model. */
    public function legacyPayout()
    {
        return $this->hasOne(TrainerPayout::class, 'payment_split_id');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('payout_id');
    }

    public static function calculate(float $total, float $commissionPct): array
    {
        $commission = round($total * ($commissionPct / 100), 2);
        return [
            'commission_amount' => $commission,
            'trainer_amount'    => round($total - $commission, 2),
        ];
    }
}
