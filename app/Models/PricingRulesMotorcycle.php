<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingRulesMotorcycle extends Model
{
    protected $table = 'pricing_rules_motorcycle';

    protected $fillable = [
        'motorcycle_type_id',
        'price',
    ];

    public function motorcycleType()
    {
        return $this->belongsTo(MotorcycleType::class, 'motorcycle_type_id');
    }
}
