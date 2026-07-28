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
        'splits_snapshot',
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
        'amount'          => 'decimal:2',
        'splits_snapshot' => 'array',
        'approved_at'     => 'datetime',
        'paid_at'         => 'datetime',
    ];

    /** Exposed on every serialization (trainer app, admin panel, mobile) so the receipt link is always available. */
    protected $appends = ['transfer_proof_url'];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    /** New model: one payout can consolidate many bookings' payment splits. */
    public function splits()
    {
        return $this->hasMany(PaymentSplit::class, 'payout_id');
    }

    /** Legacy one-to-one link from the old "one payout per booking" model. */
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

    /**
     * The bookings this payout covers, for display purposes. Prefers the live splits()
     * relation; falls back to the splits_snapshot taken at request time for a rejected
     * payout, whose splits get released (payout_id nulled) and would otherwise show empty.
     */
    public function displaySplits(): array
    {
        $live = $this->relationLoaded('splits') ? $this->splits : $this->splits()->get();

        return $live->isNotEmpty() ? $live->toArray() : ($this->splits_snapshot ?? []);
    }
}
