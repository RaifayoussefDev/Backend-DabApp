<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Append-only audit trail for seller-contact reveals on listings
 * (the "show phone number" / "contact seller" action). Queryable for
 * seller-lead reporting; mirrors the AuthLog recorder pattern.
 */
class ListingContactReveal extends Model
{
    protected $fillable = [
        'listing_id',
        'seller_id',
        'user_id',
        'channel',
        'ip_address',
        'user_agent',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenience recorder — pulls IP / user-agent off the current request.
     */
    public static function record(Listing $listing, ?int $userId, ?Request $request = null): self
    {
        $request = $request ?? request();

        return static::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'user_id' => $userId,
            'channel' => $listing->contacting_channel,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
