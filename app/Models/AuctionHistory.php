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

    // AJOUTEZ CETTE SECTION
    protected $casts = [
        'bid_amount' => 'decimal:2',
        'bid_date' => 'datetime',      // Convertir en Carbon
        'validated_at' => 'datetime',  // Convertir en Carbon
        'validated' => 'boolean',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validator_id');
    }
}
