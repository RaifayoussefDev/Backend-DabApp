<?php

namespace App\Models\Marketplace;

use App\Models\BankCard;
use App\Models\CardType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends MarketplaceModel
{
    protected $table = 'marketplace_payments';

    protected $fillable = [
        'order_id', 'card_type_id', 'bank_card_id', 'gateway',
        'amount', 'currency', 'payment_status', 'cart_id', 'tran_ref', 'payment_url',
        'expires_at', 'paid_at', 'failure_reason', 'gateway_response',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'expires_at'       => 'datetime',
        'paid_at'          => 'datetime',
        'gateway_response' => 'array',
    ];

    protected $hidden = ['gateway_response'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class, 'card_type_id');
    }

    public function bankCard(): BelongsTo
    {
        return $this->belongsTo(BankCard::class, 'bank_card_id');
    }
}
