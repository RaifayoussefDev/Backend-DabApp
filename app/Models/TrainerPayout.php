<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'payment_split_id',
        'approved_by',
        'amount',
        'currency',
        'status',
        'bank_name',
        'iban',
        'transfer_ref',
        'transfer_proof',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at'     => 'datetime',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function split()
    {
        return $this->belongsTo(PaymentSplit::class, 'payment_split_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTransferProofUrlAttribute(): ?string
    {
        return $this->transfer_proof ? asset('storage/' . $this->transfer_proof) : null;
    }
}
