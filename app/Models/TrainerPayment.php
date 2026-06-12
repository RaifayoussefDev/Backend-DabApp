<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'payment_status',
        'tran_ref',
        'cart_id',
        'resp_code',
        'resp_message',
        'paytabs_response',
        'currency',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'paytabs_response'  => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->hasOne(TrainerBooking::class, 'payment_id');
    }

    public function split()
    {
        return $this->hasOne(PaymentSplit::class, 'payment_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
