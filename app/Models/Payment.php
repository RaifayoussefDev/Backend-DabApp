<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public $timestamps = false; // created_at only

    protected $fillable = [
        'user_id',
        'listing_id',
        'amount',
        'payment_method_id',
        'bank_card_id',
        'payment_status',
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
}
