<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'price',
        'price_type',
        'seller_id',
        'category_id',
        'country_id',
        'city_id',
        'status',
        'auction_enabled',
        'minimum_bid',
        'product_state_id',
        'finish_id',
        'color_id',
        'allow_submission',
        'listing_type_id'
    ];

    public function auctions()
    {
        return $this->hasMany(AuctionHistory::class);
    }
    public function wishlistedBy()
    {
        return $this->hasMany(Wishlist::class);
    }
}
