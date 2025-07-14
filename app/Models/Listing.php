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
        'allow_submission',
        'listing_type_id',
        'contacting_channel',   // <== Doit être ici
        'seller_type', 
    ];

    public function auctions()
    {
        return $this->hasMany(AuctionHistory::class);
    }

    public function motorcycle()
    {
        return $this->hasOne(Motorcycle::class);
    }
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
    public function wishlistedBy()
    {
        return $this->hasMany(Wishlist::class);
    }
    public function images()
    {
        return $this->hasMany(ListingImage::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    public function listingType()
    {
        return $this->belongsTo(ListingType::class);
    }
    public function motorcycleBrand()
    {
        return $this->belongsTo(MotorcycleBrand::class, 'brand_id');
    }
    public function motorcycleModel()
    {
        return $this->belongsTo(MotorcycleModel::class, 'model_id');
    }
    public function motorcycleYear()
    {
        return $this->belongsTo(MotorcycleYear::class, 'year_id');
    }
    public function motorcycleType()
    {
        return $this->belongsTo(MotorcycleType::class, 'type_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function sparePart()
    {
        return $this->hasOne(SparePart::class);
    }

    public function licensePlate()
    {
        return $this->hasOne(LicensePlate::class);
    }
}
