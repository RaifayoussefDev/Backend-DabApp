<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Motorcycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'brand_id',
        'model_id',
        'year_id',
        'type_id',
        'engine',
        'mileage',
        'body_condition',
        'modified',
        'insurance',
        'general_condition',
        'vehicle_care',
        'transmission',
    ];

    public function brand()
    {
        return $this->belongsTo(MotorcycleBrand::class);
    }

    public function model()
    {
        return $this->belongsTo(MotorcycleModel::class);
    }

    public function year()
    {
        return $this->belongsTo(MotorcycleYear::class);
    }

    public function type()
    {
        return $this->belongsTo(MotorcycleType::class);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
