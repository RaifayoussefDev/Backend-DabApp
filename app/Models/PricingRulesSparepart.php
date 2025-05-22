<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRulesSparepart extends Model
{
    use HasFactory;

    protected $table = 'pricing_rules_sparepart';

    protected $fillable = [
        'bike_part_category_id',
        'price',
    ];

    public function category()
    {
        return $this->belongsTo(BikePartCategory::class, 'bike_part_category_id');
    }
}
