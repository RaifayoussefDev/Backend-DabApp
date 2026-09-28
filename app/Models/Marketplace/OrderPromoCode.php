<?php

namespace App\Models\Marketplace;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPromoCode extends MarketplaceModel
{
    protected $table = 'marketplace_order_promo_codes';

    protected $fillable = ['order_id', 'promo_code_id', 'vendor_id', 'code', 'discount_amount'];

    protected $casts = ['discount_amount' => 'decimal:2'];

    /** 'vendor' = discounts one vendor's lines only, 'platform' = whole order. */
    public function getScopeAttribute(): string
    {
        return $this->vendor_id ? 'vendor' : 'platform';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
