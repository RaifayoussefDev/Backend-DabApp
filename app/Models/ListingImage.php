<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingImage extends Model
{
    protected $fillable = ['image_url', 'listing_id','is_plate_image'];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}

