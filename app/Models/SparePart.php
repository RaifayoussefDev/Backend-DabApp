<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    protected $fillable = [
        'listing_id',
        'brand_id',
        'model_id',
        'year_id',
        'condition'
    ];

    public function brand()
    {
        return $this->belongsTo(MotorcycleBrand::class, 'brand_id');
    }

    public function model()
    {
        return $this->belongsTo(MotorcycleModel::class, 'model_id');
    }

    public function year()
    {
        return $this->belongsTo(MotorcycleYear::class, 'year_id');
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }
}
