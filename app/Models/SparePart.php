<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    protected $fillable = [
        'listing_id',
        'condition'
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    // Nouvelle relation : une pièce a plusieurs associations avec motos
    public function motorcycleAssociations()
    {
        return $this->hasMany(SparePartMotorcycle::class);
    }
}
