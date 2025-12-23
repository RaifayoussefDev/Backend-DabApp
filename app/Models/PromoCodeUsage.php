<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeUsage extends Model
{
    protected $table = 'promo_code_usages';

    protected $fillable = [
        'promo_code_id',
        'user_id',
        'listing_id',
        'used_at'
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /**
     * Get the promo code associated with the usage.
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * Get the user who used the promo code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the listing for which the promo code was used.
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
