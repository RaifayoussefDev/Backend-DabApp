<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    protected $fillable = [
        'spare_part_id',
        'listing_id',
        'bike_part_brand_id',
        'bike_part_category_id',
        'condition'
    ];

    /**
     * Listing associated with this spare part.
     */
    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    /**
     * Motorcycle brand.
     */
    public function brand()
    {
        return $this->belongsTo(MotorcycleBrand::class, 'brand_id');
    }

    /**
     * Motorcycle model.
     */
    public function model()
    {
        return $this->belongsTo(MotorcycleModel::class, 'model_id');
    }

    /**
     * Motorcycle year.
     */
    public function year()
    {
        return $this->belongsTo(MotorcycleYear::class, 'year_id');
    }

    /**
     * Bike part brand (e.g. Brembo, Akrapovic).
     */
    public function bikePartBrand()
    {
        return $this->belongsTo(BikePartBrand::class, 'bike_part_brand_id');
    }

    /**
     * Bike part category (e.g. Brake, Exhaust).
     */
    public function bikePartCategory()
    {
        return $this->belongsTo(BikePartCategory::class, 'bike_part_category_id');
    }

    /**
     * Motorcycle associations (many-to-many through pivot).
     */
    public function motorcycleAssociations()
    {
        return $this->hasMany(SparePartMotorcycle::class);
    }
    public function motorcycles()
{
    return $this->hasMany(SparePartMotorcycle::class, 'spare_part_id');
}
}
