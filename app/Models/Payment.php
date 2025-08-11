<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public $timestamps = true; // created_at et updated_at

    protected $fillable = [
        'user_id',
        'listing_id',
        'amount',
        'payment_method_id',
        'bank_card_id',
        'payment_status',
        'tran_ref',
        'cart_id',
        'resp_code',
        'resp_message',
        'verification_data'
    ];

    protected $casts = [
        'verification_data' => 'array',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * L'utilisateur ayant effectué le paiement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * L'annonce liée au paiement (facultatif).
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Le moyen de paiement utilisé (facultatif).
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(CardType::class, 'payment_method_id');
    }

    /**
     * La carte bancaire utilisée (facultatif).
     */
    public function bankCard(): BelongsTo
    {
        return $this->belongsTo(BankCard::class);
    }

    /**
     * Scope pour les paiements réussis
     */
    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }

    /**
     * Scope pour les paiements en attente
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope pour les paiements échoués
     */
    public function scopeFailed($query)
    {
        return $query->where('payment_status', 'failed');
    }

    /**
     * Vérifier si le paiement est réussi
     */
    public function isCompleted(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Vérifier si le paiement est en attente
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Vérifier si le paiement a échoué
     */
    public function isFailed(): bool
    {
        return $this->payment_status === 'failed';
    }
}
