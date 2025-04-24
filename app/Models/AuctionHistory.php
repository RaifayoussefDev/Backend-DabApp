<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class AuctionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id', 'seller_id', 'buyer_id', 'bid_amount', 'bid_date',
        'validated', 'validated_at', 'validator_id'
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
